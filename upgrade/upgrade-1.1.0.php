<?php

declare(strict_types=1);

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Upgrade vers la version 1.1.0
 * - Supprime le hook displayAdminProductsExtra (onglet module)
 * - Ajoute le hook actionAdminControllerSetMedia (bouton footer)
 *
 * @param Itrblueboost $module Instance du module
 *
 * @return bool True si succès
 */
function upgrade_module_1_1_0(Itrblueboost $module): bool
{
    // Désenregistrer les anciens hooks
    $module->unregisterHook('displayAdminProductsExtra');
    $module->unregisterHook('displayBackOfficeHeader');

    // Enregistrer le nouveau hook
    return $module->registerHook('actionAdminControllerSetMedia');
}
