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

class Config
{
    public function __construct()
    {
        require __DIR__ . '/config.php';
        $this->CONFIG = $CONFIG;
    }

    public function get($settingName)
    {
        global $Memcached;
        $settingVal = $Memcached->get("setting_$settingName");
        if ($settingVal === false) {
            global $dbh;
            $get = $dbh->prepare("SELECT `value` FROM `site` WHERE `name` = :name");
            $get->bindParam(':name', $settingName, \PDO::PARAM_STR);
            $get->execute();
            $settingVal = $get->fetchColumn();
            $Memcached->add("setting_$settingName", $settingVal, 0);
        }
        return $settingVal;
    }

    public function set($settingName, $value)
    {
        global $Memcached;
        global $dbh;
        $set = $dbh->prepare("REPLACE INTO `site` (`name`, `value`) VALUES (:name, :value)");
        $set->bindParam(':name', $settingName, \PDO::PARAM_STR);
        $set->bindParam(':value', $value, \PDO::PARAM_STR);
        $Memcached->set("setting_$settingName", $value, 0);
        return $set->execute();
    }
}

class Images
{
    public function __construct()
    {
        require __DIR__ . '/config.php';
        $this->CONFIG = $CONFIG;
    }
    public function getCoverImagePath($gameId)
    {
        global $dbh;
        $get = $dbh->prepare("SELECT `id`, `image_cover`, `cover_id`, `steam_id` FROM `games` WHERE `id` = :id");
        $get->bindParam(':id', $gameId, \PDO::PARAM_INT);
        $get->execute();
        $game = $get->fetch(\PDO::FETCH_ASSOC);
        if ($game['image_cover'] == 1){
            $time = '';
            $file = $this->CONFIG['BASEDIR']."/web/static/img/game_assets/custom/".$game['id']."_cover.jpg";
            if (file_exists($file)) {
                $time = "?".filemtime($file);
            }
            return "/static/img/game_assets/custom/".$game['id']."_cover.jpg$time";
        } elseif ($game['cover_id'] !== null){
            return "/static/img/game_assets/cover_big/".$game['cover_id'].".jpg";
        } elseif ($game['cover_id'] == null && $game['steam_id'] !== null){
            return "/static/img/game_assets/steam_covers/".$game['steam_id'].".jpg";
        } else {
            return "/static/img/nocover.png";
        }
    }
    public function getBackgroundImagePath($gameId, $size)
    {
        global $dbh;
        $get = $dbh->prepare("SELECT `id`, `image_background`, `screen_id`, `steam_id` FROM `games` WHERE `id` = :id");
        $get->bindParam(':id', $gameId, \PDO::PARAM_INT);
        $get->execute();
        $game = $get->fetch(\PDO::FETCH_ASSOC);

        switch ($size) {
            case '720p':
                if ($game['image_background'] == 1){
                    $time = '';
                    $file = $this->CONFIG['BASEDIR']."/static/img/game_assets/custom/".$game['id']."_bg_720p.jpg";
                    if (file_exists($file)) {
                        $time = "?".filemtime($file);
                    }
                    return "/static/img/game_assets/custom/".$game['id']."_bg_720p.jpg$time";
                } elseif ($game['screen_id'] !== null){
                    return "/static/img/game_assets/bg_720p/".$game['screen_id'].".jpg";
                } else {
                    return "/static/img/nobg.png";
                }
                break;
            case '1080p':
                if ($game['image_background'] == 1){
                    $time = '';
                    $file = $this->CONFIG['BASEDIR']."/static/img/game_assets/custom/".$game['id']."_bg_1080p.jpg";
                    if (file_exists($file)) {
                        $time = "?".filemtime($file);
                    }
                    return "/static/img/game_assets/custom/".$game['id']."_bg_1080p.jpg$time";
                } elseif ($game['screen_id'] !== null){
                    return "/static/img/game_assets/bg_1080p/".$game['screen_id'].".jpg";
                } else {
                    return "/static/img/nobg.png";
                }
                break;
        }

    }
}