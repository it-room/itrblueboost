<?php

declare(strict_types=1);

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Upgrade to 1.8.13 - Add generated_content_short column to product_content_lang table.
 *
 * @param Itrblueboost $module
 *
 * @return bool
 */
function upgrade_module_1_8_13($module): bool
{
    $tableName = _DB_PREFIX_ . 'itrblueboost_product_content_lang';

    $columnExists = Db::getInstance()->executeS(
        'SHOW COLUMNS FROM `' . $tableName . '` LIKE \'generated_content_short\''
    );

    if (!empty($columnExists)) {
        return true;
    }

    return Db::getInstance()->execute(
        'ALTER TABLE `' . $tableName . '` ADD `generated_content_short` MEDIUMTEXT NULL AFTER `generated_content`'
    );
}
