/*
SQLyog Community v12.4.3 (64 bit)
MySQL - 5.7.19-0ubuntu0.16.04.1 : Database - 209104781
*********************************************************************
*/

/*!40101 SET NAMES utf8 */;

CREATE TABLE `poll_votes` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `poll_id` int(10) unsigned DEFAULT NULL,
  `user_id` bigint(20) DEFAULT NULL,
  `username` varchar(255) DEFAULT NULL,
  `user_text` varchar(255) DEFAULT NULL,
  `vote` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `i_poll_user` (`poll_id`,`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

/*Table structure for table `polls` */

CREATE TABLE `polls` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `chat_id` bigint(20) DEFAULT NULL,
  `poll_text` varchar(255) DEFAULT NULL,
  `poll_votes` text,
  `poll_type` enum('vote','doodle') DEFAULT 'vote',
  `anony` enum('y','n') DEFAULT 'n',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

/*Table structure for table `users` */

CREATE TABLE `users` (
  `chat_id` bigint(20) NOT NULL,
  `username` varchar(100) DEFAULT NULL,
  `first_name` varchar(100) DEFAULT NULL,
  `last_name` varchar(100) DEFAULT NULL,
  `pointer` int(10) DEFAULT NULL,
  `type` enum('vote','doodle','v','d') DEFAULT 'vote',
  `anony` enum('y','n') DEFAULT 'n',
  PRIMARY KEY (`chat_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

