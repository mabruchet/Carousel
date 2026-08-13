ALTER TABLE `carousel` ADD `mobile_file` VARCHAR(255) NULL AFTER `file`, ADD `link_target` VARCHAR(8) NULL AFTER `url`;
ALTER TABLE `carousel_i18n` ADD `button_label` VARCHAR(255) NULL;
UPDATE `carousel` SET `disable` = 0 WHERE `disable` IS NULL;
UPDATE `carousel` SET `limited` = 0 WHERE `limited` IS NULL;
CREATE INDEX `idx_carousel_group_position` ON `carousel` (`group`, `position`);
CREATE INDEX `idx_carousel_disable` ON `carousel` (`disable`);
