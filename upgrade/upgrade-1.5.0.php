<?php

/**
 * Upgrade to 1.5.0 - Store credits in Configuration instead of API call on each page load.
 *
 * @param Itrblueboost $module
 *
 * @return bool
 */
function upgrade_module_1_5_0($module)
{
    Configuration::updateValue('ITRBLUEBOOST_CREDITS_REMAINING', '');

    return true;
}
