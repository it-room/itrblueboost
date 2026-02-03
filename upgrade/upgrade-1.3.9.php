<?php

/**
 * Upgrade script for version 1.3.9.
 *
 * Installs all missing admin tabs and fixes parent menu position (CONFIGURE section).
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
function upgrade_module_1_3_9($module): bool
{
    $tabs = getItrblueboostTabs();
    $createdTabs = [];

    foreach ($tabs as $tabData) {
        if (!installItrblueboostTab($tabData, $createdTabs, $module->name)) {
            return false;
        }
    }

    return true;
}

/**
 * @return array<int, array<string, mixed>>
 */
function getItrblueboostTabs(): array
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
    ];
}

/**
 * @param array<string, mixed> $tabData
 * @param array<string, int> $createdTabs Reference to store created tab IDs
 * @param string $moduleName
 *
 * @return bool
 */
function installItrblueboostTab(array $tabData, array &$createdTabs, string $moduleName): bool
{
    $tabId = Tab::getIdFromClassName($tabData['class_name']);

    // Calculate expected parent ID
    $expectedParentId = getItrblueboostExpectedParentId($tabData, $createdTabs);

    // Tab exists - check if parent needs to be updated
    if ($tabId) {
        $tab = new Tab($tabId);
        $createdTabs[$tabData['class_name']] = $tabId;

        // Update parent if different
        if ((int) $tab->id_parent !== $expectedParentId) {
            $tab->id_parent = $expectedParentId;
            $tab->update();
        }

        return true;
    }

    // Create new tab
    $tab = new Tab();
    $tab->class_name = $tabData['class_name'];
    $tab->module = $moduleName;
    $tab->active = $tabData['visible'];
    $tab->id_parent = $expectedParentId;

    $tab->name = getItrblueboostTabNames($tabData['name']);

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
 * @param array<string, mixed> $tabData
 * @param array<string, int> $createdTabs
 *
 * @return int
 */
function getItrblueboostExpectedParentId(array $tabData, array $createdTabs): int
{
    if ($tabData['parent_class_name'] === -1) {
        return -1;
    }

    if ($tabData['parent_class_name'] === 0) {
        return 0;
    }

    // Try to get parent from our created tabs first, then from database
    if (isset($createdTabs[$tabData['parent_class_name']])) {
        return $createdTabs[$tabData['parent_class_name']];
    }

    return (int) Tab::getIdFromClassName($tabData['parent_class_name']);
}

/**
 * @param string $name
 *
 * @return array<int, string>
 */
function getItrblueboostTabNames(string $name): array
{
    $names = [];
    $languages = Language::getLanguages(false);

    foreach ($languages as $language) {
        $names[(int) $language['id_lang']] = $name;
    }

    return $names;
}
