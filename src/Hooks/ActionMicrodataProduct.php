<?php

declare(strict_types=1);

namespace Itrblueboost\Hooks;

use Configuration;
use Context;
use Itrblueboost;
use Itrblueboost\Entity\ProductContent;

/**
 * Hook handler for actionMicrodataProduct (itrmicrodata module).
 *
 * Provides AI-generated product description to itrmicrodata module
 * for inclusion in Product JSON-LD structured data.
 */
class ActionMicrodataProduct
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

        $context = Context::getContext();

        return $this->getContentOverrides(
            $idProduct,
            (int) $context->language->id,
            (int) $context->shop->id
        );
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
        if (is_object($product) && isset($product->id)) {
            return (int) $product->id;
        }

        if (is_object($product) && isset($product->id_product)) {
            return (int) $product->id_product;
        }

        if (is_array($product) && !empty($product['id_product'])) {
            return (int) $product['id_product'];
        }

        if (is_array($product) && !empty($product['id'])) {
            return (int) $product['id'];
        }

        return 0;
    }

    /**
     * Get accepted content overrides for a product.
     *
     * @param int $idProduct Product ID
     * @param int $idLang Language ID
     * @param int $idShop Shop ID
     *
     * @return array<string, string>
     */
    private function getContentOverrides(int $idProduct, int $idLang, int $idShop): array
    {
        $contents = ProductContent::getByProduct($idProduct, $idLang, $idShop, true);

        if (empty($contents)) {
            return [];
        }

        $result = [];
        $content = $contents[0];

        if (!empty($content['generated_content'])) {
            $result['description'] = strip_tags((string) $content['generated_content']);
        }

        return $result;
    }
}
