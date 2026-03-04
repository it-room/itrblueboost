<?php

declare(strict_types=1);

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Upgrade to 1.8.8 - Add log_id column to product_image table.
 *
 * @param Itrblueboost $module
 *
 * @return bool
 */
function upgrade_module_1_8_8($module): bool
{
    $tableName = _DB_PREFIX_ . 'itrblueboost_product_image';

    $columnExists = Db::getInstance()->executeS(
        'SHOW COLUMNS FROM `' . $tableName . '` LIKE \'log_id\''
    );

    if (!empty($columnExists)) {
        return true;
    }

    return Db::getInstance()->execute(
        'ALTER TABLE `' . $tableName . '` ADD `log_id` INT(11) UNSIGNED NULL AFTER `prompt_id`'
    );
}
