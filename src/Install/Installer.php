<?php

declare(strict_types=1);

namespace Itrblueboost\Install;

use Configuration;
use Db;
use Itrblueboost;
use Language;
use Tab;

/**
 * Handles module installation and uninstallation.
 */
class Installer
{
    private Itrblueboost $module;

    public function __construct(Itrblueboost $module)
    {
        $this->module = $module;
    }

    /**
     * Execute module installation.
     */
    public function install(): bool
    {
        return $this->installDatabase()
            && $this->installTabs()
            && $this->installConfiguration();
    }

    /**
     * Execute module uninstallation.
     */
    public function uninstall()
    {
        return $this->uninstallDatabase()
            && $this->uninstallTabs()
            && $this->uninstallConfiguration();
    }

    /**
     * Install database tables.
     */
    private function installDatabase(): bool
    {
        $queries = $this->getDatabaseInstallQueries();

        foreach ($queries as $query) {
            if (!Db::getInstance()->execute($query)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Uninstall database tables.
     */
    private function uninstallDatabase(): bool
    {
        $queries = [
            'DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'itrblueboost_product_faq_shop`',
            'DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'itrblueboost_product_faq_lang`',
            'DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'itrblueboost_product_faq`',
            'DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'itrblueboost_product_image_shop`',
            'DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'itrblueboost_product_image`',
            'DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'itrblueboost_category_faq_shop`',
            'DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'itrblueboost_category_faq_lang`',
            'DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'itrblueboost_category_faq`',
            'DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'itrblueboost_api_log`',
            'DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'itrblueboost_credit_history`',
            'DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'itrblueboost_product_content_shop`',
            'DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'itrblueboost_product_content_lang`',
            'DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'itrblueboost_product_content`',
            'DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'itrblueboost_generation_job_shop`',
            'DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'itrblueboost_generation_job`',
        ];

        foreach ($queries as $query) {
            if (!Db::getInstance()->execute($query)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array<int, string>
     */
    private function getDatabaseInstallQueries(): array
    {
        return [
            'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'itrblueboost_product_faq` (
                `id_itrblueboost_product_faq` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
                `id_product` INT(11) UNSIGNED NOT NULL,
                `api_faq_id` INT(11) UNSIGNED NULL,
                `status` VARCHAR(20) DEFAULT \'pending\',
                `position` INT(11) UNSIGNED NOT NULL DEFAULT 0,
                `active` TINYINT(1) UNSIGNED NOT NULL DEFAULT 1,
                `date_add` DATETIME NOT NULL,
                `date_upd` DATETIME NOT NULL,
                PRIMARY KEY (`id_itrblueboost_product_faq`),
                KEY `id_product` (`id_product`),
                KEY `status` (`status`)
            ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8mb4',

            'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'itrblueboost_product_faq_lang` (
                `id_itrblueboost_product_faq` INT(11) UNSIGNED NOT NULL,
                `id_lang` INT(11) UNSIGNED NOT NULL,
                `question` TEXT NOT NULL,
                `answer` TEXT NOT NULL,
                PRIMARY KEY (`id_itrblueboost_product_faq`, `id_lang`)
            ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8mb4',

            'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'itrblueboost_product_faq_shop` (
                `id_itrblueboost_product_faq` INT(11) UNSIGNED NOT NULL,
                `id_shop` INT(11) UNSIGNED NOT NULL,
                PRIMARY KEY (`id_itrblueboost_product_faq`, `id_shop`)
            ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8mb4',

            'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'itrblueboost_product_image` (
                `id_itrblueboost_product_image` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
                `id_product` INT(11) UNSIGNED NOT NULL,
                `filename` VARCHAR(255) NOT NULL,
                `status` VARCHAR(20) DEFAULT \'pending\',
                `prompt_id` INT(11) UNSIGNED NOT NULL,
                `id_image` INT(11) UNSIGNED NULL,
                `rejection_reason` VARCHAR(1000) NULL,
                `date_add` DATETIME NOT NULL,
                `date_upd` DATETIME NOT NULL,
                PRIMARY KEY (`id_itrblueboost_product_image`),
                KEY `id_product` (`id_product`),
                KEY `status` (`status`)
            ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8mb4',

            'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'itrblueboost_product_image_shop` (
                `id_itrblueboost_product_image` INT(11) UNSIGNED NOT NULL,
                `id_shop` INT(11) UNSIGNED NOT NULL,
                PRIMARY KEY (`id_itrblueboost_product_image`, `id_shop`)
            ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8mb4',

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
            ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8mb4',

            'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'itrblueboost_generation_job_shop` (
                `id_itrblueboost_generation_job` INT(11) UNSIGNED NOT NULL,
                `id_shop` INT(11) UNSIGNED NOT NULL,
                PRIMARY KEY (`id_itrblueboost_generation_job`, `id_shop`)
            ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8mb4',
        ];
    }

    /**
     * Install admin tabs.
     */
    private function installTabs(): bool
    {
        $tabs = $this->getTabs();
        $createdTabs = [];

        foreach ($tabs as $tabData) {
            if (!$this->installTab($tabData, $createdTabs)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array<string, mixed> $tabData
     * @param array<string, int> $createdTabs Reference to store created tab IDs
     */
    private function installTab(array $tabData, array &$createdTabs): bool
    {
        $tabId = Tab::getIdFromClassName($tabData['class_name']);

        if ($tabId) {
            $createdTabs[$tabData['class_name']] = $tabId;
            return true;
        }

        $tab = new Tab();
        $tab->class_name = $tabData['class_name'];
        $tab->module = $this->module->name;
        $tab->active = $tabData['visible'];

        // Handle parent: -1 = hidden, 0 = root level, string = parent class name
        if ($tabData['parent_class_name'] === -1) {
            $tab->id_parent = -1;
        } elseif ($tabData['parent_class_name'] === 0) {
            $tab->id_parent = 0;
        } else {
            // Try to get parent from our created tabs first, then from database
            if (isset($createdTabs[$tabData['parent_class_name']])) {
                $tab->id_parent = $createdTabs[$tabData['parent_class_name']];
            } else {
                $tab->id_parent = (int) Tab::getIdFromClassName($tabData['parent_class_name']);
            }
        }

        $tab->name = $this->getTabNames($tabData['name']);

        if (!empty($tabData['route_name'])) {
            $tab->route_name = $tabData['route_name'];
        }

        if (!empty($tabData['wording'])) {
            $tab->wording = $tabData['wording'];
            $tab->wording_domain = $tabData['wording_domain'] ?? '';
        }

        if (!empty($tabData['icon'])) {
            $tab->icon = $tabData['icon'];
        }

        $result = $tab->add();

        if ($result && $tab->id) {
            $createdTabs[$tabData['class_name']] = (int) $tab->id;
        }

        return $result;
    }

    /**
     * @return array<int, string>
     */
    private function getTabNames(string $name): array
    {
        $names = [];
        $languages = Language::getLanguages(false);

        foreach ($languages as $language) {
            $names[(int) $language['id_lang']] = $name;
        }

        return $names;
    }

    /**
     * Uninstall admin tabs.
     */
    private function uninstallTabs(): bool
    {
        // First, delete all tabs from this module (cleanup any orphans)
        $moduleTabs = Tab::getCollectionFromModule($this->module->name);
        foreach ($moduleTabs as $tab) {
            $tab->delete();
        }

        // Then delete by class name (in case some were missed)
        $tabs = $this->getTabs();

        // Delete children first, then parents (reverse order)
        $tabs = array_reverse($tabs);

        foreach ($tabs as $tabData) {
            $tabId = Tab::getIdFromClassName($tabData['class_name']);

            if (!$tabId) {
                continue;
            }

            $tab = new Tab($tabId);
            $tab->delete();
        }

        return true;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function getTabs(): array
    {
        return [
            // Menu principal (dans Configurer, en dropdown)
            [
                'class_name' => 'AdminItrblueboostParent',
                'visible' => true,
                'parent_class_name' => 'CONFIGURE',
                'wording' => 'ITR Blue Boost',
                'wording_domain' => 'Modules.Itrblueboost.Admin',
                'name' => 'ITR Blue Boost',
                'icon' => 'auto_awesome',
            ],
            // Sous-menu: Settings
            [
                'class_name' => 'AdminItrblueboostConfiguration',
                'route_name' => 'itrblueboost_configuration',
                'visible' => true,
                'parent_class_name' => 'AdminItrblueboostParent',
                'wording' => 'Settings',
                'wording_domain' => 'Modules.Itrblueboost.Admin',
                'name' => 'Settings',
            ],
            // Sous-menu: All images générées
            [
                'class_name' => 'AdminItrblueboostGeneratedImages',
                'route_name' => 'itrblueboost_admin_generated_images',
                'visible' => true,
                'parent_class_name' => 'AdminItrblueboostParent',
                'wording' => 'All generated images',
                'wording_domain' => 'Modules.Itrblueboost.Admin',
                'name' => 'All images générées',
            ],
            // Sous-menu: All Product FAQs
            [
                'class_name' => 'AdminItrblueboostAllProductFaqs',
                'route_name' => 'itrblueboost_admin_all_product_faqs',
                'visible' => true,
                'parent_class_name' => 'AdminItrblueboostParent',
                'wording' => 'All product FAQs',
                'wording_domain' => 'Modules.Itrblueboost.Admin',
                'name' => 'All FAQs produits',
            ],
            // Sous-menu: All Category FAQs
            [
                'class_name' => 'AdminItrblueboostAllCategoryFaqs',
                'route_name' => 'itrblueboost_admin_all_category_faqs',
                'visible' => true,
                'parent_class_name' => 'AdminItrblueboostParent',
                'wording' => 'All category FAQs',
                'wording_domain' => 'Modules.Itrblueboost.Admin',
                'name' => 'All FAQs catégories',
            ],
            // Sous-menu: All Product Contents
            [
                'class_name' => 'AdminItrblueboostAllProductContents',
                'route_name' => 'itrblueboost_admin_all_product_contents',
                'visible' => true,
                'parent_class_name' => 'AdminItrblueboostParent',
                'wording' => 'All product contents',
                'wording_domain' => 'Modules.Itrblueboost.Admin',
                'name' => 'All Contenus produits',
            ],
            // Sous-menu: Compatibility
            [
                'class_name' => 'AdminItrblueboostCompatibility',
                'route_name' => 'itrblueboost_compatibility',
                'visible' => true,
                'parent_class_name' => 'AdminItrblueboostParent',
                'wording' => 'Compatibility',
                'wording_domain' => 'Modules.Itrblueboost.Admin',
                'name' => 'Compatibilité',
                'icon' => 'settings_suggest',
            ],
            // Ancien menu FAQs générées (caché)
            [
                'class_name' => 'AdminItrblueboostGeneratedFaqs',
                'route_name' => 'itrblueboost_admin_generated_faqs',
                'visible' => false,
                'parent_class_name' => -1,
                'wording' => 'All generated FAQs',
                'wording_domain' => 'Modules.Itrblueboost.Admin',
                'name' => 'All FAQs générées',
            ],
            // Tabs cachés (contextuels aux produits/catégories)
            [
                'class_name' => 'AdminItrblueboostProductFaq',
                'route_name' => 'itrblueboost_admin_product_faq_index',
                'visible' => false,
                'parent_class_name' => -1,
                'wording' => 'Product FAQ',
                'wording_domain' => 'Modules.Itrblueboost.Admin',
                'name' => 'Product FAQ',
            ],
            [
                'class_name' => 'AdminItrblueboostProductImage',
                'route_name' => 'itrblueboost_admin_product_image_index',
                'visible' => false,
                'parent_class_name' => -1,
                'wording' => 'AI Product Images',
                'wording_domain' => 'Modules.Itrblueboost.Admin',
                'name' => 'AI Product Images',
            ],
            [
                'class_name' => 'AdminItrblueboostCategoryFaq',
                'route_name' => 'itrblueboost_admin_category_faq_index',
                'visible' => false,
                'parent_class_name' => -1,
                'wording' => 'Category FAQ',
                'wording_domain' => 'Modules.Itrblueboost.Admin',
                'name' => 'Category FAQ',
            ],
            [
                'class_name' => 'AdminItrblueboostApiLogs',
                'route_name' => 'itrblueboost_admin_api_log_index',
                'visible' => false,
                'parent_class_name' => -1,
                'wording' => 'API Logs',
                'wording_domain' => 'Modules.Itrblueboost.Admin',
                'name' => 'API Logs',
            ],
            // Tab caché: Product Content
            [
                'class_name' => 'AdminItrblueboostProductContent',
                'route_name' => 'itrblueboost_admin_product_content_index',
                'visible' => false,
                'parent_class_name' => -1,
                'wording' => 'Product Content',
                'wording_domain' => 'Modules.Itrblueboost.Admin',
                'name' => 'Product Content',
            ],
        ];
    }

    /**
     * Install default configuration values.
     */
    private function installConfiguration(): bool
    {
        return Configuration::updateValue(Itrblueboost::CONFIG_API_KEY, '')
            && Configuration::updateValue(Itrblueboost::CONFIG_SERVICE_FAQ, 0)
            && Configuration::updateValue(Itrblueboost::CONFIG_SERVICE_IMAGE, 0)
            && Configuration::updateValue(Itrblueboost::CONFIG_SERVICE_CATEGORY_FAQ, 0)
            && Configuration::updateValue(Itrblueboost::CONFIG_SERVICE_CONTENT, 0)
            && Configuration::updateValue(Itrblueboost::CONFIG_CREDITS_REMAINING, '')
            && Configuration::updateValue(Itrblueboost::CONFIG_BOOTSTRAP_VERSION, 'bootstrap5')
            && Configuration::updateValue(Itrblueboost::CONFIG_API_MODE, 'prod');
    }

    /**
     * Remove configuration values.
     */
    private function uninstallConfiguration(): bool
    {
        return Configuration::deleteByName(Itrblueboost::CONFIG_API_KEY)
            && Configuration::deleteByName(Itrblueboost::CONFIG_SERVICE_FAQ)
            && Configuration::deleteByName(Itrblueboost::CONFIG_SERVICE_IMAGE)
            && Configuration::deleteByName(Itrblueboost::CONFIG_SERVICE_CATEGORY_FAQ)
            && Configuration::deleteByName(Itrblueboost::CONFIG_SERVICE_CONTENT)
            && Configuration::deleteByName(Itrblueboost::CONFIG_CREDITS_REMAINING)
            && Configuration::deleteByName(Itrblueboost::CONFIG_BOOTSTRAP_VERSION)
            && Configuration::deleteByName(Itrblueboost::CONFIG_API_MODE);
    }
}
