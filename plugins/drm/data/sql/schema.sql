
# This is a fix for InnoDB in MySQL >= 4.1.x
# It "suspends judgement" for fkey relationships until are tables are set.
SET FOREIGN_KEY_CHECKS = 0;

#-----------------------------------------------------------------------------
#-- drm_profile
#-----------------------------------------------------------------------------

DROP TABLE IF EXISTS `drm_profile`;


CREATE TABLE `drm_profile`
(
	`id` INTEGER  NOT NULL AUTO_INCREMENT,
	`partner_id` INTEGER  NOT NULL,
	`name` TEXT  NOT NULL,
	`description` TEXT,
	`provider` INTEGER  NOT NULL,
	`status` INTEGER  NOT NULL,
	`license_server_url` TEXT,
	`default_policy` TEXT,
	`created_at` DATETIME,
	`updated_at` DATETIME,
	`custom_data` TEXT,
	PRIMARY KEY (`id`),
	KEY `partner_id_index`(`partner_id`)
)Type=InnoDB;

#-----------------------------------------------------------------------------
#-- drm_policy
#-----------------------------------------------------------------------------

DROP TABLE IF EXISTS `drm_policy`;


CREATE TABLE `drm_policy`
(
	`id` INTEGER  NOT NULL AUTO_INCREMENT,
	`partner_id` INTEGER  NOT NULL,
	`profile_id` INTEGER  NOT NULL,
	`name` TEXT  NOT NULL,
	`system_name` VARCHAR(128) default '' NOT NULL,
	`description` TEXT,
	`provider` INTEGER  NOT NULL,
	`status` INTEGER  NOT NULL,
	`scenario` INTEGER  NOT NULL,
	`license_type` INTEGER,
	`license_expiration_policy` INTEGER,
	`duration` INTEGER,
	`created_at` DATETIME,
	`updated_at` DATETIME,
	`custom_data` TEXT,
	PRIMARY KEY (`id`),
	KEY `partner_id_index`(`partner_id`),
	KEY `status_index`(`status`)
)Type=InnoDB;

#-----------------------------------------------------------------------------
#-- drm_device
#-----------------------------------------------------------------------------

DROP TABLE IF EXISTS `drm_device`;


CREATE TABLE `drm_device`
(
	`id` INTEGER  NOT NULL AUTO_INCREMENT,
	`partner_id` INTEGER  NOT NULL,
	`profile_id` INTEGER  NOT NULL,
	`userId` VARCHAR(128)  NOT NULL,
	`deviceId` VARCHAR(128)  NOT NULL,
	`version` VARCHAR(128),
	`platformDescriptor` TEXT,
	`provider` INTEGER  NOT NULL,
	`status` INTEGER  NOT NULL,
	`created_at` DATETIME,
	`updated_at` DATETIME,
	`custom_data` TEXT,
	PRIMARY KEY (`id`),
	KEY `partner_id_index`(`partner_id`),
	KEY `status_index`(`status`)
)Type=InnoDB;

# This restores the fkey checks, after having unset them earlier
SET FOREIGN_KEY_CHECKS = 1;
