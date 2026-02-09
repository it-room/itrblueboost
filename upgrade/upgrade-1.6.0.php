<?php

declare(strict_types=1);

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Upgrade to 1.6.0 - Add Product Content tables for AI-generated descriptions.
 *
 * @param Itrblueboost $module
 *
 * @return bool
 */
function upgrade_module_1_6_0($module): bool
{
    $db = Db::getInstance();

    $queries = [
        'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'itrblueboost_product_content` (
            `id_itrblueboost_product_content` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
            `id_product` INT(11) UNSIGNED NOT NULL,
            `api_content_id` INT(11) UNSIGNED NULL,
            `content_type` VARCHAR(20) NOT NULL DEFAULT \'description\',
            `status` VARCHAR(20) DEFAULT \'pending\',
            `prompt_id` INT(11) UNSIGNED NOT NULL,
            `active` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
            `date_add` DATETIME NOT NULL,
            `date_upd` DATETIME NOT NULL,
            PRIMARY KEY (`id_itrblueboost_product_content`),
            KEY `id_product` (`id_product`),
            KEY `status` (`status`),
            KEY `content_type` (`content_type`)
        ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8mb4',

        'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'itrblueboost_product_content_lang` (
            `id_itrblueboost_product_content` INT(11) UNSIGNED NOT NULL,
            `id_lang` INT(11) UNSIGNED NOT NULL,
            `generated_content` MEDIUMTEXT NOT NULL,
            PRIMARY KEY (`id_itrblueboost_product_content`, `id_lang`)
        ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8mb4',

        'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'itrblueboost_product_content_shop` (
            `id_itrblueboost_product_content` INT(11) UNSIGNED NOT NULL,
            `id_shop` INT(11) UNSIGNED NOT NULL,
            PRIMARY KEY (`id_itrblueboost_product_content`, `id_shop`)
        ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8mb4',
    ];

    foreach ($queries as $query) {
        if (!$db->execute($query)) {
            return false;
        }
    }

    // Add configuration for content service
    Configuration::updateValue('ITRBLUEBOOST_SERVICE_CONTENT', 0);

    // Add tab for All Product Contents
    $languages = Language::getLanguages(false);
    $tabId = Tab::getIdFromClassName('AdminItrblueboostAllProductContents');

    if (!$tabId) {
        $tab = new Tab();
        $tab->class_name = 'AdminItrblueboostAllProductContents';
        $tab->module = $module->name;
        $tab->active = true;
        $tab->route_name = 'itrblueboost_admin_all_product_contents';

        // Get parent tab ID
        $parentTabId = Tab::getIdFromClassName('AdminItrblueboostParent');
        $tab->id_parent = $parentTabId ?: 0;

        $names = [];
        foreach ($languages as $lang) {
            $names[$lang['id_lang']] = 'All product contents';
        }
        $tab->name = $names;

        if (!$tab->add()) {
            return false;
        }
    }

    // Add hidden tab for product content management
    $tabId = Tab::getIdFromClassName('AdminItrblueboostProductContent');

    if (!$tabId) {
        $tab = new Tab();
        $tab->class_name = 'AdminItrblueboostProductContent';
        $tab->module = $module->name;
        $tab->active = false;
        $tab->route_name = 'itrblueboost_admin_product_content_index';
        $tab->id_parent = -1;

        $names = [];
        foreach ($languages as $lang) {
            $names[$lang['id_lang']] = 'Product Content';
        }
        $tab->name = $names;

        if (!$tab->add()) {
            return false;
        }
    }

    return true;
}
