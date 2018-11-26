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


require '../vendor/autoload.php';
require '../config.php';
require $CONFIG['BASEDIR'].'/vendor/autoload.php';
require $CONFIG['BASEDIR'].'/memcached.php'; // Start Memcached before everything
require $CONFIG['BASEDIR'].'/db.php';
require $CONFIG['BASEDIR'].'/class.elasticsearch.php';
require $CONFIG['BASEDIR'].'/class.igdb.extended.php';
require $CONFIG['BASEDIR'].'/class.scene.php';
require $CONFIG['BASEDIR'].'/class.api.php';
require $CONFIG['BASEDIR'].'/class.users.php';
require $CONFIG['BASEDIR'].'/twig.ext.php';

session_start();

$configuration = [
    'settings' => [
        'displayErrorDetails' => $CONFIG["DEV"],
    ],
];
$container = new \Slim\Container($configuration);
$app = new \Slim\App($container);

require 'middleware.php';

require 'dependencies.php';

require 'routes.php';

// Run, Forrest, Run!
$app->run();