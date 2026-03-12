<?php

declare(strict_types=1);

namespace Itrblueboost\Hooks;

use Configuration;
use Itrblueboost;

/**
 * Hook handler for actionMicrodataProductList (itrmicrodata module).
 *
 * Provides AI-generated product description to itrmicrodata module
 * for product listing JSON-LD structured data.
 * Uses preloaded cache from ActionMicrodataProductListPreload.
 */
class ActionMicrodataProductList
{
    /** @var Itrblueboost */
    private $module;

    public function __construct(Itrblueboost $module)
    {
        $this->module = $module;
    }

    /**
     * Execute the hook logic.
     *
     * @param array<string, mixed> $params Hook parameters containing 'product'
     *
     * @return array<string, string> Overrides for microdata (description)
     */
    public function execute(array $params): array
    {
        $contentServiceActive = (bool) Configuration::get(Itrblueboost::CONFIG_SERVICE_CONTENT);

        if (!$contentServiceActive) {
            return [];
        }

        $idProduct = $this->extractProductId($params['product'] ?? null);

        if ($idProduct <= 0) {
            return [];
        }

        return ActionMicrodataProductListPreload::getPreloadedContent($idProduct);
    }

    /**
     * Extract product ID from product data.
     *
     * @param mixed $product Product object or array
     *
     * @return int
     */
    private function extractProductId($product): int
    {
        if (is_array($product) && !empty($product['id_product'])) {
            return (int) $product['id_product'];
        }

        if (is_array($product) && !empty($product['id'])) {
            return (int) $product['id'];
        }

        if (is_object($product) && isset($product->id_product)) {
            return (int) $product->id_product;
        }

        if (is_object($product) && isset($product->id)) {
            return (int) $product->id;
        }

        return 0;
    }
}
