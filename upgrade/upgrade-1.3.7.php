<?php

/**
 * Upgrade script for version 1.3.7.
 *
 * - Adds missing columns to itrblueboost_product_faq table:
 *   - api_faq_id: INT(11) UNSIGNED NULL
 *   - status: VARCHAR(20) DEFAULT 'pending'
 *
 * - Moves FAQ and AI Images buttons to product footer:
 *   - PrestaShop 8: buttons positioned after .group-default in #product_footer_actions
 *   - PrestaShop 1.7: buttons positioned in .product-footer.justify-content-md-center
 *
 * - Adds version-specific CSS files:
 *   - admin-product-buttons-ps8.css for PrestaShop 8
 *   - admin-product-buttons-ps17.css for PrestaShop 1.7
 */

declare(strict_types=1);

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * @param Itrblueboost $module
 *
 * @return bool
 */
function upgrade_module_1_3_7($module): bool
{
    $db = Db::getInstance();
    $tableName = _DB_PREFIX_ . 'itrblueboost_product_faq';

    // Check if 'status' column exists
    $statusColumnExists = false;
    $columns = $db->executeS("SHOW COLUMNS FROM `{$tableName}` LIKE 'status'");
    if (!empty($columns)) {
        $statusColumnExists = true;
    }

    // Check if 'api_faq_id' column exists
    $apiFaqIdColumnExists = false;
    $columns = $db->executeS("SHOW COLUMNS FROM `{$tableName}` LIKE 'api_faq_id'");
    if (!empty($columns)) {
        $apiFaqIdColumnExists = true;
    }

    // Add 'api_faq_id' column if it doesn't exist
    if (!$apiFaqIdColumnExists) {
        $sql = "ALTER TABLE `{$tableName}` ADD COLUMN `api_faq_id` INT(11) UNSIGNED NULL AFTER `id_product`";
        if (!$db->execute($sql)) {
            return false;
        }
    }

    // Add 'status' column if it doesn't exist
    if (!$statusColumnExists) {
        $sql = "ALTER TABLE `{$tableName}` ADD COLUMN `status` VARCHAR(20) DEFAULT 'pending' AFTER `api_faq_id`";
        if (!$db->execute($sql)) {
            return false;
        }

        // Add index on status column
        $db->execute("ALTER TABLE `{$tableName}` ADD KEY `status` (`status`)");

        // Update existing rows to have 'completed' status (they were already generated)
        $db->execute("UPDATE `{$tableName}` SET `status` = 'completed' WHERE `status` = 'pending' OR `status` IS NULL");
    }

    // Clear Symfony cache to ensure new CSS files are loaded
    try {
        $cacheDir = _PS_ROOT_DIR_ . '/var/cache/';
        if (is_dir($cacheDir)) {
            // Touch module file to invalidate cache
            $moduleFile = _PS_MODULE_DIR_ . 'itrblueboost/itrblueboost.php';
            if (file_exists($moduleFile)) {
                touch($moduleFile);
            }
        }
    } catch (Exception $e) {
        // Silently fail, cache will be cleared on next admin page load
    }

    return true;
}
