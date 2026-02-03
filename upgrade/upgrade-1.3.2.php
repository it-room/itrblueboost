<?php

/**
 * Upgrade script for version 1.3.2.
 *
 * Adds credits display in admin header.
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
function upgrade_module_1_3_2($module): bool
{
    // Register hook for credits display in header
    $module->registerHook('displayBackOfficeHeader');

    return true;
}
