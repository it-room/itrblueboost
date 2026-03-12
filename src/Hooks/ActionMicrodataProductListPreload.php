<?php

declare(strict_types=1);

namespace Itrblueboost\Hooks;

use Configuration;
use Context;
use Db;
use Itrblueboost;

/**
 * Hook handler for actionMicrodataProductListPreload (itrmicrodata module).
 *
 * Preloads AI-generated product content in batch before the product loop
 * to avoid N+1 queries in actionMicrodataProductList.
 */
class ActionMicrodataProductListPreload
{
    /** @var Itrblueboost */
    private $module;

    /** @var array<int, array<string, string>> Preloaded content indexed by product ID */
    private static $preloadedContent = [];

    public function __construct(Itrblueboost $module)
    {
        $this->module = $module;
    }

    /**
     * Execute the hook logic.
     *
     * @param array<string, mixed> $params Hook parameters containing 'productIds'
     */
    public function execute(array $params): void
    {
        $contentServiceActive = (bool) Configuration::get(Itrblueboost::CONFIG_SERVICE_CONTENT);

        if (!$contentServiceActive) {
            return;
        }

        $productIds = $params['productIds'] ?? [];

        if (empty($productIds) || !is_array($productIds)) {
            return;
        }

        $context = Context::getContext();
        $idLang = (int) $context->language->id;
        $idShop = (int) $context->shop->id;

        $this->preloadContent($productIds, $idLang, $idShop);
    }

    /**
     * Preload accepted content for multiple products in a single query.
     *
     * @param array<int, int> $productIds Product IDs
     * @param int $idLang Language ID
     * @param int $idShop Shop ID
     */
    private function preloadContent(array $productIds, int $idLang, int $idShop): void
    {
        $sanitizedIds = array_map('intval', $productIds);
        $sanitizedIds = array_filter($sanitizedIds, function ($id) {
            return $id > 0;
        });

        if (empty($sanitizedIds)) {
            return;
        }

        $sql = 'SELECT c.id_product, cl.generated_content
                FROM `' . _DB_PREFIX_ . 'itrblueboost_product_content` c
                INNER JOIN `' . _DB_PREFIX_ . 'itrblueboost_product_content_lang` cl
                    ON c.id_itrblueboost_product_content = cl.id_itrblueboost_product_content
                    AND cl.id_lang = ' . $idLang . '
                INNER JOIN `' . _DB_PREFIX_ . 'itrblueboost_product_content_shop` cs
                    ON c.id_itrblueboost_product_content = cs.id_itrblueboost_product_content
                    AND cs.id_shop = ' . $idShop . '
                WHERE c.id_product IN (' . implode(',', $sanitizedIds) . ')
                    AND c.active = 1
                    AND c.status = \'accepted\'
                ORDER BY c.date_add DESC';

        $rows = Db::getInstance()->executeS($sql);

        if (empty($rows)) {
            return;
        }

        foreach ($rows as $row) {
            $idProduct = (int) $row['id_product'];

            if (isset(self::$preloadedContent[$idProduct])) {
                continue;
            }

            if (!empty($row['generated_content'])) {
                self::$preloadedContent[$idProduct] = [
                    'description' => strip_tags((string) $row['generated_content']),
                ];
            }
        }
    }

    /**
     * Get preloaded content for a product.
     *
     * @param int $idProduct Product ID
     *
     * @return array<string, string>
     */
    public static function getPreloadedContent(int $idProduct): array
    {
        return self::$preloadedContent[$idProduct] ?? [];
    }

    /**
     * Reset preloaded cache (for testing).
     */
    public static function resetCache(): void
    {
        self::$preloadedContent = [];
    }
}
