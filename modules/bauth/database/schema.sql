CREATE TABLE IF NOT EXISTS `{user}` (
	`id` bigint(20) NOT NULL AUTO_INCREMENT PRIMARY KEY,
	`username` varchar(16) NOT NULL,
	`email` varchar(128) NOT NULL,
	`password` varchar(128) NOT NULL,
	`activation_key` varchar(64) NOT NULL DEFAULT '',
	`status` tinyint(1) NOT NULL DEFAULT '0',
	`date_created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
	KEY `username` (`username`),
	KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;