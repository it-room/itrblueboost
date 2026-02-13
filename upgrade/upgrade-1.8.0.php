<?php

declare(strict_types=1);

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Upgrade to 1.8.0 - Add Compatibility tab and bootstrap version config.
 *
 * @param Itrblueboost $module
 *
 * @return bool
 */
function upgrade_module_1_8_0($module): bool
{
    // Install default configs
    Configuration::updateValue('ITRBLUEBOOST_BOOTSTRAP_VERSION', 'bootstrap5');
    Configuration::updateValue('ITRBLUEBOOST_API_MODE', 'prod');

    // Install the Compatibility tab
    $tabId = Tab::getIdFromClassName('AdminItrblueboostCompatibility');

    if ($tabId) {
        return true;
    }

    $parentTabId = (int) Tab::getIdFromClassName('AdminItrblueboostParent');

    if (!$parentTabId) {
        return false;
    }

    $tab = new Tab();
    $tab->class_name = 'AdminItrblueboostCompatibility';
    $tab->module = $module->name;
    $tab->active = true;
    $tab->id_parent = $parentTabId;
    $tab->route_name = 'itrblueboost_compatibility';
    $tab->wording = 'Compatibility';
    $tab->wording_domain = 'Modules.Itrblueboost.Admin';
    $tab->icon = 'settings_suggest';

    $languages = Language::getLanguages(false);
    $names = [];

    foreach ($languages as $language) {
        $names[(int) $language['id_lang']] = 'Compatibilité';
    }

    $tab->name = $names;

    return $tab->add();
}
