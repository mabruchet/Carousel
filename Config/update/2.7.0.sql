-- 2.7.0: per-slide edition redesign — mobile image variant, link target and
-- localized button label. Scal-specific columns are untouched here (they are
-- migrated to the Scal module by Scal's own 2.1.8 update, executed afterwards).

ALTER TABLE `carousel`
    ADD COLUMN `mobile_file` VARCHAR(255) DEFAULT NULL AFTER `file`,
    ADD COLUMN `link_target` VARCHAR(8) DEFAULT NULL AFTER `url`;

ALTER TABLE `carousel_i18n`
    ADD COLUMN `button_label` VARCHAR(255) DEFAULT NULL AFTER `postscriptum`;

-- Back-office extension hooks of the redesigned configuration/edition pages.
INSERT INTO `hook` (`code`, `type`, `by_module`, `native`, `activate`, `block`, `position`, `created_at`, `updated_at`)
SELECT * FROM (
    SELECT 'carousel.configuration.top' AS code, 2 AS type, 0 AS by_module, 0 AS native, 1 AS activate, 0 AS block, 1 AS position, NOW() AS created_at, NOW() AS updated_at
    UNION ALL SELECT 'carousel.configuration.bottom', 2, 0, 0, 1, 0, 1, NOW(), NOW()
    UNION ALL SELECT 'carousel.slide-list.row-actions', 2, 0, 0, 1, 0, 1, NOW(), NOW()
    UNION ALL SELECT 'carousel.slide-edit.extra-fields', 2, 0, 0, 1, 0, 1, NOW(), NOW()
    UNION ALL SELECT 'carousel.slide-edit.js', 2, 0, 0, 1, 0, 1, NOW(), NOW()
) AS new_hooks
WHERE NOT EXISTS (SELECT 1 FROM `hook` h WHERE h.`code` = new_hooks.code AND h.`type` = 2);
