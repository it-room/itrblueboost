<?php

declare(strict_types=1);

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Upgrade to v2.0.2:
 * - Register actionClearCache hook
 * - Add FAQ cache enabled config (default: enabled)
 * - Hide Compatibility tab from admin menu
 *
 * @param Itrblueboost $module
 */
function upgrade_module_2_0_2($module): bool
{
    Configuration::updateValue('ITRBLUEBOOST_FAQ_CACHE_ENABLED', 1);

    // Hide Compatibility tab from admin menu
    $tabId = (int) Tab::getIdFromClassName('AdminItrblueboostCompatibility');
    if ($tabId > 0) {
        $tab = new Tab($tabId);
        $tab->active = false;
        $tab->id_parent = -1;
        $tab->save();
    }

    return $module->registerHook('actionClearCache');
}
