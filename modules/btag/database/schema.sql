CREATE TABLE IF NOT EXISTS `{tag}` (
	`id` bigint(20) NOT NULL AUTO_INCREMENT PRIMARY KEY,
	`name` varchar(255) NOT NULL,
	`taxonomy` varchar(16) NOT NULL DEFAULT 'tag',
	`parent_id` bigint(20) NOT NULL,
	`order` bigint(20) NOT NULL DEFAULT '0',
	`status` tinyint(1) NOT NULL DEFAULT '0',
	KEY `taxonomy` (`taxonomy`),
	FULLTEXT search (`name`)
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `{tag_rel}` (
	`id` bigint(20) NOT NULL AUTO_INCREMENT PRIMARY KEY,
	`tag_id` bigint(20) NOT NULL,
	`rel_id` bigint(20) NOT NULL,
	`rel_name` varchar(16) NOT NULL,
	KEY `tag_id` (`tag_id`),
	KEY `rel_id` (`rel_id`)
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;