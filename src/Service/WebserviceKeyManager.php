<?php

declare(strict_types=1);

namespace Itrblueboost\Service;

use Configuration;
use Db;
use Itrblueboost;
use WebserviceKey;

/**
 * Manages webservice key creation and synchronization with the API.
 */
class WebserviceKeyManager
{
    /** @var string Webservice key description */
    private const WS_DESCRIPTION = 'ITR Blue Boost - Auto-generated key';

    /** @var array<int, string> HTTP methods to grant */
    private const WS_METHODS = ['GET', 'POST', 'PUT', 'DELETE', 'HEAD'];

    /**
     * Create or retrieve the webservice key, then sync with API.
     * Never blocks module install on failure.
     *
     * @return bool Always returns true to not block install
     */
    public function createAndSync(): bool
    {
        try {
            $keyId = (int) Configuration::get(Itrblueboost::CONFIG_WEBSERVICE_KEY_ID);

            if ($keyId > 0 && $this->webserviceKeyExists($keyId)) {
                $this->syncWithApi($keyId);
                return true;
            }

            $this->cleanOrphanShopAssociations();

            $keyId = $this->createWebserviceKey();

            if ($keyId <= 0) {
                return true;
            }

            Configuration::updateValue(Itrblueboost::CONFIG_WEBSERVICE_KEY_ID, $keyId);
            $this->syncWithApi($keyId);
        } catch (\Exception $e) {
            // Webservice key creation is non-critical, never block install
        }

        return true;
    }

    /**
     * Check if a webservice key still exists.
     *
     * @param int $keyId Webservice key ID
     *
     * @return bool
     */
    private function webserviceKeyExists(int $keyId): bool
    {
        $sql = 'SELECT id_webservice_account
                FROM `' . _DB_PREFIX_ . 'webservice_account`
                WHERE id_webservice_account = ' . $keyId;

        return (bool) Db::getInstance()->getValue($sql);
    }

    /**
     * Remove orphan webservice_account_shop rows that no longer have
     * a matching webservice_account entry (left by previous partial installs).
     *
     * @return void
     */
    private function cleanOrphanShopAssociations(): void
    {
        Db::getInstance()->execute(
            'DELETE ws FROM `' . _DB_PREFIX_ . 'webservice_account_shop` ws
             LEFT JOIN `' . _DB_PREFIX_ . 'webservice_account` wa
               ON wa.id_webservice_account = ws.id_webservice_account
             WHERE wa.id_webservice_account IS NULL'
        );
    }

    /**
     * Create a new webservice key with all permissions.
     *
     * @return int Created key ID or 0 on failure
     */
    private function createWebserviceKey(): int
    {
        $key = $this->generateKey();

        $wsKey = new WebserviceKey();
        $wsKey->key = $key;
        $wsKey->active = true;
        $wsKey->description = self::WS_DESCRIPTION;

        if (!$wsKey->add()) {
            return 0;
        }

        $keyId = (int) $wsKey->id;

        $this->grantAllPermissions($keyId);
        $this->associateAllShops($keyId);

        return $keyId;
    }

    /**
     * Grant all permissions on all resources.
     *
     * @param int $keyId Webservice key ID
     */
    private function grantAllPermissions(int $keyId): void
    {
        $resources = $this->getAvailableResources();

        if (empty($resources)) {
            return;
        }

        $db = Db::getInstance();

        foreach ($resources as $resource) {
            foreach (self::WS_METHODS as $method) {
                $db->insert('webservice_permission', [
                    'resource' => pSQL($resource),
                    'method' => pSQL($method),
                    'id_webservice_account' => $keyId,
                ]);
            }
        }
    }

    /**
     * Associate webservice key with all shops.
     *
     * @param int $keyId Webservice key ID
     */
    private function associateAllShops(int $keyId): void
    {
        $shops = \Shop::getShops(true, null, true);

        if (empty($shops)) {
            return;
        }

        $db = Db::getInstance();

        foreach ($shops as $idShop) {
            $db->execute(
                'INSERT IGNORE INTO `' . _DB_PREFIX_ . 'webservice_account_shop`
                 (`id_webservice_account`, `id_shop`) VALUES (' . (int) $keyId . ', ' . (int) $idShop . ')'
            );
        }
    }

    /**
     * Get all available webservice resources.
     *
     * @return array<int, string>
     */
    private function getAvailableResources(): array
    {
        $resources = [];

        if (class_exists('WebserviceRequest')) {
            try {
                $available = \WebserviceRequest::getResources();

                if (is_array($available)) {
                    $resources = array_keys($available);
                }
            } catch (\Exception $e) {
                // Fallback to core resources
                $resources = $this->getCoreResources();
            }
        } else {
            $resources = $this->getCoreResources();
        }

        return $resources;
    }

    /**
     * Fallback list of core PrestaShop webservice resources.
     *
     * @return array<int, string>
     */
    private function getCoreResources(): array
    {
        return [
            'addresses', 'carriers', 'cart_rules', 'carts', 'categories',
            'combinations', 'configurations', 'contacts', 'content_management_system',
            'countries', 'currencies', 'customer_messages', 'customer_threads',
            'customers', 'customizations', 'deliveries', 'employees', 'groups',
            'guests', 'image_types', 'images', 'languages', 'manufacturers',
            'messages', 'order_carriers', 'order_details', 'order_histories',
            'order_invoices', 'order_payments', 'order_slip', 'order_states',
            'orders', 'price_ranges', 'product_customization_fields',
            'product_feature_values', 'product_features', 'product_option_values',
            'product_options', 'product_suppliers', 'products', 'search',
            'shop_groups', 'shop_urls', 'shops', 'specific_price_rules',
            'specific_prices', 'states', 'stock_availables', 'stock_movement_reasons',
            'stock_movements', 'stocks', 'stores', 'suppliers', 'supply_order_details',
            'supply_order_histories', 'supply_order_receipt_histories',
            'supply_order_states', 'supply_orders', 'tags', 'tax_rule_groups',
            'tax_rules', 'taxes', 'translated_configurations', 'warehouse_product_locations',
            'warehouses', 'weight_ranges', 'zones',
        ];
    }

    /**
     * Sync the webservice key with the ITROOM API.
     *
     * @param int $keyId Webservice key ID
     *
     * @return bool
     */
    private function syncWithApi(int $keyId): bool
    {
        $apiKey = Configuration::get(Itrblueboost::CONFIG_API_KEY);

        if (empty($apiKey)) {
            return true;
        }

        $wsKeyValue = $this->getKeyValue($keyId);

        if (empty($wsKeyValue)) {
            return false;
        }

        $apiService = new ApiService();
        $response = $apiService->callApi(
            $apiKey,
            'webservice',
            'PUT',
            [
                'webservice_key' => $wsKeyValue,
                'platform' => 'prestashop',
            ]
        );

        return $response !== null && !empty($response['success']);
    }

    /**
     * Get the actual key string from a webservice key ID.
     *
     * @param int $keyId Webservice key ID
     *
     * @return string
     */
    private function getKeyValue(int $keyId): string
    {
        $sql = 'SELECT `key`
                FROM `' . _DB_PREFIX_ . 'webservice_account`
                WHERE id_webservice_account = ' . $keyId;

        $result = Db::getInstance()->getValue($sql);

        return $result ? (string) $result : '';
    }

    /**
     * Generate a random 32-character webservice key.
     *
     * @return string
     */
    private function generateKey(): string
    {
        $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $key = '';
        $max = strlen($characters) - 1;

        for ($i = 0; $i < 32; $i++) {
            $key .= $characters[mt_rand(0, $max)];
        }

        return $key;
    }

    /**
     * Delete the webservice key (for uninstall).
     *
     * @return bool
     */
    public function deleteKey(): bool
    {
        $keyId = (int) Configuration::get(Itrblueboost::CONFIG_WEBSERVICE_KEY_ID);

        if ($keyId <= 0) {
            return true;
        }

        $wsKey = new WebserviceKey($keyId);

        if (!$wsKey->id) {
            Configuration::deleteByName(Itrblueboost::CONFIG_WEBSERVICE_KEY_ID);

            return true;
        }

        $result = $wsKey->delete();
        Configuration::deleteByName(Itrblueboost::CONFIG_WEBSERVICE_KEY_ID);

        return $result;
    }
}
