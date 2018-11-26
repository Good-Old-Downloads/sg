<?php
$CONFIG = array(
    "IGDBKey" => "thisisafakekey",
    "IGDBUrl" => "https://api-blehbleh.io",
    "BASEDIR" => "/var/scenegames.goodolddownloads.com/",
    "DEV" => true,

    "DB" => [
        "DBNAME" => "scene",
        "DBUSER" => "root",
        "DBPASS" => ""
    ],

    "MEMCACHED" => [
        "SERVER" => "127.0.0.1",
        "PORT" => 11211
    ],

    // List of trackers to use when making magnets
    "TRACKERS" => array(
        'udp://tracker.opentrackr.org:1337/announce',
        'udp://tracker.zer0day.to:1337/announce',
        'udp://tracker.leechers-paradise.org:6969/announce',
        'udp://coppersurfer.tk:6969/announce'
    )
);