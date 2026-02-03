<?php

declare(strict_types=1);

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Upgrade module to version 1.4.2.
 *
 * Fix: Rejection reason modal not working on all-product-faqs page (PS 1.7 compatibility)
 *
 * @param Module $module
 *
 * @return bool
 */
function upgrade_module_1_4_2($module): bool
{
    // This upgrade only contains template fixes, no database changes needed
    return true;
}
