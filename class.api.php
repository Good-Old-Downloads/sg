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
namespace GoodOldDownloads;

require __DIR__ . '/db.php';

use GoodOldDownloads\IGDB as IGDB;

class SceneAPI
{
    protected $apikey;

    protected $apiUserId = null;

    /**
     * Internal API constructor.
     *
     * @param $key
     */
    public function __construct($key = null)
    {
        require __DIR__ . '/config.php';
        global $dbh;
        $this->CONFIG = $CONFIG;
        $this->IGDB = new IGDB($CONFIG['IGDBKey'], $CONFIG['IGDBUrl']);

        $this->Elastic = new \Elastic();

        $checkKey = $dbh->prepare("SELECT `id`, `username` FROM `users` WHERE `apikey` = :apikey");
        $checkKey->bindParam(':apikey', $key, \PDO::PARAM_STR);
        $checkKey->execute();

        // Check the key
        if ($checkKey->rowCount() < 1) {
            $this->log("Use API", "Someone tried to use this API key: $key", 'FAIL');
            throw new \Exception("Invalid API Key");
        }

        $this->apiUserId = $checkKey->fetch(\PDO::FETCH_ASSOC)['id'];
        $this->apikey = $key;
    }

    /**
     * Inserts a torrent from a given url
     *
     * @param string $torrentLink
     * @param string $releaseName
     * @return bool
     */
    public function insertTorrent($torrentLink, $releaseName)
    {
        global $dbh;
        if (empty(trim($releaseName))) {
            return false;
        }
        if ($torrentLink === null) {
            $addTorrent = $dbh->prepare("UPDATE `releases` SET `torrent` = null WHERE `name` = :release_name");
            $addTorrent->bindParam(':release_name', $releaseName, \PDO::PARAM_STR);
            if ($addTorrent->execute() && $addTorrent->rowCount() > 0) {
                $this->log("Remove Torrent", $releaseName, 'UPDATE');
                return true;
            } else {
                return false;
            }
        }
        if (filter_var($torrentLink, FILTER_VALIDATE_URL) !== false){
            $addTorrent = $dbh->prepare("UPDATE `releases` SET `torrent` = :torrent_url WHERE `name` = :release_name");
            $addTorrent->bindParam(':torrent_url', $torrentLink, \PDO::PARAM_STR);
            $addTorrent->bindParam(':release_name', $releaseName, \PDO::PARAM_STR);
            if ($addTorrent->execute() && $addTorrent->rowCount() > 0) {
                $this->log("Add Torrent", "Added torrent for \"$releaseName\"", 'SUCCESS');
                return true;
            } else {
                return false;
            }
        }
    }

    /**
     * Inserts a magnet
     *
     * @param string $infoHash sha1 hash or pretty much any string with a sha1 string in it
     * @param string $releaseName
     * @return bool
     */
    public function insertMagnet($infoHash, $releaseName)
    {
        global $dbh;
        if (empty(trim($releaseName))) {
            return false;
        }
        if (empty(trim($infoHash))) {
            // Remove magnet
            $removeMagnet = $dbh->prepare("UPDATE `releases` SET `magnet` = null WHERE `name` = :release_name");
            $removeMagnet->bindParam(':release_name', $releaseName, \PDO::PARAM_STR);
            if ($removeMagnet->execute()) {
                if($removeMagnet->rowCount() > 0){
                    $this->log("Removed Magnet", $releaseName, 'UPDATE');
                }
                return true;
            } else {
                return false;
            }
            
        }
        $infoHash = trim($infoHash);
        $releaseName = trim($releaseName);

        preg_match('/[a-fA-F0-9]{40}/', $infoHash, $matches); // Get sha1 hash
        if (empty($matches)) {
            return false;
        }
        $infoHash = $matches[0];

        // Encode parts that need to be
        $trackers = array();
        foreach ($this->CONFIG['TRACKERS'] as $key => $tracker) {
            $trackers[] = urlencode(trim($tracker));
        }
        $trackers = join($trackers, '&tr=');
        $torrentName = urlencode($releaseName);

        $finalMagnet = "magnet:?xt=urn:btih:$infoHash&dn=$torrentName&tr=$trackers";

        $addMagnet = $dbh->prepare("UPDATE `releases` SET `magnet` = :magnet_url WHERE `name` = :release_name");
        $addMagnet->bindParam(':magnet_url', $finalMagnet, \PDO::PARAM_STR);
        $addMagnet->bindParam(':release_name', $releaseName, \PDO::PARAM_STR);
        if ($addMagnet->execute() && $addMagnet->rowCount() > 0) {
            $this->log("Add Magnet", "$releaseName", 'SUCCESS');
            return true;
        } else {
            return false;
        }

    }

    /**
     * Inserts a link
     *
     * @param string $release either the release id (int) or the release name
     * @param string $link
     * @return bool
     */
    public function insertLink($release, $link = null, $status)
    {
        global $dbh;
        if (is_int($release)) {
            $releaseId = $release;
            $getGameId = $dbh->prepare("SELECT `game_id` FROM `releases` WHERE `id` = :rlsid");
            $getGameId->bindParam(':rlsid', $releaseId, \PDO::PARAM_INT);
            $getGameId->execute();
            $gameId = $getGameId->fetchColumn();
        } else {
            $releaseName = trim($release);
            $getRlsId = $dbh->prepare("SELECT `id`, `game_id` FROM `releases` WHERE `name` = :release_name");
            $getRlsId->bindParam(':release_name', $releaseName, \PDO::PARAM_STR);
            $getRlsId->execute();
            $rlsData = $getRlsId->fetch();
            $releaseId = $rlsData['id'];
            $gameId = $rlsData['game_id'];
        }

        $addLink = $dbh->prepare("INSERT INTO `links` (`release_id`, `link`, `status`) VALUES (:release_id, :link, :status)");
        $addLink->bindParam(':release_id', $releaseId, \PDO::PARAM_INT);
        $addLink->bindParam(':status', $status, \PDO::PARAM_STR);
        $addLink->bindParam(':link', $link, \PDO::PARAM_STR);

        if ($addLink->execute()) {
            $linkId = $dbh->lastInsertId();
            $this->Elastic->UpdateGame($gameId);
            return $linkId;
        } else {
            return false;
        }
    }

    /**
     * Inserts a link
     *
     * @param string $release either the release id (int) or the release name
     * @param string $link
     * @return bool
     */
    public function insertLinkNew($release, $link, $linksafe, $filename, $host)
    {
        global $dbh;
        $releaseName = trim($release);
        $getRlsId = $dbh->prepare("SELECT `id`, `game_id` FROM `releases` WHERE `name` = :release_name");
        $getRlsId->bindParam(':release_name', $releaseName, \PDO::PARAM_STR);
        $getRlsId->execute();

        $rlsData = $getRlsId->fetch();
        $releaseId = $rlsData['id'];
        $gameId = $rlsData['game_id'];

        $addLink = $dbh->prepare("INSERT INTO `links` (`release_id`, `link`, `link_safe`, `file_name`, `status`, `host`) VALUES (:release_id, :link, :safelink, :filename, 'DONE', :host)");
        $addLink->bindParam(':release_id', $releaseId, \PDO::PARAM_INT);
        $addLink->bindParam(':link', $link, \PDO::PARAM_STR);
        if ($linksafe !== null) {
            $addLink->bindParam(':safelink', $linksafe, \PDO::PARAM_STR);
        } else {
            $addLink->bindValue(':safelink', null, \PDO::PARAM_INT);
        }
        if ($filename !== null) {
            $addLink->bindParam(':filename', $filename, \PDO::PARAM_STR);
        } else {
            $addLink->bindValue(':filename', null, \PDO::PARAM_INT);
        }
        $addLink->bindParam(':host', $host, \PDO::PARAM_STR);

        if ($addLink->execute()) {
            $linkId = $dbh->lastInsertId();
            $this->Elastic->UpdateGame($gameId);
            return $linkId;
        } else {
            return false;
        }
    }

    /**
     * Update a link
     *
     * @param integer $release
     * @param string $link
     * @return bool
     */
    public function updateLink($linkId, $link, $filename, $status)
    {
        global $dbh;
        if (!is_int($linkId)) {
            return false;
        }

        $updateLink = $dbh->prepare("UPDATE `links`
            SET `link` = :link,
                `status` = :status,
                `file_name` = :filename
            WHERE `id` = :linkid");
        $updateLink->bindParam(':linkid', $linkId, \PDO::PARAM_INT);
        $updateLink->bindParam(':status', $status, \PDO::PARAM_STR);
        $updateLink->bindParam(':filename', $filename, \PDO::PARAM_STR);
        $updateLink->bindParam(':link', $link, \PDO::PARAM_STR);

        if ($updateLink->execute()) {
            $getGameId = $dbh->prepare("SELECT `game_id` FROM `links`
                                        LEFT JOIN `releases`
                                        ON releases.`id` = links.`release_id`
                                        WHERE links.`id` = :rlsid");
            $getGameId->bindParam(':rlsid', $linkId, \PDO::PARAM_INT);
            $getGameId->execute();
            $gameId = $getGameId->fetchColumn();
            $this->Elastic->UpdateGame($gameId);
            return true;
        } else {
            return false;
        }

    }

    public function removeLink($linkIds)
    {
        global $dbh;

        // Get game id for reindexing elasticsearch
        if (is_array($linkIds)) {
            $linkId = intval($linkIds[0]);
        } elseif (is_numeric($linkIds)) {
            $linkId = intval($linkIds);
        } else {
            return false;
        }
        $getGameId = $dbh->prepare("SELECT `game_id` FROM `links`
                                    LEFT JOIN `releases`
                                    ON releases.`id` = links.`release_id`
                                    WHERE links.`id` = :linkid");
        $getGameId->bindParam(':linkid', $linkId, \PDO::PARAM_INT);
        $getGameId->execute();
        $gameId = $getGameId->fetchColumn();

        // Do the removing
        $removeLink = $dbh->prepare("DELETE FROM `links` WHERE `id` = :linkid");
        $removeLink->bindParam(':linkid', $linkId, \PDO::PARAM_INT);
        if (is_array($linkIds)) {
            foreach ($linkIds as $key => $linkId) {
                $linkId = intval($linkId);
                $removeLink->execute();
            }
        } elseif (is_numeric($linkIds)) {
            $linkId = intval($linkIds);
            $removeLink->execute();
        }

        $this->Elastic->UpdateGame($gameId);
        return true;

    }

    /**
     * Searches for game and adds it
     *
     * @param string $gamename
     * @param bool $id
     * @return \StdClass
     * @throws \Exception
     */
    public function insertGame($gameName, $id = false)
    {
        global $dbh;
        if (empty(trim($gameName))) {
            return false;
        }

        if ($id) {
            $firstGame = $this->IGDB->getGame($gameName);
        } else {
            $gameName = str_replace("Complete Edition", "", $gameName); // fuck IGDB
            $games = $this->IGDB->searchGames($gameName, ['*'], $limit = 1, $offset = 0);
            $firstGame = $games[0];
        }

        $name = $firstGame->name;
        $igdbId = $firstGame->id;
        $slug = $firstGame->slug;
        $description = $firstGame->summary;
        $rlsDate = $firstGame->first_release_date;
        $rlsDate = $rlsDate/1000; // Convert to seconds

        $popularity = $firstGame->popularity;

        $coverId = $firstGame->cover->cloudinary_id;
        $screenId = $firstGame->screenshots[0]->cloudinary_id;

        $websites = $firstGame->websites;

        $genres = $firstGame->genres;

        $site = null;
        $steam = null;
        if ($websites) {
            foreach ($websites as $key => $value) {
                switch ($value->category) {
                    case 1:
                        $site = $value->url;
                        break;
                    case 13:
                        $steam = $value->url;
                        break;
                }
            }
        }
        $steamId = $firstGame->external->steam;

        // Insert into GAMES
        $stmt = $dbh->prepare("
            INSERT IGNORE INTO `games` ( `igdb_id`, `name`, `popularity`, `description`, `slug`, `date_released`, `date_added`, `cover_id`, `screen_id`, `steam_id`, `site`)
            VALUES (:igdbid, :name, :popularity, :description, :slug, :date_released, UNIX_TIMESTAMP(), :cover_id, :screen_id, :steam_id, :site)");
        $stmt->bindParam(':igdbid', $igdbId, \PDO::PARAM_INT);
        $stmt->bindParam(':name', $name, \PDO::PARAM_STR);
        $stmt->bindParam(':popularity', $popularity, \PDO::PARAM_STR);
        $stmt->bindParam(':description', $description, \PDO::PARAM_STR);
        $stmt->bindParam(':slug', $slug, \PDO::PARAM_STR);
        $stmt->bindParam(':date_released', $rlsDate, \PDO::PARAM_INT);
        $stmt->bindParam(':cover_id', $coverId, \PDO::PARAM_STR);
        $stmt->bindParam(':screen_id', $screenId, \PDO::PARAM_STR);
        $stmt->bindParam(':steam_id', $steamId, \PDO::PARAM_INT);
        $stmt->bindParam(':site', $site, \PDO::PARAM_STR);
        if ($stmt->execute()) {
            // Can't use lastInsertId() so just do a select `id`
            $getId = $dbh->prepare("SELECT `id` FROM `games` WHERE `igdb_id` = :igdbid");
            $getId->bindParam(':igdbid', $igdbId, \PDO::PARAM_INT);
            $getId->execute();

            $gameId = $getId->fetchColumn();

            if (isset($genres)) {
                // Fill genres
                $addGenres = $dbh->prepare("INSERT IGNORE INTO `game_genres` (`genre_id`, `game_id`)
                    VALUES (:genreid, :gameid)");
                $addGenres->bindParam(':gameid', $gameId, \PDO::PARAM_INT);
                foreach ($genres as $key => $value) {
                    $addGenres->bindParam(':genreid', $value, \PDO::PARAM_INT);
                    $addGenres->execute();
                }
            }

            // If no cover
            if ($coverId == null) {
                // Then get cover from steam instead
                if ($steamId !== null) {
                    file_put_contents($this->CONFIG['BASEDIR']."web/static/img/game_assets/steam_covers/$steamId.jpg", fopen("http://cdn.edgecast.steamstatic.com/steam/apps/$steamId/capsule_616x353.jpg", 'r'));
                }
            } else {
                file_put_contents($this->CONFIG['BASEDIR']."web/static/img/game_assets/cover_big/$coverId.jpg", fopen("https://images.igdb.com/igdb/image/upload/t_cover_big/$coverId.jpg", 'r'));
            }

            if ($screenId) {
                // Save a 1080p image for large screens
                file_put_contents($this->CONFIG['BASEDIR']."web/static/img/game_assets/bg_1080p/$screenId.jpg", fopen("https://images.igdb.com/igdb/image/upload/t_1080p/$screenId.jpg", 'r'));
                // Save a smaller one for mobile
                file_put_contents($this->CONFIG['BASEDIR']."web/static/img/game_assets/bg_720p/$screenId.jpg", fopen("https://images.igdb.com/igdb/image/upload/t_screenshot_big/$screenId.jpg", 'r'));
            }

            // Check if "added" game already exists
            $firstGame->scene_added_game_id = $gameId;
            $this->log("Add Game", "IGDB: \"$name\". | Source: \"$gameName\"", 'SUCCESS');
            $this->Elastic->UpdateGame($gameId);
            return $firstGame;
        } else {
            $this->log("Add Game", "Error INSERTing \"$gameName\"", 'FAIL');
            return false;
        }
    }

    public function slugify($str)
    {
        $generator = new \Ausi\SlugGenerator\SlugGenerator;
        $slug = $generator->generate($str);
        return $slug;
    }

    public function getGame($gameName, $steamID = false)
    {
        if ($steamID) {
            $firstAppDetails = $this->parseAppDetails($gameName);
            if ($firstAppDetails) {
                return $firstAppDetails;
            }
            return false;
        } else {
            $client = new \GuzzleHttp\Client();
            $res = $client->request('GET', "https://store.steampowered.com/search/suggest?f=games&l=english&term=".urlencode($gameName)."&".time());
            $resultsBody = $res->getBody()->getContents();
            preg_match_all('/data-ds-appid="(\d+)"/m', $resultsBody, $matchedAppids);
            if ($matchedAppids[0]) {
                $appids = array_map('intval', $matchedAppids[1]); // in case we need to loop it
                $firstAppDetails = $this->parseAppDetails($appids[0], 'app');
            } else {  // if first app isn't an app
                preg_match_all('/m_nPackageID&quot;:(\d+),/m', $resultsBody, $matchedPackages); // Find packageids if game bundles
                $packages = array_map('intval', $matchedPackages[1]);
                $firstAppDetails = $this->parseAppDetails($packages[0], 'package');
            }
            if ($firstAppDetails->type !== 'game') {
                return $this->parseAppDetails($firstAppDetails->fullgame->appid, 'app');
            }
            return $firstAppDetails;
        }
    }

    public function parseAppDetails($appid, $type = 'app')
    {
        $client = new \GuzzleHttp\Client();
        if ($appid) {
            if ($type === 'app') {
                $res = $client->request('GET', "https://store.steampowered.com/api/appdetails/?appids=$appid");
                $gameObj = json_decode($res->getBody()->getContents(), false);
                $appid = key($gameObj);
                if ($gameObj->$appid->success) {
                    return $gameObj->$appid->data;
                }
            } elseif ($type === 'package') {
                $res = $client->request('GET', "https://store.steampowered.com/api/packagedetails?packageids=$appid");
                $gameObj = json_decode($res->getBody()->getContents(), false);
                $packageId = key($gameObj);
                if ($gameObj->$packageId->success) {
                    return $this->parseAppDetails($gameObj->$packageId->data->apps[0]->id, 'app');
                }
            }
        }
        return false;
    }

    public function insertGame_force_steam($gameName, $id = false)
    {
        global $dbh;
        if (empty(trim($gameName))) {
            return false;
        }

        if ($id) {
            $firstGame = $this->getGame($gameName, true);
        } else {
            $firstGame = $this->getGame($gameName);
        }
        if ($firstGame === false) {
            return false;
        }
        $name = $firstGame->name;

        try {
            $rlsDate = (new \DateTime($firstGame->release_date->date))->format('U');
        } catch (\Exception $err){
            $rlsDate = null;
        }

        $slug = $this->slugify($name);

        // Check if slug already exists
        $checkSlug = $dbh->prepare("SELECT `slug` FROM `games` WHERE `slug` = :slug");
        $checkSlug->bindParam(':slug', $slug, \PDO::PARAM_STR);
        $checkSlug->execute();
        $slugOld = $checkSlug->fetchColumn();
        // If found a dupe slug
        if ($slugOld) {
            // If Steam returns a valid date then append year
            if ($rlsDate) {
                $slug = $slug."-".date('Y', $rlsDate);
            } else {
                // If no valid date append a number
                if (!preg_match('/-[1-9]$/', $slugOld, $matches)) {
                    $slug = $slug."-".$matches[1]+1;
                } else {
                    $slug = $slug."-1";
                }
            }
        }

        $description = htmlspecialchars_decode(strip_tags($firstGame->short_description));
        $site = $firstGame->website;
        $genres = null;
        if (isset($firstGame->genres)) {
            $genres = $firstGame->genres;
        }
        $steamId = $firstGame->steam_appid;

        // Save random screenshot if game has some
        $randomScreenKey = array_rand($firstGame->screenshots);
        $screenshot = $firstGame->screenshots[$randomScreenKey];
        $screenshotThumb = $screenshot->path_thumbnail;
        $screenshotFull = $screenshot->path_full;

        if ($screenshot) {
            $screenId = md5($screenshotFull); // need some sort of unique id to work with the old igdb shit
        }

        // Insert into GAMES
        $stmt = $dbh->prepare("
            INSERT IGNORE INTO `games` (`name`, `description`, `slug`, `date_released`, `steam_id`, `site`, `screen_id`)
            VALUES (:name, :description, :slug, :date_released, :steam_id, :site, :screen_id)");
        $stmt->bindParam(':name', $name, \PDO::PARAM_STR);
        if ($screenshot) {
            $stmt->bindParam(':screen_id', $screenId, \PDO::PARAM_STR);
        } else {
            $stmt->bindValue(':screen_id', null, \PDO::PARAM_NULL);
        }
        $stmt->bindParam(':description', $description, \PDO::PARAM_STR);
        $stmt->bindParam(':slug', $slug, \PDO::PARAM_STR);
        if ($rlsDate === null) {
            $stmt->bindValue(':date_released', null, \PDO::PARAM_NULL);
        } else {
            $stmt->bindParam(':date_released', $rlsDate, \PDO::PARAM_INT);
        }
        $stmt->bindParam(':steam_id', $steamId, \PDO::PARAM_INT);
        $stmt->bindParam(':site', $site, \PDO::PARAM_STR);
        if ($stmt->execute()) {
            // Can't use lastInsertId() so just do a select `id`
            $getId = $dbh->prepare("SELECT `id` FROM `games` WHERE `steam_id` = :steam_id");
            $getId->bindParam(':steam_id', $steamId, \PDO::PARAM_INT);
            $getId->execute();

            $gameId = $getId->fetchColumn();

            file_put_contents($this->CONFIG['BASEDIR']."web/static/img/game_assets/steam_covers/$steamId.jpg", fopen("http://cdn.edgecast.steamstatic.com/steam/apps/$steamId/capsule_616x353.jpg", 'r'));

            // hack together working screenshots for now
            if ($screenshot) {
                file_put_contents($this->CONFIG['BASEDIR']."web/static/img/game_assets/bg_1080p/$screenId.jpg", fopen($screenshotFull, 'r'));
                file_put_contents($this->CONFIG['BASEDIR']."web/static/img/game_assets/bg_720p/$screenId.jpg", fopen($screenshotThumb, 'r'));
            }

            $firstGame->scene_added_game_id = $gameId; // need this for when game gets added when a release is added
            $this->log("Add Game", "Steam: \"$name\". | Source: \"$gameName\"", 'SUCCESS');
            $this->Elastic->UpdateGame($gameId);
            return $firstGame;
        } else {
            $this->log("Add Game", "Error INSERTing \"$gameName\"", 'FAIL');
            return false;
        }
    }


    /**
     * Searches for game and adds it
     *
     * @param array $releaseArray array generated from parseRlsName()
     * @param int game id to add game to
     */
    public function insertRelease($releaseArray, $gameId = null)
    {
        global $dbh;
        if ($gameId === null) {
            return false;
        }
        $addRelease = $dbh->prepare("
            INSERT INTO `releases` (`game_id`, `name`, `group`, `date`, `last_upload`, `is_rip`, `is_addon`, `version`, `type`)
                            VALUES (:id, :name, :group, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), :isrip, :isaddon, :version, :type)
            ");
        $addRelease->bindParam(':id', $gameId, \PDO::PARAM_INT);
        $addRelease->bindParam(':name', $releaseArray['original'], \PDO::PARAM_STR);
        $addRelease->bindParam(':group', $releaseArray['group'], \PDO::PARAM_STR);
        $addRelease->bindParam(':version', $releaseArray['version'], \PDO::PARAM_STR);
        $addRelease->bindParam(':isrip', $isrip, \PDO::PARAM_INT);
        $addRelease->bindParam(':isaddon', $isaddon, \PDO::PARAM_INT);
        $addRelease->bindParam(':type', $type, \PDO::PARAM_STR);
        if ($releaseArray['rip']) {
            $isrip = 1;
        } else {
            $isrip = 0;
        }
        if ($releaseArray['addon']) {
            $isaddon = 1;
        } else {
            $isaddon = 0;
        }
        if ($releaseArray['update_version'] !== null) {
            $type = "UPDATE";
        } else {
            $type = "BASE";
        }
        if ($addRelease->execute()) {
            $this->Elastic->UpdateGame($gameId);
            $this->log("Add Release", $releaseArray['original'], 'SUCCESS');
            return $releaseArray;
        } else {
            $this->log("Add Release", 'Error INSERTing "'.$releaseArray['original'].'"', 'FAIL');
            return false;
        }
    }

    /**
     * Updates release data
     *
     * @param array $releaseArray array generated from parseRlsName()
     * @param int release id to update
     */
    public function updateRelease($releaseArray, $releaseId = null)
    {
        global $dbh;
        if ($releaseId === null) {
            return false;
        }

        // Fetch data before changing to compare
        $checkRelease = $dbh->prepare("
            SELECT `name`, `group`, `version`, `version`
            FROM `releases` WHERE `id` = :id");
        $checkRelease->bindParam(':id', $releaseId, \PDO::PARAM_INT);
        $checkRelease->execute();
        $releaseOld = $checkRelease->fetch(\PDO::FETCH_ASSOC);

        $updateRelease = $dbh->prepare("
                UPDATE `releases`
                SET `name` = :name,
                    `group` = :group,
                    `version` = :version,
                    `is_rip` = :isrip,
                    `is_addon` = :isaddon,
                    `version` = :version,
                    `type` = :type
                WHERE `id` = :id
            ");
        $updateRelease->bindParam(':id', $releaseId, \PDO::PARAM_INT);
        $updateRelease->bindParam(':name', $releaseArray['original'], \PDO::PARAM_STR);
        $updateRelease->bindParam(':group', $releaseArray['group'], \PDO::PARAM_STR);
        $updateRelease->bindParam(':version', $releaseArray['version'], \PDO::PARAM_STR);
        $updateRelease->bindParam(':isrip', $isrip, \PDO::PARAM_INT);
        $updateRelease->bindParam(':isaddon', $isaddon, \PDO::PARAM_INT);
        $updateRelease->bindParam(':type', $type, \PDO::PARAM_STR);
        if ($releaseArray['rip']) {
            $isrip = 1;
        } else {
            $isrip = 0;
        }
        if ($releaseArray['addon']) {
            $isaddon = 1;
        } else {
            $isaddon = 0;
        }
        if ($releaseArray['update_version'] !== null) {
            $type = "UPDATE";
        } else {
            $type = "BASE";
        }

        if ($updateRelease->execute()) {
            // Get game and update elasticsearch
            $getGame = $dbh->prepare("SELECT `game_id` FROM `releases` WHERE `id` = :releaseId");
            $getGame->bindParam(':releaseId', $releaseId, \PDO::PARAM_INT);
            $getGame->execute();
            $gameId = $getGame->fetchColumn();
            $this->Elastic->UpdateGame($gameId);
            if ($releaseOld['name'] !== $releaseArray['original']) {
                $this->log("Edit Release", "FROM: ".$releaseOld['name']." | TO: ".$releaseArray['original'], 'UPDATE');
            }
            return $releaseArray;
        } else {
            $this->log("Edit Release", $releaseArray['original'], 'FAIL');
            return false;
        }
    }

    /**
     * Parses scene release name and returns an array of info 
     *
     * @param string $gamename
     * @return \Array
     */
    public function parseRlsName($releaseName)
    {
        if (empty(trim($releaseName))) {
            return false;
        }
        $name = trim($releaseName);
        $nameClean = $name; // used when replacing chars in name

        $languages = []; // :|

        $data = [
            'addon' => '/(?:\.|_)RIP(?:\.|_).+ADDON/i', // "ADDON" is always with RIP. Come before RIP so the name gets replaced properly
            'rip' => '/(?:\.|_)RIP(?:\.|_)?/i',
            'multilang' => '/MULTi[0-9]{1,2}/i',
            'lang' => '/(GERMAN|FRENCH|ITALIAN|RUSSIAN|POLISH|SPANISH)/',
            'group' => '/[A-Za-z0-9_]+$/',
            'update_version' => '/Update(?:\.|_)v?[0-9.]+/i',
            'build' => '/(?:Build(?:\.|_)([0-9a-z.]+))/i', // Sometimes used instead of "v123". Not always paired with an Update.
            'version' => '/v(?:\d+\.)(?:\d+\.?)?(?:\d+)|hotfix/i',
            'dirfix' => '/DIRFIX/i',
            'internal' => '/(?:\.|_)iNTERNAL/i',
            'arch' => '/x(?:64|86)/i',
            'repack' => '/REPACK/'
            ];
        $boolData = ['addon', 'rip', 'internal', 'dirfix', 'repack'];

        foreach ($data as $key => $value) {
            // Modify original $data array with replaced data or null if nothing
            if (preg_match($value, $name, $m)) {
                if (in_array($key, $boolData)) {
                    // change some values to true that should be bools instead of matched string
                    $data[$key] = true;
                } else {
                    $data[$key] = $m[0];
                }
                $nameClean = preg_replace($value, '', $nameClean);
            } else {
                if (in_array($key, $boolData)) {
                    // change some values to false that should be bools instead of null
                    $data[$key] = false;
                } else {
                    $data[$key] = null;
                }
            }
        }

        // fuck darksiders
        if ($data['group'] === 'DARKSiDERS' && preg_match('/\.GAME-$/', $nameClean)) {
            $nameClean = str_replace('.GAME-', '', $nameClean);
        }

        $nameClean = str_replace('-', '', $nameClean); // remove -
        $nameClean = str_replace('.', ' ', str_replace('_', ' ', $nameClean)); // replace . with " "

        $data['name'] = trim($nameClean);
        $data['original'] = $name;
        return $data;
    }

    /**
     * Gets list of genres from IGDB and adds saves it to the database
     *
     * @return \StdClass
     * @throws \Exception
     */
    public function saveGenres()
    {
        global $dbh;
        $genres = $this->IGDB->getGenres(array('id', 'name', 'slug'));

        $stmt = $dbh->prepare("INSERT IGNORE INTO `genres` (`id`, `name`, `slug`) VALUES (:id, :name, :slug)");
        foreach ($genres as $key => $genre) {
            $stmt->bindParam(':id', $genre->id, \PDO::PARAM_INT);
            $stmt->bindParam(':name', $genre->name, \PDO::PARAM_STR);
            $stmt->bindParam(':slug', $genre->slug, \PDO::PARAM_STR);
            $stmt->execute();
        }
        $this->log("Add Genres", 'Genre list updated.', 'SUCCESS');
        return $genres;
    }

    private function log($action, $msg, $result)
    {
        global $dbh;

        $ip = null; // Don't really care, just in case

        $userid = $this->apiUserId;
        if ($this->apiUserId === null) {
            $userid = null;
        }

        $stmt = $dbh->prepare("
            INSERT INTO `logs` (`user_id`, `action`, `message`, `key`, `ip`, `result`, `date`)
                       VALUES (:uid, :action, :msg, :key, :ip, :result, UNIX_TIMESTAMP(NOW()))
        ");

        $stmt->bindParam(':uid', $userid, \PDO::PARAM_INT);
        $stmt->bindParam(':action', $action, \PDO::PARAM_STR);
        $stmt->bindParam(':msg', $msg, \PDO::PARAM_STR);
        $stmt->bindParam(':key', $this->apikey, \PDO::PARAM_STR);
        $stmt->bindParam(':ip', $ip, \PDO::PARAM_STR);
        $stmt->bindParam(':result', $result, \PDO::PARAM_STR);
        return $stmt->execute();
    }
}