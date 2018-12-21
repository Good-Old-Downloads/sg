# SceneGames Installation

These instructions assume you have experience with installing, configuring, and securing web site software within Linux. Instructions have been tested with Ubuntu 16.04.4 LTS and Debian Stretch.

That said, it is possible to run SceneGames on a Windows server.
### Prerequisites

- PHP >= 7.2

  `apt-get install php`
- MariaDB

  `apt-get install mariadb-server`
- Java or OpenJDK (whichever version that whatever version of ElasticSearch you installed wants)

  `apt-get install openjdk-8-jdk`
- ElasticSearch >= 6.2

  https://www.elastic.co/downloads/elasticsearch
- memcached

  `apt-get install memcached`
- Composer

  https://getcomposer.org/download/

### Installing
##### Getting the sauce:

```bash
git clone https://github.com/Good-Old-Downloads/sg.git
```

##### Installing the PHP requirements:
`cd` into the directory where the code now lies then install the site dependencies via Composer:
```bash
cd sg
php composer.phar install
```
##### Configuring the site:
Make a copy of config_blank.php named config.php and edit it.
```bash
cp config_blank.php config.php
vi config.php
```
config.php explantion:
```php
<?php
$CONFIG = [
    // IGDB Key, used as fallback for when Steam fails.
    "IGDBKey" => "thisisafakekey",
    // API URL suppplied by IGDB
    "IGDBUrl" => "https://api-blehbleh.io",
    // Root directory of the website code, ending in a /
    "BASEDIR" => "/var/www/sg/",
    // Shows PHP errors, disables the Twig cache.
    // Set to false in production.
    "DEV" => true,

    // MySQL deets
    "DB" => [
        "DBNAME" => "sg",
        "DBUSER" => "root",
        "DBPASS" => ""
    ],

    // Memcached deets
    "MEMCACHED" => [
        "SERVER" => "127.0.0.1",
        "PORT" => 11211
    ],

    // List of trackers to use when making magnets
    "TRACKERS" => [
        'udp://tracker.opentrackr.org:1337/announce',
        'udp://tracker.zer0day.to:1337/announce',
        'udp://tracker.leechers-paradise.org:6969/announce',
        'udp://coppersurfer.tk:6969/announce'
    ]
];
```

##### Importing the empty database:
Login to MySQL, create a database, then import db.sql.
```
MariaDB [(none)]> CREATE DATABASE `sg`;
MariaDB [sg]> USE `sg`;
MariaDB [sg]> SOURCE db.sql;
```

##### Configuring the Nginx:
The Nginx config is pretty standard. I'll only list the relevant config values.
```nginx
server {
        listen 443 ssl http2;
        root /var/www/sg/web; # <-- must point to /web directory
        index index.php
        autoindex on;
        location = /index.php {
                try_files $uri =404;
                fastcgi_pass unix:/var/run/php/php7.2-fpm.sock;
                fastcgi_index index.php;
                fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
                include fastcgi_params;
        }
        location / {
            try_files $uri /index.php$is_args$args;
        }
        location ~ \.php$ {
                # prevent exposure of any other .php files!!!
                return 404;
        }
        location ~ /\.ht {
                deny all;
        }
}
```

##### Starting up memcached and ElasticSearch:
If you made it this far, you should know how to start these up already, and how to secure them both.  
In short, install memcached/elasticsearch then edit the config.php with the connection details.

##### Post-install stuff
For Linux, generate languages so gettext will work:
`sudo locale-gen ar_SA de_DE es_ES el_GR en_CA et_EE ru_RU` (Check \locale for which ones to install)

Generate Search:
Go to Admin > Cache > Reindex ElasticSearch Data

### Running tests
lol

### Coding style
hahaha
