<?php

declare(strict_types=1);

namespace Itrblueboost\Install;

use Configuration;
use Db;
use Itrblueboost;
use Itrblueboost\Service\WebserviceKeyManager;
use Language;
use Tab;

/**
 * Handles module installation and uninstallation.
 */
class Installer
{
    /** @var Itrblueboost */
    private $module;

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
            && $this->installConfiguration()
            && $this->installWebserviceKey();
    }

    /**
     * Execute module uninstallation.
     */
    public function uninstall()
    {
        return $this->uninstallWebserviceKey()
            && $this->uninstallDatabase()
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
            'DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'itrblueboost_category_content_shop`',
            'DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'itrblueboost_category_content_lang`',
            'DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'itrblueboost_category_content`',
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
            'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'itrblueboost_product_image` (
                `id_itrblueboost_product_image` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
                `id_product` INT(11) UNSIGNED NOT NULL,
                `filename` VARCHAR(255) NOT NULL,
                `status` VARCHAR(20) DEFAULT \'pending\',
                `prompt_id` INT(11) UNSIGNED NOT NULL,
                `log_id` INT(11) UNSIGNED NULL,
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
                `generated_content_short` MEDIUMTEXT NULL,
                PRIMARY KEY (`id_itrblueboost_product_content`, `id_lang`)
            ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8mb4',

            'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'itrblueboost_product_content_shop` (
                `id_itrblueboost_product_content` INT(11) UNSIGNED NOT NULL,
                `id_shop` INT(11) UNSIGNED NOT NULL,
                PRIMARY KEY (`id_itrblueboost_product_content`, `id_shop`)
            ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8mb4',

            'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'itrblueboost_category_content` (
                `id_itrblueboost_category_content` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
                `id_category` INT(11) UNSIGNED NOT NULL,
                `api_content_id` INT(11) UNSIGNED NULL,
                `content_type` VARCHAR(20) NOT NULL DEFAULT \'description\',
                `status` VARCHAR(20) DEFAULT \'pending\',
                `prompt_id` INT(11) UNSIGNED NOT NULL,
                `active` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
                `original_meta_title` VARCHAR(255) NULL,
                `original_meta_description` VARCHAR(512) NULL,
                `original_meta_keywords` VARCHAR(512) NULL,
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
                `meta_title` VARCHAR(255) NULL,
                `meta_description` VARCHAR(512) NULL,
                `meta_keywords` VARCHAR(512) NULL,
                PRIMARY KEY (`id_itrblueboost_category_content`, `id_lang`)
            ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8mb4',

            'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'itrblueboost_category_content_shop` (
                `id_itrblueboost_category_content` INT(11) UNSIGNED NOT NULL,
                `id_shop` INT(11) UNSIGNED NOT NULL,
                PRIMARY KEY (`id_itrblueboost_category_content`, `id_shop`)
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
     * Cleans all existing module tabs first to ensure a fresh, consistent state.
     */
    private function installTabs(): bool
    {
        $this->cleanAllModuleTabs();

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
     * Remove all existing tabs for this module before reinstalling.
     *
     * @return void
     */
    private function cleanAllModuleTabs(): void
    {
        $db = Db::getInstance();
        $moduleName = pSQL($this->module->name);

        $tabIds = $db->executeS(
            'SELECT id_tab FROM `' . _DB_PREFIX_ . 'tab` WHERE module = \'' . $moduleName . '\''
        );

        if (!is_array($tabIds) || empty($tabIds)) {
            return;
        }

        $ids = array_map(function ($row) {
            return (int) $row['id_tab'];
        }, $tabIds);

        $idList = implode(',', $ids);

        $db->execute('DELETE FROM `' . _DB_PREFIX_ . 'tab_lang` WHERE id_tab IN (' . $idList . ')');
        $db->execute('DELETE FROM `' . _DB_PREFIX_ . 'tab_shop` WHERE id_tab IN (' . $idList . ')');
        $db->execute('DELETE FROM `' . _DB_PREFIX_ . 'tab` WHERE id_tab IN (' . $idList . ')');
    }

    /**
     * @param array<string, mixed> $tabData
     * @param array<string, int> $createdTabs Reference to store created tab IDs
     */
    private function installTab(array $tabData, array &$createdTabs): bool
    {
        $tabId = (int) Tab::getIdFromClassName($tabData['class_name']);

        if ($tabId > 0) {
            $createdTabs[$tabData['class_name']] = $tabId;
            return true;
        }

        // Clean orphan records from previous partial installs
        $this->cleanOrphanTab($tabData['class_name']);

        $tab = new Tab();
        $tab->class_name = $tabData['class_name'];
        $tab->module = $this->module->name;
        $tab->active = $tabData['visible'];

        $tab->id_parent = $this->resolveParentId($tabData['parent_class_name'], $createdTabs);
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
     * Resolve the parent tab ID from class name, integer, or created tabs cache.
     *
     * @param string|int $parentClassName
     * @param array<string, int> $createdTabs
     *
     * @return int
     */
    private function resolveParentId($parentClassName, array $createdTabs): int
    {
        if ($parentClassName === -1 || $parentClassName === 0) {
            return (int) $parentClassName;
        }

        if (isset($createdTabs[$parentClassName])) {
            return $createdTabs[$parentClassName];
        }

        return (int) Tab::getIdFromClassName($parentClassName);
    }

    /**
     * Remove orphan tab records left by a previous partial install.
     *
     * @param string $className
     *
     * @return void
     */
    private function cleanOrphanTab(string $className): void
    {
        $db = Db::getInstance();
        $escapedClassName = pSQL($className);

        $orphanId = (int) $db->getValue(
            'SELECT id_tab FROM `' . _DB_PREFIX_ . 'tab` WHERE class_name = \'' . $escapedClassName . '\''
        );

        if ($orphanId <= 0) {
            return;
        }

        $db->execute('DELETE FROM `' . _DB_PREFIX_ . 'tab_lang` WHERE id_tab = ' . $orphanId);
        $db->execute('DELETE FROM `' . _DB_PREFIX_ . 'tab_shop` WHERE id_tab = ' . $orphanId);
        $db->execute('DELETE FROM `' . _DB_PREFIX_ . 'tab` WHERE id_tab = ' . $orphanId);
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
        $tabs = array_reverse($tabs);

        foreach ($tabs as $tabData) {
            $tabId = (int) Tab::getIdFromClassName($tabData['class_name']);

            if ($tabId > 0) {
                $tab = new Tab($tabId);
                $tab->delete();
            }

            // Also clean any orphan records
            $this->cleanOrphanTab($tabData['class_name']);
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
            // Sous-menu: All Category Contents
            [
                'class_name' => 'AdminItrblueboostAllCategoryContents',
                'route_name' => 'itrblueboost_admin_all_category_contents',
                'visible' => true,
                'parent_class_name' => 'AdminItrblueboostParent',
                'wording' => 'All category contents',
                'wording_domain' => 'Modules.Itrblueboost.Admin',
                'name' => 'All Contenus catégories',
            ],
            // Tab caché: Compatibility (accessible via bouton "Paramètre")
            [
                'class_name' => 'AdminItrblueboostCompatibility',
                'route_name' => 'itrblueboost_compatibility',
                'visible' => false,
                'parent_class_name' => -1,
                'wording' => 'Compatibility',
                'wording_domain' => 'Modules.Itrblueboost.Admin',
                'name' => 'Compatibilité',
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
            // Tab caché: Category Content
            [
                'class_name' => 'AdminItrblueboostCategoryContent',
                'visible' => false,
                'parent_class_name' => -1,
                'wording' => 'Category Content',
                'wording_domain' => 'Modules.Itrblueboost.Admin',
                'name' => 'Category Content',
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
            && Configuration::updateValue(Itrblueboost::CONFIG_SERVICE_CATEGORY_CONTENT, 0)
            && Configuration::updateValue(Itrblueboost::CONFIG_CREDITS_REMAINING, '')
            && Configuration::updateValue(Itrblueboost::CONFIG_BOOTSTRAP_VERSION, 'bootstrap5')
            && Configuration::updateValue(Itrblueboost::CONFIG_API_MODE, 'prod')
            && Configuration::updateValue(Itrblueboost::CONFIG_FAQ_CACHE_TTL, 3600)
            && Configuration::updateValue(Itrblueboost::CONFIG_FAQ_CACHE_ENABLED, 1);
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
            && Configuration::deleteByName(Itrblueboost::CONFIG_SERVICE_CATEGORY_CONTENT)
            && Configuration::deleteByName(Itrblueboost::CONFIG_CREDITS_REMAINING)
            && Configuration::deleteByName(Itrblueboost::CONFIG_BOOTSTRAP_VERSION)
            && Configuration::deleteByName(Itrblueboost::CONFIG_API_MODE)
            && Configuration::deleteByName(Itrblueboost::CONFIG_WEBSERVICE_KEY_ID)
            && Configuration::deleteByName(Itrblueboost::CONFIG_FAQ_CACHE_ENABLED);
    }

    /**
     * Create webservice key and sync with API.
     */
    private function installWebserviceKey(): bool
    {
        $manager = new WebserviceKeyManager();

        return $manager->createAndSync();
    }

    /**
     * Delete webservice key on uninstall.
     */
    private function uninstallWebserviceKey(): bool
    {
        $manager = new WebserviceKeyManager();

        return $manager->deleteKey();
    }
}
