<?php

declare(strict_types=1);

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Upgrade to 1.7.0 - Add generation_job table for async API processing.
 *
 * @param Itrblueboost $module
 *
 * @return bool
 */
function upgrade_module_1_7_0($module): bool
{
    $db = Db::getInstance();

    $tableExists = $db->executeS(
        'SHOW TABLES LIKE \'' . _DB_PREFIX_ . 'itrblueboost_generation_job\''
    );

    if (!empty($tableExists)) {
        return true;
    }

    $result = $db->execute(
        'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'itrblueboost_generation_job` (
            `id_itrblueboost_generation_job` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
            `job_type` VARCHAR(50) NOT NULL,
            `status` VARCHAR(20) NOT NULL DEFAULT \'pending\',
            `progress` INT(11) UNSIGNED NOT NULL DEFAULT 0,
            `progress_label` VARCHAR(255) NULL,
            `id_product` INT(11) UNSIGNED NULL,
            `id_category` INT(11) UNSIGNED NULL,
            `request_data` LONGTEXT NULL,
            `response_data` LONGTEXT NULL,
            `error_message` TEXT NULL,
            `date_add` DATETIME NOT NULL,
            `date_upd` DATETIME NOT NULL,
            PRIMARY KEY (`id_itrblueboost_generation_job`),
            KEY `idx_status` (`status`),
            KEY `idx_job_type` (`job_type`),
            KEY `idx_date_add` (`date_add`)
        ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8mb4'
    );

    if (!$result) {
        return false;
    }

    return $db->execute(
        'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'itrblueboost_generation_job_shop` (
            `id_itrblueboost_generation_job` INT(11) UNSIGNED NOT NULL,
            `id_shop` INT(11) UNSIGNED NOT NULL,
            PRIMARY KEY (`id_itrblueboost_generation_job`, `id_shop`)
        ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8mb4'
    );
}
