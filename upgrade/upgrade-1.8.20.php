<?php

declare(strict_types=1);

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Upgrade to 1.8.20 - Create webservice key and sync with API.
 *
 * @param Itrblueboost $module
 *
 * @return bool
 */
function upgrade_module_1_8_20($module): bool
{
    $manager = new \Itrblueboost\Service\WebserviceKeyManager();

    return $manager->createAndSync();
}
