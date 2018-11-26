CREATE TABLE IF NOT EXISTS `games` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `igdb_id` int(11) DEFAULT NULL,
  `name` varchar(500) CHARACTER SET utf8 NOT NULL,
  `description` text COLLATE utf8_bin DEFAULT NULL,
  `popularity` double DEFAULT NULL,
  `slug` varchar(255) COLLATE utf8_bin NOT NULL,
  `date_released` int(11) DEFAULT NULL,
  `date_added` int(11) NOT NULL DEFAULT 0,
  `cover_id` varchar(50) COLLATE utf8_bin DEFAULT NULL,
  `screen_id` varchar(50) COLLATE utf8_bin DEFAULT NULL,
  `image_cover` tinyint(1) NOT NULL DEFAULT 0,
  `image_background` tinyint(1) NOT NULL DEFAULT 0,
  `site` varchar(2083) COLLATE utf8_bin DEFAULT NULL,
  `steam_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unq_slug` (`slug`),
  KEY `igdb_id` (`igdb_id`),
  KEY `popularity` (`popularity`),
  KEY `date_added` (`date_added`),
  KEY `slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;
INSERT INTO `games` (`id`, `igdb_id`, `name`, `description`, `popularity`, `slug`, `date_released`, `date_added`, `cover_id`, `screen_id`, `image_cover`, `image_background`, `site`, `steam_id`) VALUES
    (0, NULL, 'Unmatched', NULL, NULL, 'game-not-matched-fam', NULL, 0, NULL, NULL, 0, 0, 'https://scenegames.goodolddownloads.com/', NULL);

CREATE TABLE IF NOT EXISTS `game_genres` (
  `genre_id` int(11) NOT NULL,
  `game_id` int(11) NOT NULL,
  PRIMARY KEY (`genre_id`,`game_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

CREATE TABLE IF NOT EXISTS `genres` (
  `id` int(11) NOT NULL,
  `name` varchar(50) COLLATE utf8_bin NOT NULL,
  `slug` varchar(50) COLLATE utf8_bin NOT NULL,
  PRIMARY KEY (`id`),
  KEY `slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

CREATE TABLE IF NOT EXISTS `hosters` (
  `id` varchar(50) NOT NULL,
  `name` varchar(50) NOT NULL,
  `order` int(2) unsigned NOT NULL,
  `icon_html` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `order` (`order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
INSERT INTO `hosters` (`id`, `name`, `order`, `icon_html`) VALUES
    ('1fichier', '1fichier', 13, '<img src="/static/img/hoster_logos/1fichier.svg" class="fac-hoster">'),
    ('filecloud', 'filecloud.io', 7, '<i class="fas fa-fw fa-cloud"></i>'),
    ('filescdn', 'Filescdn', 8, '<img src="/static/img/hoster_logos/filescdn.svg" class="fac-hoster">'),
    ('gdrive', 'Google Drive', 2, '<i class="fab fa-fw fa-google-drive"></i>'),
    ('gdrive_folder', 'Google Drive (Folder)', 1, '<i class="fac-hoster fa-drive-folder"><i class="fas fa-folder"></i><i class="fab fa-google-drive fa-inverse"></i></i>'),
    ('gdrive_single', 'Google Drive (ISO Link)', 3, '<i class="fab fa-fw fa-google-drive"></i>'),
    ('letsupload', 'LetsUpload', 3, '<img src="/static/img/hoster_logos/letsupload.svg" class="fac-hoster">'),
    ('megaup', 'MegaUp', 3, '<img src="/static/img/hoster_logos/megaup.svg" class="fac-hoster">'),
    ('openload', 'Openload', 6, '<img src="/static/img/hoster_logos/openload.svg" class="fac-hoster">'),
    ('shareonline_biz', 'Share-Online', 12, '<img src="/static/img/hoster_logos/shareonline.svg" class="fac-hoster">'),
    ('uploaded', 'Uploaded.net', 11, '<img src="/static/img/hoster_logos/uploaded.svg" class="fac-hoster">'),
    ('uploadhaven', 'UploadHaven', 4, '<img src="/static/img/hoster_logos/uploadhaven.svg" class="fac-hoster">'),
    ('uploadhaven_single', 'UploadHaven (ISO Link)', 5, '<img src="/static/img/hoster_logos/uploadhaven.svg" class="fac-hoster">'),
    ('uptobox', 'UptoBox', 10, '<img src="/static/img/hoster_logos/uptobox.svg" class="fac-hoster">'),
    ('userscloud', 'Userscloud', 4, '<i class="fa-stack fa-fw"><i class="fas fa-square fa-stack-2x"></i><i class="fas fa-star fa-stack-1x fa-inverse"></i></i>'),
    ('zippyshare', 'Zippyshare', 9, '<img src="/static/img/hoster_logos/zippyshare.svg" class="fac-hoster">');

CREATE TABLE IF NOT EXISTS `links` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `release_id` int(11) NOT NULL,
  `file_name` varchar(2000) CHARACTER SET utf8 DEFAULT NULL,
  `link` varchar(2083) COLLATE utf8_bin DEFAULT NULL,
  `link_safe` varchar(2083) COLLATE utf8_bin DEFAULT NULL,
  `status` enum('UPLOADING','DONE') COLLATE utf8_bin DEFAULT NULL,
  `host` varchar(50) COLLATE utf8_bin DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `release_id` (`release_id`),
  KEY `status` (`status`),
  KEY `host` (`host`),
  KEY `link_safe` (`link_safe`(1024)),
  KEY `link` (`link`(1024)),
  KEY `file_name` (`file_name`(1024))
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

CREATE TABLE IF NOT EXISTS `logs` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned DEFAULT NULL,
  `action` varchar(50) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
  `message` text CHARACTER SET utf8 COLLATE utf8_unicode_ci DEFAULT NULL,
  `key` varchar(32) CHARACTER SET utf8 COLLATE utf8_unicode_ci DEFAULT NULL,
  `ip` varbinary(16) DEFAULT NULL,
  `result` enum('FAIL','SUCCESS','UPDATE') CHARACTER SET utf8 COLLATE utf8_unicode_ci DEFAULT NULL,
  `date` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

CREATE TABLE IF NOT EXISTS `opencritic` (
  `id` int(10) unsigned NOT NULL,
  `game_id` int(10) unsigned DEFAULT NULL,
  `link` text DEFAULT NULL,
  `score` int(10) unsigned DEFAULT NULL,
  `average_reviewers` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `game_id_unq` (`game_id`),
  KEY `score` (`score`),
  KEY `game_id` (`game_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `regcodes` (
  `code` varchar(32) COLLATE utf8_bin NOT NULL,
  PRIMARY KEY (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

CREATE TABLE IF NOT EXISTS `releases` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `game_id` int(11) NOT NULL DEFAULT 0,
  `name` varchar(255) CHARACTER SET utf8 NOT NULL,
  `group` varchar(50) CHARACTER SET utf8 NOT NULL,
  `date` int(11) NOT NULL DEFAULT 0,
  `last_upload` int(11) unsigned NOT NULL,
  `state` enum('UPLOADING','COMPLETE') COLLATE utf8_bin NOT NULL DEFAULT 'COMPLETE',
  `type` enum('BASE','UPDATE','REPACK','FIX','RIP') COLLATE utf8_bin NOT NULL DEFAULT 'BASE',
  `lang` varchar(10) COLLATE utf8_bin DEFAULT NULL,
  `version` varchar(20) COLLATE utf8_bin DEFAULT NULL,
  `is_rip` tinyint(1) NOT NULL DEFAULT 0,
  `is_p2p` tinyint(1) NOT NULL DEFAULT 0,
  `is_addon` tinyint(1) NOT NULL DEFAULT 0,
  `nfo` mediumtext COLLATE utf8_bin DEFAULT NULL,
  `platform` enum('WIN','OSX','LINUX') COLLATE utf8_bin NOT NULL DEFAULT 'WIN',
  `nuked` tinyint(1) NOT NULL DEFAULT 0,
  `torrent` varchar(255) COLLATE utf8_bin DEFAULT NULL,
  `magnet` varchar(2083) COLLATE utf8_bin DEFAULT NULL,
  `hidden` tinyint(1) NOT NULL DEFAULT 0,
  `size` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unq_rls_name` (`name`),
  KEY `release_date` (`date`),
  KEY `game_id` (`game_id`),
  KEY `rls_name` (`name`),
  KEY `group` (`group`),
  KEY `nuked` (`nuked`),
  KEY `lang` (`lang`),
  KEY `rip` (`is_rip`),
  KEY `addon` (`is_addon`),
  KEY `hidden` (`hidden`),
  KEY `is_p2p` (`is_p2p`),
  KEY `last_upload` (`last_upload`),
  KEY `state` (`state`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

CREATE TABLE IF NOT EXISTS `site` (
  `name` varchar(100) COLLATE utf8_unicode_ci NOT NULL,
  `value` text COLLATE utf8_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

CREATE TABLE IF NOT EXISTS `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(70) COLLATE utf8_bin NOT NULL,
  `password` varchar(255) COLLATE utf8_bin NOT NULL DEFAULT '0',
  `class` enum('ADMIN','VIP','DISABLED') COLLATE utf8_bin DEFAULT NULL,
  `apikey` varchar(32) COLLATE utf8_bin DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  KEY `name` (`username`),
  KEY `apikey` (`apikey`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

CREATE TABLE IF NOT EXISTS `users_ips` (
  `ip` varbinary(16) NOT NULL,
  `user_id` int(11) NOT NULL,
  PRIMARY KEY (`ip`),
  UNIQUE KEY `unq_ip` (`ip`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci COMMENT='List of ips by user';

CREATE TABLE IF NOT EXISTS `votes` (
  `uid` varbinary(16) NOT NULL,
  `release_id` int(11) unsigned NOT NULL,
  PRIMARY KEY (`uid`,`release_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC;