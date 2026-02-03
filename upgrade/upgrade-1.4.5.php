<?php

declare(strict_types=1);

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Upgrade module to version 1.4.5.
 *
 * - Improve API error handling to capture detailed error messages on HTTP errors
 *
 * @param Module $module
 *
 * @return bool
 */
function upgrade_module_1_4_5($module): bool
{
    // This upgrade only contains controller fixes, no database changes needed
    return true;
}
