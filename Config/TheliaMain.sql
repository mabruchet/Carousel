
# This is a fix for InnoDB in MySQL >= 4.1.x
# It "suspends judgement" for fkey relationships until are tables are set.
SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------
-- carousel
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `carousel`;

CREATE TABLE `carousel`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `file` VARCHAR(255),
    `mobile_file` VARCHAR(255),
    `position` INTEGER,
    `disable` INTEGER,
    `group` VARCHAR(255),
    `url` VARCHAR(255),
    `link_target` VARCHAR(8),
    `limited` INTEGER,
    `start_date` DATETIME,
    `end_date` DATETIME,
    `created_at` DATETIME,
    `updated_at` DATETIME,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- carousel_i18n
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `carousel_i18n`;

CREATE TABLE `carousel_i18n`
(
    `id` INTEGER NOT NULL,
    `locale` VARCHAR(5) DEFAULT 'en_US' NOT NULL,
    `alt` VARCHAR(255),
    `title` VARCHAR(255),
    `description` LONGTEXT,
    `chapo` TEXT,
    `postscriptum` TEXT,
    `button_label` VARCHAR(255),
    PRIMARY KEY (`id`,`locale`),
    CONSTRAINT `carousel_i18n_fk_2ec1b2`
        FOREIGN KEY (`id`)
        REFERENCES `carousel` (`id`)
        ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Back-office extension hooks (idempotent, also replayed by update/2.7.0.sql)
-- ---------------------------------------------------------------------

INSERT INTO `hook` (`code`, `type`, `by_module`, `native`, `activate`, `block`, `position`, `created_at`, `updated_at`)
SELECT * FROM (
    SELECT 'carousel.configuration.top' AS code, 2 AS type, 0 AS by_module, 0 AS native, 1 AS activate, 0 AS block, 1 AS position, NOW() AS created_at, NOW() AS updated_at
    UNION ALL SELECT 'carousel.configuration.bottom', 2, 0, 0, 1, 0, 1, NOW(), NOW()
    UNION ALL SELECT 'carousel.slide-list.row-actions', 2, 0, 0, 1, 0, 1, NOW(), NOW()
    UNION ALL SELECT 'carousel.slide-edit.extra-fields', 2, 0, 0, 1, 0, 1, NOW(), NOW()
    UNION ALL SELECT 'carousel.slide-edit.js', 2, 0, 0, 1, 0, 1, NOW(), NOW()
) AS new_hooks
WHERE NOT EXISTS (SELECT 1 FROM `hook` h WHERE h.`code` = new_hooks.code AND h.`type` = 2);

# This restores the fkey checks, after having unset them earlier
SET FOREIGN_KEY_CHECKS = 1;
