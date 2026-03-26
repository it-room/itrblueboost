<?php

declare(strict_types=1);

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Upgrade to v2.0.3 — Register displayCMSDisputeInformation hook for CMS FAQ display.
 *
 * @param Itrblueboost $module
 */
function upgrade_module_2_0_3($module): bool
{
    return $module->registerHook('displayCMSDisputeInformation');
}
