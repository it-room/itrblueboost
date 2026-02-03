<?php

/**
 * Upgrade script for version 1.3.0.
 *
 * Adds Category FAQ tables and configuration.
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
function upgrade_module_1_3_0($module): bool
{
    $db = Db::getInstance();

    // Create category FAQ tables
    $queries = [
        'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'itrblueboost_category_faq` (
            `id_itrblueboost_category_faq` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
            `id_category` INT(11) UNSIGNED NOT NULL,
            `api_faq_id` INT(11) UNSIGNED NULL,
            `status` VARCHAR(20) DEFAULT \'pending\',
            `position` INT(11) UNSIGNED NOT NULL DEFAULT 0,
            `active` TINYINT(1) UNSIGNED NOT NULL DEFAULT 1,
            `date_add` DATETIME NOT NULL,
            `date_upd` DATETIME NOT NULL,
            PRIMARY KEY (`id_itrblueboost_category_faq`),
            KEY `id_category` (`id_category`),
            KEY `status` (`status`)
        ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8mb4',

        'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'itrblueboost_category_faq_lang` (
            `id_itrblueboost_category_faq` INT(11) UNSIGNED NOT NULL,
            `id_lang` INT(11) UNSIGNED NOT NULL,
            `question` TEXT NOT NULL,
            `answer` TEXT NOT NULL,
            PRIMARY KEY (`id_itrblueboost_category_faq`, `id_lang`)
        ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8mb4',

        'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'itrblueboost_category_faq_shop` (
            `id_itrblueboost_category_faq` INT(11) UNSIGNED NOT NULL,
            `id_shop` INT(11) UNSIGNED NOT NULL,
            PRIMARY KEY (`id_itrblueboost_category_faq`, `id_shop`)
        ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8mb4',

        // API Logs table
        'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'itrblueboost_api_log` (
            `id_itrblueboost_api_log` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
            `method` VARCHAR(10) NOT NULL,
            `endpoint` VARCHAR(500) NOT NULL,
            `request_body` LONGTEXT NULL,
            `request_headers` TEXT NULL,
            `response_code` INT(11) NULL,
            `response_body` LONGTEXT NULL,
            `duration` DECIMAL(10,6) NULL,
            `error_message` TEXT NULL,
            `context` VARCHAR(100) NULL,
            `date_add` DATETIME NOT NULL,
            PRIMARY KEY (`id_itrblueboost_api_log`),
            KEY `idx_date_add` (`date_add`),
            KEY `idx_context` (`context`),
            KEY `idx_response_code` (`response_code`)
        ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8mb4',

        // Credit History table
        'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'itrblueboost_credit_history` (
            `id_itrblueboost_credit_history` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
            `service_code` VARCHAR(50) NOT NULL,
            `credits_used` INT(11) UNSIGNED NOT NULL,
            `credits_remaining` INT(11) UNSIGNED NOT NULL DEFAULT 0,
            `entity_id` INT(11) UNSIGNED NULL,
            `entity_type` VARCHAR(50) NULL,
            `details` VARCHAR(255) NULL,
            `date_add` DATETIME NOT NULL,
            PRIMARY KEY (`id_itrblueboost_credit_history`),
            KEY `idx_service_code` (`service_code`),
            KEY `idx_date_add` (`date_add`)
        ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8mb4',
    ];

    foreach ($queries as $query) {
        if (!$db->execute($query)) {
            return false;
        }
    }

    // Add configuration for category FAQ service
    if (!Configuration::hasKey('ITRBLUEBOOST_SERVICE_CATEGORY_FAQ')) {
        Configuration::updateValue('ITRBLUEBOOST_SERVICE_CATEGORY_FAQ', 0);
    }

    // Register new hooks
    if (!$module->registerHook('filterCategoryContent')) {
        // Hook may not exist in all PS versions, ignore error
    }

    if (!$module->registerHook('actionCategoryDelete')) {
        // Hook may not exist in all PS versions, ignore error
    }

    // Add admin tab for category FAQ
    $tabId = Tab::getIdFromClassName('AdminItrblueboostCategoryFaq');

    if (!$tabId) {
        $tab = new Tab();
        $tab->class_name = 'AdminItrblueboostCategoryFaq';
        $tab->module = $module->name;
        $tab->active = false;
        $tab->id_parent = -1;
        $tab->route_name = 'itrblueboost_admin_category_faq_index';
        $tab->wording = 'Category FAQ';
        $tab->wording_domain = 'Modules.Itrblueboost.Admin';

        $languages = Language::getLanguages(false);
        $names = [];

        foreach ($languages as $language) {
            $names[(int) $language['id_lang']] = 'Category FAQ';
        }

        $tab->name = $names;

        if (!$tab->add()) {
            return false;
        }
    }

    // Add admin tab for API Logs
    $tabIdLogs = Tab::getIdFromClassName('AdminItrblueboostApiLogs');

    if (!$tabIdLogs) {
        $tab = new Tab();
        $tab->class_name = 'AdminItrblueboostApiLogs';
        $tab->module = $module->name;
        $tab->active = false;
        $tab->id_parent = -1;
        $tab->route_name = 'itrblueboost_admin_api_log_index';
        $tab->wording = 'API Logs';
        $tab->wording_domain = 'Modules.Itrblueboost.Admin';

        $languages = Language::getLanguages(false);
        $names = [];

        foreach ($languages as $language) {
            $names[(int) $language['id_lang']] = 'API Logs';
        }

        $tab->name = $names;

        if (!$tab->add()) {
            return false;
        }
    }

    return true;
}
