<?php

declare(strict_types=1);

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Upgrade to version 1.2.2
 * - Add service status configuration keys
 *
 * @param Itrblueboost $module Module instance
 *
 * @return bool True on success
 */
function upgrade_module_1_2_2(Itrblueboost $module): bool
{
    Configuration::updateValue(Itrblueboost::CONFIG_SERVICE_FAQ, 0);
    Configuration::updateValue(Itrblueboost::CONFIG_SERVICE_IMAGE, 0);

    return true;
}
