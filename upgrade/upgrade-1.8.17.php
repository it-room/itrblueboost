<?php

declare(strict_types=1);

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Upgrade to 1.8.17 - Add category content tables and configuration.
 *
 * @param Itrblueboost $module
 *
 * @return bool
 */
function upgrade_module_1_8_17($module): bool
{
    $db = Db::getInstance();

    $queries = [
        'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'itrblueboost_category_content` (
            `id_itrblueboost_category_content` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
            `id_category` INT(11) UNSIGNED NOT NULL,
            `api_content_id` INT(11) UNSIGNED NULL,
            `content_type` VARCHAR(20) NOT NULL DEFAULT \'description\',
            `status` VARCHAR(20) DEFAULT \'pending\',
            `prompt_id` INT(11) UNSIGNED NOT NULL,
            `active` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
            `date_add` DATETIME NOT NULL,
            `date_upd` DATETIME NOT NULL,
            PRIMARY KEY (`id_itrblueboost_category_content`),
            KEY `id_category` (`id_category`),
            KEY `status` (`status`)
        ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8mb4',

        'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'itrblueboost_category_content_lang` (
            `id_itrblueboost_category_content` INT(11) UNSIGNED NOT NULL,
            `id_lang` INT(11) UNSIGNED NOT NULL,
            `generated_content` MEDIUMTEXT NOT NULL,
            `generated_content_short` MEDIUMTEXT NULL,
            PRIMARY KEY (`id_itrblueboost_category_content`, `id_lang`)
        ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8mb4',

        'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'itrblueboost_category_content_shop` (
            `id_itrblueboost_category_content` INT(11) UNSIGNED NOT NULL,
            `id_shop` INT(11) UNSIGNED NOT NULL,
            PRIMARY KEY (`id_itrblueboost_category_content`, `id_shop`)
        ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8mb4',
    ];

    foreach ($queries as $query) {
        if (!$db->execute($query)) {
            return false;
        }
    }

    // Add configuration
    Configuration::updateValue('ITRBLUEBOOST_SERVICE_CATEGORY_CONTENT', 0);

    // Install or fix tab (route_name must be empty since the route requires id_category)
    $tabId = Tab::getIdFromClassName('AdminItrblueboostCategoryContent');
    if ($tabId) {
        $tab = new Tab($tabId);
        $tab->route_name = '';
        $tab->update();
    } else {
        $tab = new Tab();
        $tab->class_name = 'AdminItrblueboostCategoryContent';
        $tab->module = $module->name;
        $tab->active = false;
        $tab->id_parent = -1;
        $tab->route_name = '';

        $languages = Language::getLanguages(false);
        $names = [];
        foreach ($languages as $lang) {
            $names[(int) $lang['id_lang']] = 'Category Content';
        }
        $tab->name = $names;
        $tab->add();
    }

    // Install "All Category Contents" tab
    $allTabId = Tab::getIdFromClassName('AdminItrblueboostAllCategoryContents');
    if (!$allTabId) {
        $parentTabId = Tab::getIdFromClassName('AdminItrblueboostParent');

        $allTab = new Tab();
        $allTab->class_name = 'AdminItrblueboostAllCategoryContents';
        $allTab->module = $module->name;
        $allTab->active = true;
        $allTab->id_parent = $parentTabId ?: -1;
        $allTab->route_name = 'itrblueboost_admin_all_category_contents';

        $languages = Language::getLanguages(false);
        $names = [];
        foreach ($languages as $lang) {
            $names[(int) $lang['id_lang']] = 'All Contenus catégories';
        }
        $allTab->name = $names;
        $allTab->add();
    }

    return true;
}
