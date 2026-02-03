<?php

declare(strict_types=1);

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Upgrade to version 1.2.1
 * - Register hook actionObjectImageDeleteAfter
 *
 * @param Itrblueboost $module Module instance
 *
 * @return bool True on success
 */
function upgrade_module_1_2_1(Itrblueboost $module): bool
{
    return $module->registerHook('actionObjectImageDeleteAfter');
}
