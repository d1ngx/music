CREATE TABLE IF NOT EXISTS `plugin_music_track` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `userID` bigint(20) unsigned NOT NULL,
  `sourceID` bigint(20) unsigned NOT NULL,
  `fileID` bigint(20) unsigned NOT NULL,
  `name` varchar(250) NOT NULL,
  `ext` varchar(16) NOT NULL,
  `size` bigint(20) unsigned NOT NULL DEFAULT 0,
  `title` varchar(250) NOT NULL DEFAULT '',
  `artist` varchar(250) NOT NULL DEFAULT '',
  `album` varchar(250) NOT NULL DEFAULT '',
  `genre` varchar(120) NOT NULL DEFAULT '',
  `year` varchar(16) NOT NULL DEFAULT '',
  `trackNo` int(11) unsigned NOT NULL DEFAULT 0,
  `duration` int(11) unsigned NOT NULL DEFAULT 0,
  `hasCover` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `lyricOffset` int(11) NOT NULL DEFAULT 0,
  `favorite` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `sourceModifyTime` int(11) unsigned NOT NULL DEFAULT 0,
  `createTime` int(11) unsigned NOT NULL,
  `modifyTime` int(11) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_source` (`userID`,`sourceID`),
  KEY `user_artist` (`userID`,`artist`(64)),
  KEY `user_album` (`userID`,`album`(64)),
  KEY `user_title` (`userID`,`title`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `plugin_music_list` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `userID` bigint(20) unsigned NOT NULL,
  `name` varchar(80) NOT NULL,
  `createTime` int(11) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `user_list` (`userID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `plugin_music_list_item` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `listID` bigint(20) unsigned NOT NULL,
  `userID` bigint(20) unsigned NOT NULL,
  `sourceID` bigint(20) unsigned NOT NULL,
  `sort` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `list_source` (`listID`,`sourceID`),
  KEY `user_list_item` (`userID`,`listID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
