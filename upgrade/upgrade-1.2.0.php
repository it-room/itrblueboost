<?php

declare(strict_types=1);

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Upgrade to version 1.2.0
 * - Add AI product image tables
 * - Add admin tab for AI images
 * - Create uploads/pending directory
 *
 * @param Itrblueboost $module Module instance
 *
 * @return bool True on success
 */
function upgrade_module_1_2_0(Itrblueboost $module): bool
{
    $db = Db::getInstance();

    $sql1 = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'itrblueboost_product_image` (
        `id_itrblueboost_product_image` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
        `id_product` INT(11) UNSIGNED NOT NULL,
        `filename` VARCHAR(255) NOT NULL,
        `status` VARCHAR(20) DEFAULT \'pending\',
        `prompt_id` INT(11) UNSIGNED NOT NULL,
        `id_image` INT(11) UNSIGNED NULL,
        `date_add` DATETIME NOT NULL,
        `date_upd` DATETIME NOT NULL,
        PRIMARY KEY (`id_itrblueboost_product_image`),
        KEY `id_product` (`id_product`),
        KEY `status` (`status`)
    ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8mb4';

    if (!$db->execute($sql1)) {
        return false;
    }

    $sql2 = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'itrblueboost_product_image_shop` (
        `id_itrblueboost_product_image` INT(11) UNSIGNED NOT NULL,
        `id_shop` INT(11) UNSIGNED NOT NULL,
        PRIMARY KEY (`id_itrblueboost_product_image`, `id_shop`)
    ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8mb4';

    if (!$db->execute($sql2)) {
        return false;
    }

    $tabId = Tab::getIdFromClassName('AdminItrblueboostProductImage');
    if (!$tabId) {
        $tab = new Tab();
        $tab->class_name = 'AdminItrblueboostProductImage';
        $tab->module = $module->name;
        $tab->active = false;
        $tab->id_parent = -1;
        $tab->route_name = 'itrblueboost_admin_product_image_index';
        $tab->wording = 'AI Product Images';
        $tab->wording_domain = 'Modules.Itrblueboost.Admin';

        $languages = Language::getLanguages(false);
        $names = [];
        foreach ($languages as $lang) {
            $names[$lang['id_lang']] = 'AI Product Images';
        }
        $tab->name = $names;

        if (!$tab->add()) {
            return false;
        }
    }

    $modulePath = _PS_MODULE_DIR_ . 'itrblueboost/';
    $uploadsPath = $modulePath . 'uploads/';
    $pendingPath = $uploadsPath . 'pending/';

    if (!is_dir($uploadsPath)) {
        mkdir($uploadsPath, 0755, true);
    }

    if (!is_dir($pendingPath)) {
        mkdir($pendingPath, 0755, true);
    }

    $htaccessContent = "# Security - Deny PHP script execution\n";
    $htaccessContent .= "<FilesMatch \"\\.(php|php3|php4|php5|php7|phtml|pl|py|cgi|asp|jsp)$\">\n";
    $htaccessContent .= "    Require all denied\n";
    $htaccessContent .= "</FilesMatch>\n\n";
    $htaccessContent .= "# Allow only images\n";
    $htaccessContent .= "<FilesMatch \"\\.(jpg|jpeg|png|gif|webp)$\">\n";
    $htaccessContent .= "    Require all granted\n";
    $htaccessContent .= "</FilesMatch>\n";

    file_put_contents($pendingPath . '.htaccess', $htaccessContent);

    $indexContent = "<?php\nheader('Location: ../../../');\nexit;\n";
    file_put_contents($uploadsPath . 'index.php', $indexContent);
    file_put_contents($pendingPath . 'index.php', $indexContent);

    $module->registerHook('actionObjectImageDeleteAfter');

    return true;
}
