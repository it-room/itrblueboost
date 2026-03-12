<?php

declare(strict_types=1);

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Upgrade to 1.8.19 - Add microdata hooks integration and FAQPage JSON-LD.
 *
 * @param Itrblueboost $module
 *
 * @return bool
 */
function upgrade_module_1_8_19($module): bool
{
    return $module->registerHook('actionMicrodataProduct')
        && $module->registerHook('actionMicrodataProductList')
        && $module->registerHook('actionMicrodataProductListPreload');
}
