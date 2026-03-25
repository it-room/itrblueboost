<?php

declare(strict_types=1);

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Upgrade to v2.0.0 — Remove FAQ local storage, switch to API-based FAQ reading.
 *
 * @param Itrblueboost $module
 */
function upgrade_module_2_0_0($module): bool
{
    $db = Db::getInstance();

    // 1. Drop FAQ tables (order: shop → lang → main to respect foreign key deps)
    $tables = [
        'itrblueboost_product_faq_shop',
        'itrblueboost_product_faq_lang',
        'itrblueboost_product_faq',
        'itrblueboost_category_faq_shop',
        'itrblueboost_category_faq_lang',
        'itrblueboost_category_faq',
    ];

    foreach ($tables as $table) {
        $db->execute('DROP TABLE IF EXISTS `' . _DB_PREFIX_ . $table . '`');
    }

    // 2. Remove FAQ admin tabs
    $tabClassNames = [
        'AdminItrblueboostAllProductFaqs',
        'AdminItrblueboostAllCategoryFaqs',
        'AdminItrblueboostGeneratedFaqs',
        'AdminItrblueboostProductFaq',
        'AdminItrblueboostCategoryFaq',
    ];

    foreach ($tabClassNames as $className) {
        $tabId = (int) Tab::getIdFromClassName($className);
        if ($tabId > 0) {
            $tab = new Tab($tabId);
            $tab->delete();
        }
    }

    // 3. Add FAQ cache TTL config
    Configuration::updateValue('ITRBLUEBOOST_FAQ_CACHE_TTL', 3600);

    // 4. Create cache directory
    $cacheDir = _PS_MODULE_DIR_ . 'itrblueboost/var/cache/faq';
    if (!is_dir($cacheDir)) {
        @mkdir($cacheDir, 0755, true);
    }

    return true;
}
