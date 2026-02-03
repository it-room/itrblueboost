<?php

declare(strict_types=1);

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Upgrade module to version 1.4.4.
 *
 * - Add rejection reason modal to all_category_faqs page (same as all_product_faqs)
 * - Add API sync to AllCategoryFaqsController (accept, reject, toggle, delete)
 *
 * @param Module $module
 *
 * @return bool
 */
function upgrade_module_1_4_4($module): bool
{
    // This upgrade only contains template and controller fixes, no database changes needed
    return true;
}
