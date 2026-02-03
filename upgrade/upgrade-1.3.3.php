<?php

/**
 * Upgrade script for version 1.3.3.
 *
 * Updates credits badge position in admin header.
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
function upgrade_module_1_3_3($module): bool
{
    // Ensure hook is registered
    if (!$module->isRegisteredInHook('displayBackOfficeHeader')) {
        $module->registerHook('displayBackOfficeHeader');
    }

    return true;
}
