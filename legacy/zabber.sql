# Structure of ZabberEx
# Author: Zheng Kai

# MySQL Server 5.0.41

# 2007-10-13 17:10:17

CREATE DATABASE `zabber`;

CREATE TABLE `comment` (
  `id` int(10) unsigned NOT NULL auto_increment,
  `jid` char(50) character set ascii NOT NULL,
  `content` char(255) character set utf8 NOT NULL,
  `status` enum('normal','hidden','delete') character set ascii NOT NULL default 'normal',
  `date_c` datetime NOT NULL,
  PRIMARY KEY  (`id`),
  KEY `jid` (`jid`,`status`,`id`),
  KEY `id` (`status`,`id`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
