CREATE TABLE IF NOT EXISTS `{forum_category}` (
	`id` bigint(20) NOT NULL AUTO_INCREMENT PRIMARY KEY,
	`name` varchar(32) NOT NULL,
	`position` tinyint(1) NOT NULL,
	`status` tinyint(1) NOT NULL DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `{forum_topic}` (
	`id` bigint(20) NOT NULL AUTO_INCREMENT PRIMARY KEY,
	`user_id` bigint(20) NOT NULL,
	`category_id` bigint(20) NOT NULL,
	`title` varchar(128) NOT NULL,
	`status` tinyint(1) NOT NULL DEFAULT '1',
	`date_created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
	KEY `user_id` (`user_id`),
	KEY `category_id` (`category_id`),
	FULLTEXT search (`title`)
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `{forum_message}` (
	`id` bigint(20) NOT NULL AUTO_INCREMENT PRIMARY KEY,
	`user_id` bigint(20) NOT NULL,
	`topic_id` bigint(20) NOT NULL,
	`text` text NOT NULL,
	`status` tinyint(1) NOT NULL DEFAULT '1',
	`date_created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
	KEY `user_id` (`user_id`),
	KEY `topic_id` (`topic_id`),
	FULLTEXT search (`text`)
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;