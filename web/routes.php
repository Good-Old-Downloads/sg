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
require 'routes_api.php';

$app->get('/', function ($request, $response, $args) {
    $CONFIG = $this->get('site_config');
    $Memcached = $this->get('memcached');
    $dbh = $this->get('dbh');
    $latest15 = $Memcached->get('releases_latest');
    if ($latest15 === false) {
        $Images = new \GoodOldDownloads\Images();
        $getLatest = $dbh->prepare("
        SELECT games.`id`, games.`name`, games.`slug`, games.`cover_id`, games.`screen_id`, games.`image_cover`, games.`image_background`, games.`site`, games.`steam_id`, MAX(date) AS `date` FROM `releases`
        LEFT JOIN `games`
        ON `game_id` = games.`id`
        WHERE `hidden` != 1
        GROUP BY `game_id`
        ORDER BY `date` DESC
        LIMIT 21");
        $getLatest->execute();
        $last10Games = $getLatest->fetchAll(PDO::FETCH_ASSOC);
        
        $latest15 = array();
        foreach ($last10Games as $key => $game) {
            // Note to future self: nfo is stored on filesystem so only get it if the user specifically requested it
            $getReleases = $dbh->prepare("SELECT `id`, `name`, `type`, `group`, `magnet`, `torrent`, `date`, `last_upload`, `size`, `is_p2p`, IF(DATE_ADD(FROM_UNIXTIME(`last_upload`), INTERVAL 30 DAY) < NOW(), 1, 0) as `can_vote`
                                          FROM `releases`
                                          WHERE `hidden` = 0 AND `game_id` = :gameid
                                          ORDER BY `date` DESC");
            $getReleases->bindParam(':gameid', $game['id'], \PDO::PARAM_STR);
            $getReleases->execute();
            $releases = $getReleases->fetchAll(PDO::FETCH_ASSOC);

            $game['releases'] = $releases;
            $latest15[] = $game;
        }
        $Memcached->set('releases_latest', $latest15, 30);
    }

    $config = new \GoodOldDownloads\Config();

    $featured = $config->get('featured');
    if ($featured === false) {
        $featured = false;
    } else {
        $featured = unserialize($featured);
    }

    return $this->view->render($response, 'index.twig', array('latest' => $latest15, 'notice' => $config->get('notice'), 'featured' => $featured));
});

$app->get('/game/{game_slug}', function ($request, $response, $args) {
    $CONFIG = $this->get('site_config');
    $Memcached = $this->get('memcached');
    $dbh = $this->get('dbh');
    if (isset($args['game_slug'])) {
        $game = $dbh->prepare("
        SELECT `id`, `name`, `slug`, `cover_id`, `screen_id`, `image_cover`, `image_background`, `site`, `steam_id`, `description`
        FROM `games`
        WHERE `slug` = :game_slug");
        $game->bindParam(':game_slug', $args['game_slug'], \PDO::PARAM_STR);
        $game->execute();
        $game = $game->fetch(PDO::FETCH_ASSOC);

        $getReleases = $dbh->prepare("SELECT `id`, `name`, `type`, `group`, `magnet`, `torrent`, `date`, `size` FROM `releases` WHERE `hidden` = 0 AND `game_id` = :gameid ORDER BY `date` DESC");
        $getReleases->bindParam(':gameid', $game['id'], \PDO::PARAM_STR);
        $getReleases->execute();
        $releases = $getReleases->fetchAll(PDO::FETCH_ASSOC);

        if (count($releases) === 0){
            throw new \Slim\Exception\NotFoundException($request, $response);
        }

        // Get links
        foreach ($releases as $rlskey => $release) {
            $getLinks = $dbh->prepare("SELECT
                                            `link`,
                                            CASE WHEN (`file_name` IS NULL) OR (`file_name` = '') THEN `link` ELSE `file_name` END as `file_name`,
                                            `host`,
                                            hosters.`name` as `host_name`,
                                            `icon_html`,
                                            `status`
                                        FROM `links`
                                        LEFT JOIN `hosters`
                                        ON links.`host` = hosters.`id`
                                        WHERE `release_id` = :releaseid
                                        ORDER BY hosters.`order` ASC, `file_name` ASC");
            $getLinks->bindParam(':releaseid', $release['id'], \PDO::PARAM_INT);
            $getLinks->execute();
            $links = $getLinks->fetchAll(PDO::FETCH_ASSOC);
            $linklist = [];
            foreach ($links as $key => $link) {
                $newitem = [];
                $newitem['file_name'] = $link['file_name'];
                $newitem['link'] = $link['link'];
                if ($link['host'] == null) { // backwards compat
                    $releases[$rlskey]['old_view'] = true;
                    $linklist[] = $link;
                } else {
                    $linklist[$link['host']]['slug'] = $link['host'];
                    $linklist[$link['host']]['name'] = $link['host_name'];
                    $linklist[$link['host']]['icon'] = $link['icon_html'];
                    $linklist[$link['host']]['links'][] = $newitem;
                }
            }
            if ($releases[$rlskey]['old_view']) {
                usort($linklist, function($a, $b) {
                    return $a['file_name'] <=> $b['file_name'];
                });
            }
            $releases[$rlskey]['links'] = $linklist;
        }
        if ($game) {
            $Images = new \GoodOldDownloads\Images();
            return $this->view->render($response, 'game.twig',
                [
                    'game' => $game,
                    'cover' => $Images->getCoverImagePath($game['id']),
                    'bg720p' => $Images->getBackgroundImagePath($game['id'], '720p'),
                    'bg1080p' => $Images->getBackgroundImagePath($game['id'], '1080p'),
                    'releases' => $releases
                ]
            );
        } else {
            throw new \Slim\Exception\NotFoundException($request, $response);
        }
    } else {
        throw new \Slim\Exception\NotFoundException($request, $response);
    }
});

$search = function ($request, $response, $args) {
    $CONFIG = $this->get('site_config');
    $Memcached = $this->get('memcached');
    $dbh = $this->get('dbh');
    $searchType = $request->getAttribute('route')->getName();
    if ($searchType === "group") {
        $isGroup = true;
        // Check if group exists
        $getGroup = $dbh->prepare("SELECT `group` FROM `releases` WHERE `group` = :group LIMIT 1");
        $getGroup->bindParam(':group', trim($args['group']), \PDO::PARAM_STR);
        $getGroup->execute();
        if ($getGroup->rowCount() > 0) {
            $term = $getGroup->fetchColumn();
        } else {
            // Group does not exist
            return $response->withRedirect("/search/all", 302);
        }
    } elseif ($searchType === "search") {
        $isGroup = false;
    }

    $isTerm = isset($args['s']) && !empty(trim($args['s'])) && $args['s'] !== 'all';

    $Elasctic = new Elastic();

    //////////
    //  ORDERING
    //////////
    $allowedOrders = ['asc', 'desc'];
    $order = 'asc';
    if (!empty(trim($request->getParam('o')))) {
        if (in_array($request->getParam('o'), $allowedOrders)) {
            $order = $request->getParam('o');
        }
    }

    $allowedTypes = ['date', 'title'];
    if (!$isTerm) {
        $orderType = 'title';
    }
    if (!empty(trim($request->getParam('t')))) {
        if (in_array($request->getParam('t'), $allowedTypes)) {
            $orderType = $request->getParam('t');
        }
    }
    $sort = [];
    switch ($orderType) {
        case 'title':
            $sort[] = ['name.raw' => [ 'order' => $order ]];
            break;
        case 'date':
            $sort[] = ['date_added' => [ 'order' => $order ]];
            break;
        default:
            $sort = [];
            break;
    }

    //////////
    //  GENRE FILTERING
    //////////

    // Get list of genres to make dropdown and to validate
    $getGenres = $dbh->prepare("SELECT * FROM `genres` ORDER BY `name`");
    $getGenres->execute();
    $genreList = $getGenres->fetchAll(PDO::FETCH_ASSOC);

    $genres = []; // Default genres if empty or invalid genre
    if (!empty(trim($request->getParam('genres')))) {
        // Make flat genre list to be used to compare param
        $genreListFlat = [];
        foreach ($genreList as $key => $value) {
            $genreListFlat[] = $value['slug'];
        }
        $genresValid = true;
        $genresSplit = explode(',', $request->getParam('genres'));

        foreach ($genresSplit as $key => $value) {
            if (!in_array($value, $genreListFlat)) {
                $genresValid = false;
                break;
            }
        }
        if ($genresValid) {
            foreach ($genresSplit as $key => $value) {
                array_push($genres, ['term' => ['genres_slugs' => $value]]);
            }
        } else {
            $genresSplit = [];
        }
    }

    //////////
    //  PAGINATION
    //////////
    $limit = 27; // so it can be even

    $pageCurrent = intval($args['page']);

    // In case page is less than first page
    if ($pageCurrent < 1) {
        $offset = 0;
    } else {
        $offset = ($pageCurrent - 1) * $limit;
    }

    /*// In case page is higher than last
    if ($pageCurrent > ceil($countTotal/$limit)) {
        $pageCurrent = ceil($countTotal/$limit);
    }*/
    if ($isGroup) {
        $countParams = [
            'index' => 'games', 
            'type' => 'game',
            'body'  => [
                'query' => [
                    'bool' => [
                        'must' => [
                            'nested' => [
                                'path' => 'releases',
                                'query' => [
                                    'term' => [
                                        'releases.group' => [
                                            'value' => $term
                                        ]
                                    ]
                                ]
                            ],
                        ],
                        'filter' => [
                            'bool' => [
                                'must' => $genres
                            ]
                        ]
                    ]
                ]
            ]
        ];
        $countTotal = $Elasctic->count($countParams)['count'];
        if (isset($term) && !empty($term)) {
            $params = [
                'index' => 'games', 
                'type' => 'game',
                'from' => $offset,
                'size' => $limit,
                'filter_path' => ['hits.total', 'hits.hits._id', 'hits.hits._source', '-hits.hits._source.releases.nfo'],
                'body'  => [
                    'query' => [
                        'bool' => [
                            'must' => [
                                'nested' => [
                                    'path' => 'releases',
                                    'query' => [
                                        'term' => [
                                            'releases.group' => [
                                                'value' => $term
                                            ]
                                        ]
                                    ]
                                ],
                            ],
                            'filter' => [
                                'bool' => [
                                    'must' => $genres
                                ]
                            ]
                        ]
                    ],
                    'sort' => $sort
                ]
            ];
            
        }
    } else {
        if ($isTerm) {
            $term = $args['s'];
            $countParams = [
                'index' => 'games', 
                'type' => 'game',
                'body'  => [
                    'query' => [
                        'bool' => [
                            'must' => [
                                'match' => [
                                    'name' => [
                                        'query' => $term
                                    ],
                                ]
                            ],
                            'filter' => [
                                'bool' => [
                                    'must' => $genres
                                ]
                            ]
                        ]
                    ]
                ]
            ];
            $countTotal = $Elasctic->count($countParams)['count'];
            if (isset($args['s']) && !empty($args['s'])) {
                $params = [
                    'index' => 'games', 
                    'type' => 'game',
                    'from' => $offset,
                    'size' => $limit,
                    'filter_path' => ['hits.total', 'hits.hits._id', 'hits.hits._source', '-hits.hits._source.releases.nfo'],
                    'body'  => [
                        'query' => [
                            'bool' => [
                                'must' => [
                                    'match' => [
                                        'name' => [
                                            'query' => $term
                                        ],
                                    ]
                                ],
                                'filter' => [
                                    'bool' => [
                                        'must' => $genres
                                    ]
                                ]
                            ]
                        ],
                        'sort' => $sort
                    ]
                ];
                
            }
        } elseif ($args['s'] === 'all') {
            // Empty
            $countParams = [
                'index' => 'games', 
                'type' => 'game',
                'body'  => [
                    'query' => [
                        'constant_score' => [
                            'filter' => [
                                'bool' => [
                                    'must' => $genres
                                ]
                            ]
                        ]
                    ]
                ]
            ];
            $countTotal = $Elasctic->count($countParams)['count'];

            $params = [
                'index' => 'games', 
                'type' => 'game',
                'from' => $offset,
                'size' => $limit,
                'filter_path' => ['hits.total', 'hits.hits._id', 'hits.hits._source', '-hits.hits._source.releases.nfo'],
                'body'  => [
                    'query' => [
                        'constant_score' => [
                            'filter' => [
                                'bool' => [
                                    'must' => $genres
                                ]
                            ]
                        ]
                    ],
                    'sort' => $sort
                ]
            ];
        }
    }
    $responses = $Elasctic->search($params);

    $pagination = [
        'total' => $countTotal,
        'page' => $pageCurrent,
        'offset' => $offset,
        'limit' => $limit
    ];

    $templateVars = array(
        'results' => $responses['hits'],
        'genreList' => $genreList,
        'genres' => $genresSplit,
        'order' => $order,
        'orderType' => $orderType,
        'term' => $term,
        'pagination' => $pagination,
        'isGroup' => $isGroup,
        'group' => $term
    );

    if ($request->getParam('ajaxSearch') != null) {
        return $this->view->fetchBlock('search.twig', 'searchblock', $templateVars);
    } else {
        return $this->view->render($response, 'search.twig', $templateVars);
    }
};

$app->get('/search', function ($request, $response, $args) {
    /*
        If JavaScript is disabled then searches are done via /search/?s={queryStr}
        So just redirect those
    */
    if (!empty(trim($request->getParam('s')))) {
        return $response->withRedirect("/search/".$request->getParam('s'), 302);
    }
    return $response->withRedirect("/search/all", 302);
});
$app->get('/group/{group}[/{page}]', $search)->setName('group');
$app->get('/search/{s}[/{page}]', $search)->setName('search');

$release = function($request, $response, $args) {
    $dbh = $this->get('dbh');
    $release = trim($args['release']);
    $game = $dbh->prepare("
        SELECT `slug` FROM `releases`
        JOIN `games`
        ON releases.`game_id` = games.`id`
        WHERE releases.`name` = :rls_name
    ");
    $game->bindParam(':rls_name', $release, \PDO::PARAM_STR);
    $game->execute();

    if ($game->rowCount() > 0) {
        $gameSlug = $game->fetchColumn(0);
        return $response->withRedirect("/game/$gameSlug/#$release", 302);
    } else {
        throw new \Slim\Exception\NotFoundException($request, $response);
    }
};

$app->get('/release/{release}', $release);
$app->get('/rls/{release}', $release);
$app->get('/r/{release}', $release);

$app->get('/queue', function ($request, $response, $args) {
    $dbh = $this->get('dbh');

    $currentUpload = $dbh->prepare("
    SELECT `name`, `state`, COUNT(`release_id`) as `votes` FROM `votes`
    JOIN `releases` ON `release_id` = `id`
    WHERE `state` = 'UPLOADING'
    GROUP BY `release_id`
    ORDER BY `votes` DESC, `release_id` ASC");
    $currentUpload->execute();
    $uploadList = $currentUpload->fetchAll(PDO::FETCH_ASSOC);

    $votes = $dbh->prepare("
    SELECT `name`, `state`, COUNT(`release_id`) as `votes` FROM `votes`
    JOIN `releases` ON `release_id` = `id`
    WHERE `state` != 'UPLOADING'
    GROUP BY `release_id`
    ORDER BY `votes` DESC, `release_id` ASC");
    $votes->execute();
    $voteList = $votes->fetchAll(PDO::FETCH_ASSOC);
    return $this->view->render($response, 'queue.twig', ['uploading' => $uploadList, 'releases' => $voteList]);
});

$app->get('/getlinks', function ($request, $response, $args) {
    $dbh = $this->get('dbh');
    $Config = new \GoodOldDownloads\Config();
    $id = intval($request->getParam('id'));
    // Get release name
    $getRls = $dbh->prepare("SELECT `id`, `name`, IF(DATE_ADD(FROM_UNIXTIME(`last_upload`), INTERVAL 30 DAY) < NOW(), 1, 0) as `can_vote` FROM `releases` WHERE `id` = :releaseid");
    $getRls->bindParam(':releaseid', $id, \PDO::PARAM_INT);
    $getRls->execute();
    $release = $getRls->fetch(PDO::FETCH_ASSOC);

    // Get links
    $oldView = false;
    $getLinks = $dbh->prepare("SELECT
                                IF(`file_name` IS NULL, IF(`link_safe` IS NULL, `link`, `link_safe`), links.`file_name`) as `file_name`,
                                IF(`link_safe` IS NULL, `link`, `link_safe`) as `link`,
                                `host`,
                                hosters.`name` as `host_name`,
                                `icon_html`,
                                `status`
                            FROM `links`
                            LEFT JOIN `hosters`
                            ON links.`host` = hosters.`id`
                            WHERE `release_id` = :releaseid
                            ORDER BY hosters.`order` ASC, `file_name` ASC");
    $getLinks->bindParam(':releaseid', $id, \PDO::PARAM_INT);
    $getLinks->execute();
    $links = $getLinks->fetchAll(PDO::FETCH_ASSOC);

    $hasDrive = false;
    $linklist = [];
    foreach ($links as $key => $link) {
        $newitem = [];
        $newitem['file_name'] = $link['file_name'];
        $newitem['link'] = $link['link'];
        if ($link['host'] == null) { // backwards compat
            $oldView = true;
            $linklist[] = $link;
        } else {
            $linklist[$link['host']]['slug'] = $link['host'];
            $linklist[$link['host']]['name'] = $link['host_name'];
            $linklist[$link['host']]['icon'] = $link['icon_html'];
            $linklist[$link['host']]['links'][] = $newitem;
            if ($link['host'] === 'gdrive_folder' || $link['host'] === 'gdrive') {
                $hasDrive = true;
            }
        }
    }
    if ($oldView) {
        usort($linklist, function($a, $b) {
            return $a['file_name'] <=> $b['file_name'];
        });
    }

    if ($hasDrive && boolval($Config->get('disable_drive_voting'))) {
        $release['can_vote'] = false;
    }

    return $this->view->fetchBlock('release_links.twig', 'linksblock', [
        'release' => [
            'id' => $release['id'],
            'name' => $release['name'],
            'can_vote' => $release['can_vote'],
            'old_view' => $oldView,
            'links' => $linklist
        ]
    ]);
});

$app->group('/rss', function () {
    $this->get('/releases', function ($request, $response, $args) {
        $Memcached = $this->get('memcached');
        $dbh = $this->get('dbh');
        $latest = $Memcached->get('releases_latest_rss');
        if ($latest === false) {
            $getLatest = $dbh->prepare("
            SELECT releases.`name` as `rls_name`, games.`name` as `name`, releases.`date` as `date`, `description`, `slug`, `torrent`, `magnet` FROM `releases`
            LEFT JOIN `games` ON
            games.`id` = releases.`game_id`
            WHERE `hidden` = 0
            ORDER BY `date` DESC
            LIMIT 50");
            $getLatest->execute();
            $latest = $getLatest->fetchAll(PDO::FETCH_ASSOC);
            $Memcached->set('releases_latest_rss', $latest, 30);
        }
        return $this->view->render($response, 'rss/releases.twig', [
            'latest' => $latest
        ])->withHeader('Content-type', 'application/rss+xml');
    });
    $this->get('/torrents', function ($request, $response, $args) {
        $Memcached = $this->get('memcached');
        $dbh = $this->get('dbh');
        $latest = $Memcached->get('releases_latest_torrents_rss');
        if ($latest === false) {
            $getLatest = $dbh->prepare("
            SELECT releases.`name` as `rls_name`, games.`name` as `name`, releases.`date` as `date`, `description`, `slug`, `torrent`, `magnet` FROM `releases`
            LEFT JOIN `games` ON
            games.`id` = releases.`game_id`
            WHERE `hidden` = 0 AND (`torrent` is not NULL || `magnet` is not NULL)
            ORDER BY `date` DESC
            LIMIT 50");
            $getLatest->execute();
            $latest = $getLatest->fetchAll(PDO::FETCH_ASSOC);
            $Memcached->set('releases_latest_torrents_rss', $latest, 30);
        }
        return $this->view->render($response, 'rss/torrents.twig', [
            'latest' => $latest
        ])->withHeader('Content-type', 'application/rss+xml');
    });
});

$app->get('/nfo', function ($request, $response, $args) {
    $dbh = $this->get('dbh');

    $rlsName = trim($request->getParam('release'));
    $getNfo = $dbh->prepare("SELECT `nfo` FROM `releases` WHERE `name` = :rls_name");
    $getNfo->bindParam(':rls_name', $rlsName, \PDO::PARAM_STR);
    $exec = $getNfo->execute();
    $nfo = $getNfo->fetch(PDO::FETCH_ASSOC);

    if ($nfo['nfo'] != false) {
        return $response->withJson(['SUCCESS' => true, 'MSG' => $nfo['nfo']]);
    } else {
        return $response->withJson(['SUCCESS' => false, 'MSG' => "No NFO Found!"]);
    }
});

$app->map(['GET', 'POST'], '/login', function ($request, $response, $args) {
    $USER = new GoodOldDownloads\Users;

    if ($request->getParam('login') != null) {
        $login = $USER->login($_POST['user'], $_POST['pass']);
        if ($login !== true) {
            return $response->withJson(['SUCCESS' => false, 'MSG' => $login]);
        } else {
            return $response->withJson(['SUCCESS' => true]);
        }
    }

    if ($request->getParam('register') != null) {
        $register = $USER->register($_POST['user'], $_POST['pass'], $_POST['regcode']);
        if ($register !== true) {
            return $response->withJson(['SUCCESS' => false, 'MSG' => $register]);
        }
    }
    return $this->view->render($response, 'register.twig', ['message' => null]);
});

$app->get('/logout', function ($request, $response, $args) {
    $User = new GoodOldDownloads\Users;
    $User->logout();
    return $response->withRedirect('/'); 
});

$app->get('/google-drive-bypass-tutorial', function ($request, $response, $args) {
    return $this->view->render($response, 'tutorial.twig');
});

$app->get('/faq', function ($request, $response, $args) {
    return $this->view->render($response, 'faq.twig');
});

$app->group('/admin', function () {
    $this->get('', function ($request, $response, $args) {
        $dbh = $this->get('dbh');
        $Config = new \GoodOldDownloads\Config();
        $logs = $request->getAttribute('logs');

        // Convert ips
        foreach($logs as $key => $log) {
            $logs[$key]['ip'] = inet_ntop($log['ip']);
        }
        return $this->view->render($response, 'admin/index.twig', ['messages' => [], 'logs' => $logs, 'notice' => $Config->get('notice')]);
    });
    $this->post('', function ($request, $response, $args) {
        $Elastic = new Elastic();
        $Config = new \GoodOldDownloads\Config();
        $logs = $request->getAttribute('logs');
        $messages = [];
        if ($request->getParam('update_notice') != null) {
            if($Config->set('notice', $request->getParam('notice_text'))){
                $messages[] = 'Notice Updated.';
            } else {
                $messages[] = 'Failed to update notice.';
            }
        }
        if ($request->getParam('disable_drive_voting') != null) {
            $selected = intval($request->getParam('disable_drive_voting_value'));
            if($Config->set('disable_drive_voting', $selected)){
                $messages[] = 'Drive settings saved';
            }
        }
        return $this->view->render($response, 'admin/index.twig', ['messages' => $messages, 'logs' => $logs, 'notice' => $Config->get('notice')]);
    });
    $this->get('/cache', function ($request, $response, $args) {
        return $this->view->render($response, 'admin/cache.twig', ['messages' => []]);
    });
    $this->post('/cache', function ($request, $response, $args) {
        $Elastic = new Elastic();
        $Config = new \GoodOldDownloads\Config();
        $messages = [];
        if ($request->getParam('recreateSearch') != null) {
            try {
                $Elastic->Clear();
            } catch (Exception $e) {

            }
            if($Elastic->InsertAll()){
                $messages[] = 'Search data cleared and recreated';
            }
        }
        return $this->view->render($response, 'admin/cache.twig', ['messages' => $messages]);
    });
    $this->get('/games', function ($request, $response, $args) {
        $Config = new \GoodOldDownloads\Config();
        $featured = $Config->get('featured');
        if ($featured === false) {
            $featured = false;
        } else {
            $featured = unserialize($featured);
        }
        return $this->view->render($response, 'admin/games.twig', ['featured' => $featured]);
    });
    $this->post('/games/editFeatured', function ($request, $response, $args) {
        $dbh = $this->get('dbh');
        $Config = new \GoodOldDownloads\Config();
        if ($request->getParam('update') != null) {
            if ($request->getParam('update') == null) {
                $Config->set('featured', false);
            } else {
                $Images = new \GoodOldDownloads\Images();

                $idList = explode(',', $request->getParam('update'));
                $getGame = $dbh->prepare("SELECT `id`, `name`, `slug` FROM `games` WHERE `id` = :game_id");
                $getGame->bindParam(':game_id', $gameId, \PDO::PARAM_INT);
                $newVals = [];
                foreach ($idList as $key => $gameId) {
                    $getGame->execute();
                    $game = $getGame->fetch(\PDO::FETCH_ASSOC);
                    $game['cover'] = $Images->getCoverImagePath($gameId);
                    $newVals[] = $game;
                }
                $Config->set('featured', serialize($newVals));
            }
        } elseif ($request->getParam('add') != null) {
            $gameId = intval($request->getParam('id'));
            $Images = new \GoodOldDownloads\Images();

            $getGame = $dbh->prepare("SELECT `id`, `name` FROM `games` WHERE `id` = :game_id");
            $getGame->bindParam(':game_id', $gameId, \PDO::PARAM_INT);
            $getGame->execute();
            $game = $getGame->fetch(\PDO::FETCH_ASSOC);
            $game['cover'] = $Images->getCoverImagePath($game['id']);
            return $response->withJson($game);
        }
    });
})->add(function ($request, $response, $next) {
    $dbh = $this->get('dbh');
    $USER = new GoodOldDownloads\Users;
    if ($USER->get()['class'] !== 'ADMIN') {
        return $this->view->render($response, 'page_restricted.twig');
    } else {
        $getLogs = $dbh->prepare("
            SELECT l.*, u.username FROM `logs` as l
            LEFT JOIN users u
            ON u.id = l.user_id
            ORDER BY `date` DESC
            LIMIT 0,10
        ");
        $getLogs->execute();
        $logs = $getLogs->fetchAll(\PDO::FETCH_ASSOC);
        $request = $request->withAttribute('logs', $logs);
        return $next($request, $response);
    }
});

$app->group('/admin/ajax', function () {
    $this->get('/game', function ($request, $response, $args) {
        $dbh = $this->get('dbh');
        $SCENE = $request->getAttribute('SCENE');
        $gameId = intval($request->getParam('gameId'));
        $getGame = $dbh->prepare("SELECT * FROM `games` WHERE `id` = :gameid");
        $getGame->bindParam(':gameid', $gameId, \PDO::PARAM_INT);
        $getGame->execute();
        $game = $getGame->fetch(PDO::FETCH_ASSOC);

        // Get releases
        $getReleases = $dbh->prepare("SELECT `id`, `name`, `type`, `group`, `magnet`, `torrent`, `date`, `state`, `is_p2p` FROM `releases` WHERE `game_id` = :gameid ORDER BY `date` DESC");
        $getReleases->bindParam(':gameid', $game['id'], \PDO::PARAM_STR);
        $getReleases->execute();
        $releases = $getReleases->fetchAll(PDO::FETCH_ASSOC);

        // Get links
        foreach ($releases as $key => $release) {
            // Convert `is_p2p` to bool for mustache
            $releases[$key]['is_p2p'] = (bool)$releases[$key]['is_p2p'];

            $getLinks = $dbh->prepare("SELECT `id`, `link`, `file_name`, `status` FROM `links` WHERE `release_id` = :releaseid ORDER BY `id` ASC");
            $getLinks->bindParam(':releaseid', $release['id'], \PDO::PARAM_INT);
            $getLinks->execute();
            $links = $getLinks->fetchAll(PDO::FETCH_ASSOC);
            $releases[$key]['links'] = $links;
        }
        $game['releases'] = $releases;
        return $response->withJson(['SUCCESS' => true, 'MSG' => $game]);
    });
    $this->post('/game', function ($request, $response, $args) {
        $dbh = $this->get('dbh');
        $CONFIG = $this->get('site_config');
        $SCENE = $request->getAttribute('SCENE');
        $Elasctic = new Elastic();
        if ($request->getParam('edit_type') === 'release') {
            $filled = false;
            $required = [$request->getParam('name'), $request->getParam('date'), $request->getParam('release_id'), $request->getParam('state')];
            foreach ($required as $param) {
                if (isset($param) && !empty(trim($param))) {
                    $filled = true;
                }
            }

            if ($filled) {
                $id = intval($request->getParam('release_id'));
                $releaseName = trim($request->getParam('name'));

                $torrenURL = null;
                if ($request->getParam('torrent') != null && trim($request->getParam('torrent')) !== "") {
                    $torrenURL = $request->getParam('torrent');
                }

                $magnetURL = null;
                if ($request->getParam('magnet') != null && trim($request->getParam('magnet')) !== "") {
                    $magnetURL = $request->getParam('magnet');
                }
                if ($request->getParam('p2p') != null) {
                    $setP2P = $dbh->prepare("
                            UPDATE `releases`
                            SET `is_p2p` = 1
                            WHERE `id` = :id
                        ");
                    $setP2P->bindParam(':id', $id, \PDO::PARAM_INT);
                } else {
                    $setP2P = $dbh->prepare("
                            UPDATE `releases`
                            SET `is_p2p` = 0
                            WHERE `id` = :id
                        ");
                    $setP2P->bindParam(':id', $id, \PDO::PARAM_INT);
                }
                $setP2P->execute();

                // No isset() cause state is a required field
                $states = ["UPLOADING", "COMPLETE"];
                $state = $request->getParam('state');
                if (in_array($state, $states)) {
                    $setState = $dbh->prepare("
                            UPDATE `releases`
                            SET `state` = :state
                            WHERE `id` = :id
                        ");
                    $setState->bindParam(':state', $state, \PDO::PARAM_STR);
                    $setState->bindParam(':id', $id, \PDO::PARAM_INT);
                    $setState->execute();
                }

                try {
                    // Edit release name
                    $updateRelease = $SCENE->updateRelease($SCENE->parseRlsName($releaseName), $id);
                    if ($updateRelease === false) {
                        throw new Exception("Failed to edit release.");
                    }

                    // Edit Torrent/magnet
                    $updateTorrent = $SCENE->insertTorrent($torrenURL, $updateRelease['original']);
                    $updateMagnet = $SCENE->insertMagnet($magnetURL, $updateRelease['original']);
                } catch (Exception $e) {
                    return $response->withJson(['SUCCESS' => false, 'MSG' => $e->getMessage()]);
                }
                return $response->withJson(['SUCCESS' => true]);
            } else {
                return $response->withJson(['SUCCESS' => false, 'MSG' => "Fill in all required fields!"], 400);
            }
        } elseif ($request->getParam('edit_type') === 'game') {
            // Edit game here
            $filled = false;
            $required = [$request->getParam('name'), $request->getParam('game_id'), $request->getParam('slug')];
            foreach ($required as $param) {
                if (isset($param) && !empty(trim($param))) {
                    $filled = true;
                }
            }

            if ($filled) {
                $id = intval($request->getParam('game_id'));
                $slug = trim($request->getParam('slug'));

                $description = null;
                if ($request->getParam('description') != null && trim($request->getParam('description') !== "")) {
                    $description = $request->getParam('description');
                }

                $site = null;
                if (filter_var($request->getParam('site'), FILTER_VALIDATE_URL)){
                    $site = $request->getParam('site');
                }

                try {
                    $updateGame = $dbh->prepare("
                            UPDATE `games`
                            SET `name` = :name,
                                `description` = :description,
                                `site` = :site,
                                `steam_id` = :steam_id,
                                `slug` = :slug
                            WHERE `id` = :id
                        ");
                    $updateGame->bindParam(':id', $id, \PDO::PARAM_INT);
                    $updateGame->bindParam(':name', $_POST['name'], \PDO::PARAM_STR);
                    $updateGame->bindParam(':description', $description, \PDO::PARAM_STR);
                    $updateGame->bindParam(':site', $site, \PDO::PARAM_STR);
                    if (!empty($request->getParam('steam_id')) && is_numeric($request->getParam('steam_id'))) {
                        $updateGame->bindParam(':steam_id', $request->getParam('steam_id'), \PDO::PARAM_INT);
                    } else {
                        $updateGame->bindValue(':steam_id', null, PDO::PARAM_INT);
                    }
                    $updateGame->bindParam(':slug', $slug, \PDO::PARAM_STR);
                    if(!$updateGame->execute()){
                        throw new Exception("Error updating game.");
                    }
                } catch (Exception $e) {
                    return $response->withJson(['SUCCESS' => false, 'MSG' => $e->getMessage()]);
                }

                // update elasticsearch
                $Elasctic->UpdateGame($id);
                return $response->withJson(['SUCCESS' => true]);
            } else {
                return $response->withJson(['SUCCESS' => false, 'MSG' => "Fill in all required fields!"], 400);
            }
        } elseif ($request->getParam('edit_type') === 'images') {
            $gameId = intval($request->getParam('game_id'));
            if (!empty($request->getParam('cover_url'))) {
                try {
                    $imagePath = file_get_contents($request->getParam('cover_url'));
                    $image = \Eventviva\ImageResize::createFromString($imagePath);
                    $image->resizeToWidth(264);
                    $image->save($CONFIG['BASEDIR'].'/web/static/img/game_assets/custom/'.$gameId.'_cover.jpg', IMAGETYPE_JPEG);

                    $setCustom = $dbh->prepare("UPDATE `games` SET `image_cover` = 1 WHERE `id` = :id");
                    $setCustom->bindParam(':id', $gameId, \PDO::PARAM_INT);
                    $setCustom->execute();
                } catch (\Eventviva\ImageResizeException $e) {
                    return $response->withJson(['SUCCESS' => false, 'MSG' => $e->getMessage()]);
                }
            }
            if (!empty($request->getParam('screen_url'))) {
                try {
                    $imagePath = file_get_contents($request->getParam('screen_url'));
                    $image1080p = \Eventviva\ImageResize::createFromString($imagePath);
                    $image1080p->crop(1920, 1080, \Eventviva\ImageResize::CROPBOTTOM);
                    $image1080p->save($CONFIG['BASEDIR'].'/web/static/img/game_assets/custom/'.$gameId.'_bg_1080p.jpg', IMAGETYPE_JPEG);

                    $image720p = \Eventviva\ImageResize::createFromString($imagePath);
                    $image720p->crop(940, 529, \Eventviva\ImageResize::CROPBOTTOM);
                    $image720p->save($CONFIG['BASEDIR'].'/web/static/img/game_assets/custom/'.$gameId.'_bg_720p.jpg', IMAGETYPE_JPEG);

                    $setCustom = $dbh->prepare("UPDATE `games` SET `image_background` = 1 WHERE `id` = :id");
                    $setCustom->bindParam(':id', $gameId, \PDO::PARAM_INT);
                    $setCustom->execute();
                } catch (\Eventviva\ImageResizeException $e) {
                    return $response->withJson(['SUCCESS' => false, 'MSG' => $e->getMessage()]);
                }
            }
            return $response->withJson(['SUCCESS' => true]);
        }
    });
})->add(function ($request, $response, $next) {
    $USER = new GoodOldDownloads\Users;
    if ($USER->get()['class'] !== 'ADMIN') {
        return $response->write("fuck off lol");
    } else {
        return $next($request, $response);
    }
})->add($checkApiKey);