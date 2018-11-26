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
try {
    $dbh = new PDO(
        "mysql:host=localhost;dbname=".$CONFIG["DB"]["DBNAME"].";charset=utf8",
        $CONFIG["DB"]["DBUSER"],
        $CONFIG["DB"]["DBPASS"]);
} catch (PDOException $e) {
    echo "Shit's fuxked yo";
    die();
}