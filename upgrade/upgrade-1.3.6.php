<?php

/**
 * Upgrade script for version 1.3.6.
 *
 * Adds displayFooterCategory hook for category FAQ display in front-office.
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
function upgrade_module_1_3_6($module): bool
{
    // Unregister old hook
    $module->unregisterHook('filterCategoryContent');

    // Register new hook for category footer
    if (!$module->isRegisteredInHook('displayFooterCategory')) {
        $module->registerHook('displayFooterCategory');
    }

    return true;
}
