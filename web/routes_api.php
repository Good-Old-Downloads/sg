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


/*
    Private
*/
$checkApiKey = function($request, $response, $next) {
    $CONFIG = $this->get('site_config');
    if ($request->hasHeader('HTTP_X_API_KEY')) {
        $key = $request->getHeader("HTTP_X_API_KEY")[0];
        try {
            $SCENE = new GoodOldDownloads\SceneAPI($key);
            $request = $request->withAttribute('SCENE', $SCENE);
            return $next($request, $response);
        } catch (Exception $e) {
            return $response->withJson(['SUCCESS' => false, 'MSG' => $e->getMessage()], 500);
        }
    } else {
        return $response->withJson(['SUCCESS' => false, 'MSG' => "API key not set"], 400);
    }
};

$app->group('/api', function () {
    $this->post('/game/add', function ($request, $response, $args) {
        // must have ?name= param
        if (array_key_exists("name", $request->getParams()) && !empty(trim($request->getParam("name")))) {
            $SCENE = $request->getAttribute('SCENE');
            $client = new GuzzleHttp\Client();
            $gamename = $request->getParam("name");
            if (filter_var($gamename, FILTER_VALIDATE_URL) !== false) {
                $client = new GuzzleHttp\Client();
                $res = $client->request('GET', $gamename, [
                    'headers' => ['User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:59.0) Gecko/20100101 Firefox/59.0']
                ]);
                preg_match("/games\/([0-9]+)/", $res->getBody(), $matches);
                $igdbID = intval($matches[1]);
                $game = $SCENE->insertGame($igdbID, true);
            } else {
                $game = $SCENE->insertGame($gamename);
            }
            if ($game) {
                return $response->withJson(['SUCCESS' => true, 'MSG' => "$game->name added."]);
            } else {
                return $response->withJson(['SUCCESS' => false, 'MSG' => "idk what happened"], 500);
            }
        } else {
            return $response->withJson(['SUCCESS' => false, 'MSG' => "Missing params"], 400);
        }
    });
    $this->post('/game/add/steam', function ($request, $response, $args) {
        // must have ?name= param
        if (array_key_exists("name", $request->getParams()) && !empty(trim($request->getParam("name")))) {
            $SCENE = $request->getAttribute('SCENE');
            $gamename = $request->getParam("name");
            if (filter_var($gamename, FILTER_VALIDATE_URL) !== false) {
                preg_match("/\/app\/(\d+)\/?/", $gamename, $matches);
                $steamID = intval($matches[1]);
                $game = $SCENE->insertGame_force_steam($steamID, true);
            } elseif (is_numeric($gamename)) {
                $game = $SCENE->insertGame_force_steam(intval($gamename), true);
            } else {
                $game = $SCENE->insertGame_force_steam($gamename);
            }

            if ($game) {
                return $response->withJson(['SUCCESS' => true, 'MSG' => "$game->name added."]);
            } else {
                return $response->withJson(['SUCCESS' => false, 'MSG' => "Failed to add game."], 500);
            }
        } else {
            return $response->withJson(['SUCCESS' => false, 'MSG' => "Missing params"], 400);
        }

    });
    $this->post('/nfo/add', function ($request, $response, $args) {
        $dbh = $this->get('dbh');
        $nfoFile = $request->getUploadedFiles()['nfo'];
        if (array_key_exists("rlsName", $request->getParams()) && !empty(trim($request->getParam("rlsName"))) && $nfoFile !== null) {
            $SCENE = $request->getAttribute('SCENE');
            $file = file_get_contents($nfoFile->file);
            $rlsName = trim($request->getParam("rlsName"));
            $encoding = mb_detect_encoding($file, 'UTF-8, ISO-8859-15', true);

            if ($encoding === "UTF-8") {
                $nfo = $file;
            } else {
                $nfo = mb_convert_encoding($file, 'UTF-8', 'CP866');
            }

            if ($nfo) {
                $addNFO = $dbh->prepare("UPDATE `releases` SET `nfo` = :nfo WHERE `name` = :name");
                $addNFO->bindParam(':name', $rlsName, \PDO::PARAM_STR);
                $addNFO->bindParam(':nfo', $nfo, \PDO::PARAM_STR);
                $addNFO->execute();
                if ($addNFO->execute()) {
                    return $response->withJson(['SUCCESS' => true]);
                } else {
                    return $response->withJson(['SUCCESS' => false, 'MSG' => "Couldn't add to database."]);
                }
            } else {
                return $response->withJson(['SUCCESS' => false], 500);
            }
        } else {
            return $response->withJson(['SUCCESS' => false, 'MSG' => "Missing params"], 400);
        }
    });
    $this->get('/queue', function ($request, $response, $args) {
        $dbh = $this->get('dbh');
        $queue = $dbh->prepare("
            SELECT `name`, `state`, COUNT(`release_id`) as `votes` FROM `votes`
            JOIN `releases` ON `release_id` = `id`
            WHERE `state` = 'COMPLETE'
            GROUP BY `release_id`
            ORDER BY `votes` DESC, `release_id` ASC
            LIMIT 1
        ");
        $queue->execute();
        $nextUp = $queue->fetch(PDO::FETCH_ASSOC);
        return $response->withJson($nextUp);
    });
})->add($checkApiKey);

$app->group('/api/release', function () {
    $this->post('/delete', function ($request, $response, $args) {
        $dbh = $this->get('dbh');
        if (array_key_exists("releaseIds", $request->getParams())) {
            try {
                $SCENE = $request->getAttribute('SCENE');
            } catch (Exception $e) {
                return $response->withJson(['SUCCESS' => false, 'MSG' => $e->getMessage()], 500);
            }
            $Elastic = new Elastic();

            $releasesFrom = $request->getParam('releaseIds');

            // Get game before changing things
            $getGame = $dbh->prepare("SELECT `game_id` FROM `releases` WHERE `id` = :releaseId");
            $getGame->bindParam(':releaseId', $releasesFrom[0], \PDO::PARAM_INT);
            $getGame->execute();
            $gameId = $getGame->fetchColumn();

            $hide = $dbh->prepare("UPDATE `releases` SET `hidden` = 1 WHERE `id` = :rlsid");
            $hide->bindParam(':rlsid', $rlsId, \PDO::PARAM_INT);

            foreach ($releasesFrom as $key => $releaseId) {
                $rlsId = $releaseId;
                $hide->execute();
            }

            $Elastic->UpdateGame($gameId);

            return $response->withJson(['SUCCESS' => true, 'MSG' => 'Successfully Hidden!']);
        } else {
            return $response->withJson(['SUCCESS' => false, 'MSG' => "Missing params"], 400);
        }
    });
    $this->post('/move', function ($request, $response, $args) {
        $dbh = $this->get('dbh');
        if (array_key_exists("game_to", $request->getParams()) && !empty(trim($request->getParam("game_to"))) &&
            array_key_exists("releaseIds", $request->getParams()) && !empty($request->getParam("releaseIds"))) {
            try {
                $SCENE = $request->getAttribute('SCENE');
            } catch (Exception $e) {
                return $response->withJson(['SUCCESS' => false, 'MSG' => $e->getMessage()], 500);
            }
            $Elastic = new Elastic();

            $releasesFrom = $request->getParam('releaseIds');
            $gameTo = intval($request->getParam('game_to'));

            // Get game before changing things
            $getGame = $dbh->prepare("SELECT `game_id` FROM `releases` WHERE `id` = :releaseId");
            $getGame->bindParam(':releaseId', $releasesFrom[0], \PDO::PARAM_INT);
            $getGame->execute();
            $gameId = $getGame->fetchColumn();

            // Check if allowed to move this (if own release) -later
            $move = $dbh->prepare("UPDATE `releases` SET `game_id` = :to WHERE `id` = :releaseId");
            $move->bindParam(':to', $gameTo, \PDO::PARAM_INT);
            $move->bindParam(':releaseId', $rlsId, \PDO::PARAM_INT);

            foreach ($releasesFrom as $key => $releaseId) {
                $rlsId = $releaseId;
                $move->execute();
            }

            $Elastic->UpdateGame($gameId); // Update where it used to be
            $Elastic->UpdateGame($gameTo); // Also update game that released moved to

            return $response->withJson(['SUCCESS' => true, 'MSG' => 'Successfully Moved!']);
        } else {
            return $response->withJson(['SUCCESS' => false, 'MSG' => "Missing params"], 400);
        }
    });
    $this->post('/magnet', function ($request, $response, $args) {
        $dbh = $this->get('dbh');
        if (array_key_exists("release", $request->getParams()) && !empty(trim($request->getParam("release"))) &&
            array_key_exists("hash", $request->getParams()) && !empty(trim($request->getParam("hash")))) {

            try {
                $SCENE = $request->getAttribute('SCENE');
            } catch (Exception $e) {
                return $response->withJson(['SUCCESS' => false, 'MSG' => $e->getMessage()], 500);
            }

            $releasename = trim($request->getParam('release'));
            $infohash = trim($request->getParam('hash'));

            if ($SCENE->insertMagnet($infohash, $releasename)) {
                $getGameId = $dbh->prepare("SELECT `game_id` FROM `releases` WHERE `name` = :rlsname");
                $getGameId->bindParam(':rlsname', $releasename, \PDO::PARAM_STR);
                $getGameId->execute();
                $gameId = $getGameId->fetchColumn();
                $Elastic = new \Elastic();
                $Elastic->UpdateGame($gameId);
                return $response->withJson(['SUCCESS' => true]);
            } else {
                return $response->withJson(['SUCCESS' => false], 500);
            }
        } else {
            return $response->withJson(['SUCCESS' => false, 'MSG' => "Missing params"], 400);
        }
    });
    $this->post('/torrent', function ($request, $response, $args) {
        $dbh = $this->get('dbh');
        if (array_key_exists("release", $request->getParams()) && !empty(trim($request->getParam("release"))) &&
            array_key_exists("link", $request->getParams()) && !empty(trim($request->getParam("link")))) {

            try {
                $SCENE = $request->getAttribute('SCENE');
            } catch (Exception $e) {
                return $response->withJson(['SUCCESS' => false, 'MSG' => $e->getMessage()], 500);
            }

            $releasename = trim($request->getParam('release'));
            $torrentlink = trim($request->getParam('link'));

            $insertTorrent = $SCENE->insertTorrent($torrentlink, $releasename);
            $getGameId = $dbh->prepare("SELECT `game_id` FROM `releases` WHERE `name` = :rlsname");
            $getGameId->bindParam(':rlsname', $releasename, \PDO::PARAM_STR);
            $getGameId->execute();
            $gameId = $getGameId->fetchColumn();

            if ($insertTorrent) {
                $Elastic = new \Elastic();
                $Elastic->UpdateGame($gameId);
                return $response->withJson(['SUCCESS' => true]);
            } else {
                return $response->withJson(['SUCCESS' => false], 500);
            }
        } else {
            return $response->withJson(['SUCCESS' => false, 'MSG' => "Missing params"], 400);
        }
    });
    $this->post('/pre-reupload', function ($request, $response, $args) {
        $dbh = $this->get('dbh');
        if (array_key_exists("release", $request->getParams()) && !empty(trim($request->getParam("release")))) {
            $SCENE = $request->getAttribute('SCENE');
            $releasename = trim($request->getParam('release'));
            $dbh->beginTransaction();

            try {
                $getReleaseId = $dbh->prepare("SELECT `id` FROM `releases` WHERE `name` = :releasename");
                $getReleaseId->bindParam(':releasename', $releasename, \PDO::PARAM_STR);
                $getReleaseId->execute();
                $releaseId = $getReleaseId->fetchColumn(0);

                // Clear links
                $removeLinks = $dbh->prepare("DELETE FROM `links` WHERE `release_id` = :rlsid");
                $removeLinks->bindParam(':rlsid', $releaseId, \PDO::PARAM_INT);
                $removeLinks->execute();
                
                // Set last upload time and state
                $updateCols = $dbh->prepare("UPDATE `releases` SET `last_upload` = UNIX_TIMESTAMP(), `state` = 'UPLOADING' WHERE `id` = :rlsid");
                $updateCols->bindParam(':rlsid', $releaseId, \PDO::PARAM_INT);
                $updateCols->execute();

                $dbh->commit();
                return $response->withJson(['SUCCESS' => true]);
            } catch(Exception $e){
                $dbh->rollBack();
                return $response->withJson(['SUCCESS' => false, 'MSG' => $e->getMessage()], 500);
            }
        } else {
            return $response->withJson(['SUCCESS' => false, 'MSG' => "Missing params"], 400);
        }
    });
    $this->post('/post-reupload', function ($request, $response, $args) {
        $dbh = $this->get('dbh');
        if (array_key_exists("release", $request->getParams()) && !empty(trim($request->getParam("release")))) {
            $SCENE = $request->getAttribute('SCENE');
            $releasename = trim($request->getParam('release'));
            $dbh->beginTransaction();

            try {
                $getReleaseId = $dbh->prepare("SELECT `id` FROM `releases` WHERE `name` = :releasename");
                $getReleaseId->bindParam(':releasename', $releasename, \PDO::PARAM_STR);
                $getReleaseId->execute();
                $releaseId = $getReleaseId->fetchColumn(0);

                // Set last upload time and state
                $updateCols = $dbh->prepare("UPDATE `releases` SET `state` = 'COMPLETE' WHERE `id` = :rlsid");
                $updateCols->bindParam(':rlsid', $releaseId, \PDO::PARAM_INT);
                $updateCols->execute();

                // Clear votes
                $clearLinks = $dbh->prepare("DELETE FROM `votes` WHERE `release_id` = :rlsid");
                $clearLinks->bindParam(':rlsid', $releaseId, \PDO::PARAM_INT);
                $clearLinks->execute();

                $dbh->commit();
                return $response->withJson(['SUCCESS' => true]);
            } catch(Exception $e){
                $dbh->rollBack();
                return $response->withJson(['SUCCESS' => false, 'MSG' => $e->getMessage()], 500);
            }
        } else {
            return $response->withJson(['SUCCESS' => false, 'MSG' => "Missing params"], 400);
        }
    });
})->add($checkApiKey);

$app->group('/api/release/scene', function () {
    $this->post('/add', function ($request, $response, $args) {
        $dbh = $this->get('dbh');
        if (array_key_exists("name", $request->getParams()) && !empty(trim($request->getParam("name")))) {
            $SCENE = $request->getAttribute('SCENE');

            $gamename = trim($request->getParam("name"));
            $release = $SCENE->parseRlsName($gamename);
            if ($release) {
                // check if release is already here
                $checkExists = $dbh->prepare("SELECT `id` FROM `releases` WHERE `name` = :name");
                $checkExists->bindParam(':name', $release['original'], \PDO::PARAM_STR);
                $checkExists->execute();
                if ($checkExists->rowCount() > 0) {
                    // Already exists! (replace files, or do nothing?)
                    echo json_encode(array('SUCCESS' => false, "MSG" => "Release already exists."));
                } else {
                    // Doesn't exist

                    // Check if the exact game is here
                    $checkName = $dbh->prepare("SELECT `id` FROM `games` WHERE `name` = :name");
                    $checkName->bindParam(':name', $release['name'], \PDO::PARAM_STR);
                    $checkName->execute();
                    $checkName = $checkName->fetchColumn();
                    if($checkName){
                        // Exact game exists, no need to add a new game entry

                        // Insert release
                        $insertRls = $SCENE->insertRelease($release, $checkName);
                        if ($insertRls) {
                            echo json_encode(array('SUCCESS' => true, 'MSG' => $insertRls['original']." added."));
                        } else {
                            echo json_encode(array('SUCCESS' => false, 'MSG' => "Failed to add release."));
                        }
                        
                    } else {
                        // Game doesn't exist
                        // Add game
                        $game = $SCENE->insertGame_force_steam($release['name']);

                        // IGDB fallback
                        if (!$game) {
                            $game = $SCENE->insertGame($release['name']);
                        }
                        if ($game) {
                            // Game added now add release
                            $insertRls = $SCENE->insertRelease($release, $game->scene_added_game_id);
                            if ($insertRls) {
                                return $response->withJson(['SUCCESS' => true, 'MSG' => $insertRls['original']." added."]);
                            } else {
                                return $response->withJson(['SUCCESS' => false, 'MSG' => "Failed to add release."]);
                            }
                        } else {
                            return $response->withJson(['SUCCESS' => false, 'MSG' => "Game failed to get added."], 500);
                        }
                    }
                }
            } else {
                return $response->withJson(['SUCCESS' => false], 500);
            }
        } else {
            return $response->withJson(['SUCCESS' => false, 'MSG' => "Missing params"], 400);
        }
        return $response->withJson($nextUp);
    });
    $this->post('/parse-name', function ($request, $response, $args) {
        if (array_key_exists("name", $request->getParams()) && !empty(trim($request->getParam("name")))) {
            $SCENE = $request->getAttribute('SCENE');

            $gamename = trim($request->getParam("name"));
            $release = $SCENE->parseRlsName($gamename);
            if ($release){
                return $response->withJson(['SUCCESS' => true, 'RELEASE' => $release]);
            } else {
                return $response->withJson(['SUCCESS' => false], 500);
            }
        } else {
            return $response->withJson(['SUCCESS' => false, 'MSG' => "Missing params"], 400);
        }
        return $response->withJson($nextUp);
    });
    $this->post('/size/add', function ($request, $response, $args) {
        $dbh = $this->get('dbh');
        if (array_key_exists("release", $request->getParams()) && !empty(trim($request->getParam("release")))) {
            if (!is_numeric($request->getParam('size'))) {
                return $response->withJson(['SUCCESS' => false, 'MSG' => "Size not numeric"], 400);
            }

            $releasename = trim($_POST['release']);
            $size = intval($_POST['size']);

            $updateRls = $dbh->prepare("
                    UPDATE `releases`
                    SET `size` = :size
                    WHERE `name` = :rls_name
                ");
            $updateRls->bindParam(':size', $size, \PDO::PARAM_INT);
            $updateRls->bindParam(':rls_name', $releasename, \PDO::PARAM_STR);

            if ($updateRls->execute()) {
                return $response->withJson(['SUCCESS' => true]);
            } else {
                return $response->withJson(['SUCCESS' => false], 500);
            }
        } else {
            return $response->withJson(['SUCCESS' => false, 'MSG' => "Missing params"], 400);
        }
        return $response->withJson($nextUp);
    });
})->add($checkApiKey);

$app->group('/api/release/scene/links', function () {
    $this->post('/add', function ($request, $response, $args) {
        $dbh = $this->get('dbh');
        if (array_key_exists("link", $request->getParams()) && !empty(trim($request->getParam("link")))) {
            $SCENE = $request->getAttribute('SCENE');

            $releasename = trim($request->getParam('release'));
            $link = $request->getParam('link');

            if ($request->getParam('id') !== null) {
                $releaseId = $request->getParam('id');
            }

            // New
            if ($request->getParam('host') != null) {
                $host = trim($request->getParam('host'));
                
                $filename = null;
                $linksafe = null;
                if (!empty($request->getParam('filename'))) {
                    $filename = trim($request->getParam('filename'));
                }
                if (!empty($request->getParam('link_safe'))) {
                    $linksafe = trim($request->getParam('link_safe'));
                }
                $insertLink = $SCENE->insertLinkNew($releasename, $link, $linksafe, $filename, $host);
            } else {
                // Old
                if ($releaseId) {
                    $insertLink = $SCENE->insertLink($releaseId, $link, 'DONE');
                } else {
                    $insertLink = $SCENE->insertLink($releasename, $link, 'DONE');
                }
            }

            if ($insertLink) {
                return $response->withJson(['SUCCESS' => true]);
            } else {
                return $response->withJson(['SUCCESS' => false], 500);
            }
        } else {
            return $response->withJson(['SUCCESS' => false, 'MSG' => "Missing params"], 400);
        }
    });
    $this->post('/remove', function ($request, $response, $args) {
        $dbh = $this->get('dbh');
        if (array_key_exists("linkIds", $request->getParams())) {
            $SCENE = $request->getAttribute('SCENE');

            $linkIds = $request->getParam('linkIds');
            $removeLinks = $SCENE->removeLink($linkIds);

            if ($removeLinks) {
                return $response->withJson(['SUCCESS' => true]);
            } else {
                return $response->withJson(['SUCCESS' => false], 500);
            }
        } else {
            return $response->withJson(['SUCCESS' => false, 'MSG' => "Missing params"], 400);
        }
    });
    $this->post('/update', function ($request, $response, $args) {
        $dbh = $this->get('dbh');
        if (array_key_exists("id", $request->getParams()) && array_key_exists("link", $request->getParams())) {
            $SCENE = $request->getAttribute('SCENE');

            $linkId = intval($request->getParam('id'));
            $link = trim($request->getParam('link'));

            $status = $request->getParam('status');
            if ($request->getParam('status') == null) {
                $status = 'DONE';
            }

            $filename = null;
            if ($request->getParam('filename') != null) {
                $filename = trim($request->getParam('filename'));
            }
            $updateLink = $SCENE->updateLink($linkId, $link, $filename, $status);

            if ($updateLink) {
                return $response->withJson(['SUCCESS' => true]);
            } else {
                return $response->withJson(['SUCCESS' => false], 500);
            }
        } else {
            return $response->withJson(['SUCCESS' => false, 'MSG' => "Missing params"], 400);
        }
    });
})->add($checkApiKey);

/*
    Public
*/
$app->group('/api', function () {
    $this->get('/autocomplete', function ($request, $response, $args) {
        $Elastic = new Elastic();
        $term = $request->getParam('term');
        if ($request->getParam('term') == null) {
            return $response->withStatus(400);
        }
        $filter = ['responses.hits.total', 'responses.hits.hits._id', 'responses.hits.hits._source', '-responses.hits.hits._source.releases'];
        if ($request->getParam('show_releases') == null) {
            $filter = [
                'responses.hits.total',
                'responses.hits.hits._id',
                'responses.hits.hits._source',
                '-responses.hits.hits._source.id',
                '-responses.hits.hits._source.releases.game_id',
                '-responses.hits.hits._source.releases.group',
                '-responses.hits.hits._source.releases.type',
                '-responses.hits.hits._source.releases.lang',
                '-responses.hits.hits._source.releases.version',
                '-responses.hits.hits._source.releases.is_rip',
                '-responses.hits.hits._source.releases.is_addon',
                '-responses.hits.hits._source.releases.links',
                '-responses.hits.hits._source.releases.torrent',
                '-responses.hits.hits._source.releases.platform',
                '-responses.hits.hits._source.releases.hidden',
                '-responses.hits.hits._source.releases.nuked',
                '-responses.hits.hits._source.releases.nfo'
            ];
        }

        // Do a multi search, first one looks for exact release, second is a "normal" search
        $params = [
            'filter_path' => $filter,
            'body'  => [
                [
                    'index' => 'games', 
                    'type' => 'game',
                ],
                [
                    'query' => [
                        'nested' => [
                            'path' => 'releases',
                            'query' => [
                                'term' => [
                                    'releases.name' => [
                                        'value' => $term
                                    ]
                                ]
                            ]
                        ]
                    ],
                ],
                [
                    'index' => 'games', 
                    'type' => 'game',
                ],
                [
                    'query' => [
                        'match' => [
                            'name' => [
                                'query' => $term
                            ],
                        ]
                    ]
                ]
            ]
        ];
        $responses = $Elastic->msearch($params);
        $exactRelease = $responses['responses'][0];
        $matches = $responses['responses'][1];

        // If exact release found
        if ($exactRelease['hits']['total'] > 0) {
            return $response->withJson($exactRelease['hits']);
        } else {
            return $response->withJson($matches['hits']);
        }
    });

    $this->get('/latest', function ($request, $response, $args) {
        $dbh = $this->get('dbh');
        $allowedOutputs = ['json', 'plain', 'csv', 'tsv', 'html'];
        $page = 0;
        if ($request->getParam('page')) {
            $page = intval($request->getParam('page')) * 25;
        }
        if(in_array($request->getParam('output'), $allowedOutputs)){
            $getReleases = $dbh->prepare("SELECT `name`, `date` FROM `releases` WHERE `hidden` = 0 ORDER BY `date` DESC LIMIT :page, 25");
            $getReleases->bindParam(':page', $page, \PDO::PARAM_INT);
            $getReleases->execute();
            switch ($request->getParam('output')) {
                case 'json':
                    $releases = $getReleases->fetchAll(PDO::FETCH_ASSOC);
                    $output = [];
                    foreach ($releases as $release) {
                        $output[] = [
                            'name' => $release['name'],
                            'date' => intval($release['date'])
                        ];
                    }
                    return $response->withJson($output);
                    break;
                
                case 'plain':
                    $releases = $getReleases->fetchAll(PDO::FETCH_COLUMN, 0);
                    return $response->withHeader('Content-Type', 'text/plain')->write(join($releases, "\n"));
                    break;
                case 'csv':
                    $releases = $getReleases->fetchAll(PDO::FETCH_ASSOC);
                    $output = '';
                    foreach ($releases as $release) {
                        $output .= '"'.$release['name'].'","'.date("c", strtotime('@'.$release['date']))."\"\n";
                    }
                    return $response->withHeader('Content-Type', 'text/csv')->write($output);
                    break;
                case 'tsv':
                    $releases = $getReleases->fetchAll(PDO::FETCH_ASSOC);
                    $output = '';
                    foreach ($releases as $release) {
                        $output .= "\"".$release['name']."\"\t\"".date("c", strtotime('@'.$release['date']))."\"\n";
                    }
                    return $response->withHeader('Content-Type', 'text/tsv')->write($output);
                    break;
                case 'html':
                    $releases = $getReleases->fetchAll(PDO::FETCH_ASSOC);
                    $output = '<!doctype html><html lang=en><head><meta charset=utf-8><title>latest</title></head><body>';
                    foreach ($releases as $release) {
                        $output .= $release['name']."<br>";
                    }
                    if (intval($request->getParam('page')) !== 0) {
                        $output .= '<a href="/api/latest?output=html&amp;page='.(intval($request->getParam('page'))-1).'">&lt;</a> ';
                    }
                    $output .= '<a href="/api/latest?output=html&amp;page='.(intval($request->getParam('page'))+1).'">&gt;</a>';
                    $output .= '</body></html>';
                    return $response->write($output);
                    break;
            }
        }
    });

    /*
        VisualCaptcha
    */
    $this->group('/captcha', function () {
        $this->get('/begin/{howmany}', function ($request, $response, $args) {
            $dbh = $this->get('dbh');
            $captcha = $this->get('visualCaptcha');
            $captcha->generate($args['howmany']);
            return $response->withJson($captcha->getFrontEndData());
        });
        $this->get('/img/{index}', function ($request, $response, $args) {
            $dbh = $this->get('dbh');
            $captcha = $this->get('visualCaptcha');
            $headers = [];
            $image = $captcha->streamImage($headers, $args['index'], false);
            if (!$image) {
                throw new \Slim\Exception\NotFoundException($request, $response);
            } else {
                // Set headers
                foreach ($headers as $key => $val) {
                    $response = $response->withHeader($key, $val);
                }
                return $response;
            }
        });
        $this->post('/vote', function ($request, $response, $args) {
            $dbh = $this->get('dbh');
            $Config = new \GoodOldDownloads\Config();
            $ipAddress = $request->getAttribute('ip_address');
            if ($request->getParam('rls_id') != null && is_numeric($request->getParam('rls_id'))) {
                $session = new \visualCaptcha\Session();
                $captcha = $this->get('visualCaptcha');

                $id = $request->getParam('rls_id');

                // check captcha
                $frontendData = $captcha->getFrontendData();
                $captchaError = false;
                if (!$frontendData) {
                    $captchaError = _('Invalid Captcha Data');
                } else {
                    // If an image field name was submitted, try to validate it
                    if ($imageAnswer = $request->getParam($frontendData['imageFieldName'])){
                        // If incorrect
                        if (!$captcha->validateImage($imageAnswer)){
                            $captchaError = _('Incorrect Captcha Image. Please try again.');
                        }
                        // Generate new captcha or else the user can just rety the old one
                        $howMany = count($captcha->getImageOptions());
                        $captcha->generate($howMany);
                    } else {
                        $captchaError = _('Invalid Captcha Data');
                    }
                }

                if ($captchaError !== false) {
                    return $response->withJson(['SUCCESS' => false, 'MSG' => $captchaError]);
                }

                // if not allowing drive voting
                if (boolval($Config->get('disable_drive_voting'))) {
                    // check if has a google drive link
                    $checkDrive = $dbh->prepare("SELECT COUNT(*) FROM `links` WHERE (`host` = 'gdrive' OR `host` = 'gdrive_folder') AND `release_id` = :rls_id");
                    $checkDrive->bindParam(':rls_id', $id, \PDO::PARAM_INT);
                    $checkDrive->execute();
                    // don't vote if has drive link
                    if (intval($checkDrive->fetchColumn(0)) > 0) {
                        return $response->withJson(['SUCCESS' => false, 'MSG' => _('Failed to vote, refresh and try again.')]);
                    }
                }

                // check ip + amount of times voted
                $checkVoteCount = $dbh->prepare("SELECT COUNT(*) FROM `votes` WHERE `uid` = INET6_ATON(:ip)");
                $checkVoteCount->bindParam(':ip', $ipAddress, \PDO::PARAM_STR);
                $checkVoteCount->execute();
                if ($checkVoteCount->fetchColumn() >= 3) {
                    return $response->withJson(['SUCCESS' => false, 'MSG' => _('Your vote limit has exceeded.')]);
                }

                // check if game is old enough
                $chechUploadAge = $dbh->prepare("SELECT `last_upload` FROM `releases`
                                                     WHERE `id` = :release_id
                                                     AND IF(DATE_ADD(FROM_UNIXTIME(`last_upload`), INTERVAL 30 DAY) >= NOW(), 0, 1)");
                $chechUploadAge->bindParam(':release_id', $id, \PDO::PARAM_INT);
                $chechUploadAge->execute();
                if ($chechUploadAge->rowCount() < 1) {
                    return $response->withJson(['SUCCESS' => false, 'MSG' => _('Game not old enough to vote on.')]);
                }

                // check game
                $checkGame = $dbh->prepare("SELECT `state` FROM `releases`
                                                WHERE `id` = :release_id
                                                AND IF(`state` = 'UPLOADING', 0, 1)");
                $checkGame->bindParam(':release_id', $id, \PDO::PARAM_INT);
                $checkGame->execute();
                if ($checkGame->fetchColumn() == 1) {
                    return $response->withJson(['SUCCESS' => false, 'MSG' => _('Game is already uploading.')]);
                }

                $vote = $dbh->prepare("INSERT INTO `votes` (`uid`, `release_id`) VALUES(INET6_ATON(:ip), :release_id)");
                $vote->bindParam(':ip', $ipAddress, \PDO::PARAM_STR);
                $vote->bindParam(':release_id', $id, \PDO::PARAM_INT);
                if ($vote->execute()) {
                    return $response->withJson(['SUCCESS' => true]);
                } else {
                    return $response->withJson(['SUCCESS' => false, 'MSG' => _('You already voted on this.')]);
                }
            }
            return $response->withJson(['SUCCESS' => false, 'MSG' => 'Invalid Release.']);
        });
    });
});