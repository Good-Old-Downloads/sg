<?php
/*
    SceneGames
    Copyright (C) 2018  GoodOldDownloads

    This program is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program.  If not, see <http://www.gnu.org/licenses/>.

*/
require __DIR__ . '/db.php';

class Elastic
{
    private $client = null;
    public function __construct()
    {
        $this->client = Elasticsearch\ClientBuilder::create()->build();
    }
    public function Mapping(){
        $params = [
            'index' => 'games',
            'body' => [
                'settings' => [
                    'number_of_shards' => 1,
                    'analysis' => [
                        'filter' => [
                            'autocomplete_filter' => [
                                'type' => 'edge_ngram',
                                'min_gram' => 1,
                                'max_gram' => 20
                            ]
                        ],
                        'analyzer' => [
                            'autocomplete' => [
                                'type' => 'custom',
                                'tokenizer' => 'standard',
                                'filter' => [
                                    'lowercase',
                                    'autocomplete_filter' 
                                ]
                            ]
                        ]
                    ]
                ],
                'mappings' => [
                    'game' => [
                        'properties' => [
                            'id' => [
                                'type' => 'integer'
                            ],
                            'name' => [
                                'type' => 'text',
                                'analyzer' => 'autocomplete',
                                'fields' => [
                                    'raw' => [
                                        'type' => 'keyword'
                                    ]
                                ]
                            ],
                            'slug' => [
                                'type' => 'text'
                            ],
                            'date_added' => [
                                'type' => 'date',
                                'format' => 'epoch_second'
                            ],
                            'cover_id' => [
                                'type' => 'text'
                            ],
                            'screen_id' => [
                                'type' => 'text'
                            ],
                            'site' => [
                                'type' => 'text'
                            ],
                            'steam_id' => [
                                'type' => 'integer'
                            ],
                            'genres' => [
                                'type' => 'nested'
                            ],
                            'genres_slugs' => [
                                'type' => 'text'
                            ],
                            'releases' => [
                                'type' => 'nested',
                                'properties' => [
                                    'name' => [
                                        'type' => 'keyword'
                                    ],
                                    'group' => [
                                        'type' => 'keyword'
                                    ],
                                    'nfo' => [
                                        'type' => 'keyword',
                                        'index' => false
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ];
        $this->client->indices()->create($params);
    }
    public function Clear(){
        $this->client->indices()->delete(['index' => 'games']);
    }
    public function InsertAll(){
        global $dbh;
        $params = null;
        $client = $this->client;
        $getGames = $dbh->prepare("SELECT `id`, `name`, `slug`, `date_added`, `cover_id`, `screen_id`, `site`, `steam_id` FROM `games`");
        $getGames->execute();
        $games = $getGames->fetchAll(\PDO::FETCH_ASSOC);

        $getGenre = $dbh->prepare("
        SELECT `genre_id` as `id`, `name`, `slug` FROM `game_genres` as GAM
        LEFT JOIN `genres` as GEN ON
        GEN.id = GAM.genre_id
        WHERE GAM.game_id = :game_id");
        $getGenre->bindParam(':game_id', $gameId, \PDO::PARAM_INT);

        $getReleases = $dbh->prepare("
        SELECT * FROM `releases`
        WHERE game_id = :game_id AND `hidden` != 1
        ORDER BY `date` DESC");
        $getReleases->bindParam(':game_id', $gameId, \PDO::PARAM_INT);

        $this->Mapping();
        foreach (array_chunk($games, 500, true) as $chunk) {
            foreach ($chunk as $key => $game) {
                $gameId = $game['id'];
                $getGenre->execute();
                $genres = $getGenre->fetchAll(\PDO::FETCH_ASSOC);
                $getReleases->execute();
                $releases = $getReleases->fetchAll(\PDO::FETCH_ASSOC);
                if ($getReleases->rowCount() === 0) {
                    // Skip if no releases
                    continue;
                }
                $params['body'][] = array(
                    'index' => array(
                        '_index' => 'games',
                        '_type' => 'game',
                        '_id' => $game['id']
                    ),
                );

                $genres_flat = [];
                foreach ($genres as $key => $value) {
                    $genres_flat[] = $value['slug'];
                }

                $params['body'][] = [
                    'name' => $game['name'],
                    'slug' => $game['slug'],
                    'slug' => $game['slug'],
                    'date_added' => $game['date_added'],
                    'cover_id' => $game['cover_id'],
                    'screen_id' => $game['screen_id'],
                    'site' => $game['site'],
                    'steam_id' => $game['steam_id'],
                    'genres' => $genres,
                    'genres_slugs' => $genres_flat,
                    'releases' => $releases
                ];
            }
            if ($params !== null) {
                $responses = $client->bulk($params);
                unset($params);
            }
        }
        return true;
    }
    public function UpdateGame($gameId)
    {
        global $dbh;
        try {
            if ($gameId == null) {
                throw new \Exception("Game ID is null");
            }
            $getGame = $dbh->prepare("SELECT `id`, `name`, `slug`, `date_added`, `cover_id`, `screen_id`, `site`, `steam_id` FROM `games` WHERE `id` = :game_id");
            $getGame->bindParam(':game_id', $gameId, \PDO::PARAM_INT);
            $getGame->execute();
            $game = $getGame->fetch(\PDO::FETCH_ASSOC);
            $gameId = $game['id'];

            if ($getGame->rowCount() === 0) {
                throw new \Exception("Game does not exist");
            }

            $getGenre = $dbh->prepare("
            SELECT `genre_id` as `id`, `name`, `slug` FROM `game_genres` as GAM
            LEFT JOIN `genres` as GEN ON
            GEN.id = GAM.genre_id
            WHERE GAM.game_id = :game_id");
            $getGenre->bindParam(':game_id', $gameId, \PDO::PARAM_INT);
            $getGenre->execute();
            $genres = $getGenre->fetchAll(\PDO::FETCH_ASSOC);

            $genres_flat = [];
            foreach ($genres as $key => $value) {
                $genres_flat[] = $value['slug'];
            }

            $getReleases = $dbh->prepare("
            SELECT * FROM `releases`
            WHERE game_id = :game_id AND `hidden` != 1
            ORDER BY `date` DESC");
            $getReleases->bindParam(':game_id', $gameId, \PDO::PARAM_INT);
            $getReleases->execute();
            $releases = $getReleases->fetchAll(\PDO::FETCH_ASSOC);

            $params = [
                'index' => 'games',
                'type' => 'game',
                'id' => $gameId,
                'body' => [
                    'doc' => [
                        'name' => $game['name'],
                        'slug' => $game['slug'],
                        'slug' => $game['slug'],
                        'date_added' => $game['date_added'],
                        'cover_id' => $game['cover_id'],
                        'screen_id' => $game['screen_id'],
                        'site' => $game['site'],
                        'steam_id' => $game['steam_id'],
                        'genres' => $genres,
                        'genres_slugs' => $genres_flat,
                        'releases' => $releases
                    ],
                    'doc_as_upsert' => true
                ]
            ];
            // throw here after checking error
            $responses = $this->client->update($params);
        } catch(\Exception $e){
            return $e->getMessage();
        }
        return $responses;
    }
    public function msearch($params){
        return $this->client->msearch($params);
    }
    public function search($params){
        return $this->client->search($params);
    }
    public function count($params){
        return $this->client->count($params);
    }
}