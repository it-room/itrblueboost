<?php

declare(strict_types=1);

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Upgrade to 1.6.1 - Add rejection_reason column to product_image table.
 *
 * @param Itrblueboost $module
 *
 * @return bool
 */
function upgrade_module_1_6_1($module): bool
{
    $db = Db::getInstance();

    $columnExists = $db->executeS(
        'SHOW COLUMNS FROM `' . _DB_PREFIX_ . 'itrblueboost_product_image` LIKE \'rejection_reason\''
    );

    if (empty($columnExists)) {
        return $db->execute(
            'ALTER TABLE `' . _DB_PREFIX_ . 'itrblueboost_product_image` '
            . 'ADD COLUMN `rejection_reason` VARCHAR(1000) NULL AFTER `id_image`'
        );
    }

    return true;
}
