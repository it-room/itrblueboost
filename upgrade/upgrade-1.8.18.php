<?php

declare(strict_types=1);

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Upgrade to 1.8.18 - Add SEO fields to category content.
 *
 * @param Itrblueboost $module
 *
 * @return bool
 */
function upgrade_module_1_8_18($module): bool
{
    $db = Db::getInstance();

    $queries = [
        'ALTER TABLE `' . _DB_PREFIX_ . 'itrblueboost_category_content`
            ADD COLUMN `original_meta_title` VARCHAR(255) NULL AFTER `active`,
            ADD COLUMN `original_meta_description` VARCHAR(512) NULL AFTER `original_meta_title`,
            ADD COLUMN `original_meta_keywords` VARCHAR(512) NULL AFTER `original_meta_description`',

        'ALTER TABLE `' . _DB_PREFIX_ . 'itrblueboost_category_content_lang`
            ADD COLUMN `meta_title` VARCHAR(255) NULL AFTER `generated_content_short`,
            ADD COLUMN `meta_description` VARCHAR(512) NULL AFTER `meta_title`,
            ADD COLUMN `meta_keywords` VARCHAR(512) NULL AFTER `meta_description`',
    ];

    foreach ($queries as $query) {
        try {
            $db->execute($query);
        } catch (\Exception $e) {
            // Columns may already exist
        }
    }

    return true;
}
