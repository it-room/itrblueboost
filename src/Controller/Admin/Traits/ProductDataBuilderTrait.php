<?php

declare(strict_types=1);

namespace Itrblueboost\Controller\Admin\Traits;

use Product;

/**
 * Trait for building structured product data for API calls.
 */
trait ProductDataBuilderTrait
{
    /**
     * Build structured product data for API.
     *
     * @param Product $product Product instance
     * @param int $idLang Language ID
     *
     * @return array<string, mixed>
     */
    private function buildProductData(Product $product, int $idLang): array
    {
        $brandName = $this->getProductBrandName($product, $idLang);
        $categoryName = $this->getProductCategoryName($product, $idLang);
        $features = $this->getProductFeatures($product, $idLang);
        $combinations = $this->getProductCombinations($product, $idLang);

        return [
            'name' => $product->name,
            'description' => strip_tags($product->description ?? ''),
            'description_short' => strip_tags($product->description_short ?? ''),
            'brand' => $brandName,
            'category' => $categoryName,
            'reference' => $product->reference ?? '',
            'features' => $features,
            'combinations' => $combinations,
            'price' => (float) $product->getPrice(true, null, 2),
            'url' => $product->getLink(),
        ];
    }

    /**
     * @param Product $product
     * @param int $idLang
     *
     * @return string
     */
    private function getProductBrandName(Product $product, int $idLang): string
    {
        if ($product->id_manufacturer <= 0) {
            return '';
        }

        $manufacturer = new \Manufacturer($product->id_manufacturer, $idLang);

        return $manufacturer->id ? $manufacturer->name : '';
    }

    /**
     * @param Product $product
     * @param int $idLang
     *
     * @return string
     */
    private function getProductCategoryName(Product $product, int $idLang): string
    {
        if ($product->id_category_default <= 0) {
            return '';
        }

        $category = new \Category($product->id_category_default, $idLang);

        return $category->id ? $category->name : '';
    }

    /**
     * @param Product $product
     * @param int $idLang
     *
     * @return array<int, array{name: string, value: string}>
     */
    private function getProductFeatures(Product $product, int $idLang): array
    {
        $features = [];
        $productFeatures = $product->getFrontFeatures($idLang);

        if (empty($productFeatures)) {
            return $features;
        }

        foreach ($productFeatures as $feature) {
            $features[] = [
                'name' => $feature['name'],
                'value' => $feature['value'],
            ];
        }

        return $features;
    }

    /**
     * @param Product $product
     * @param int $idLang
     *
     * @return array<int, array<string, mixed>>
     */
    private function getProductCombinations(Product $product, int $idLang): array
    {
        if (!$product->hasAttributes()) {
            return [];
        }

        $rawCombinations = $product->getAttributeCombinations($idLang);
        $groupedCombinations = [];

        foreach ($rawCombinations as $combination) {
            $idCombination = (int) $combination['id_product_attribute'];

            if (!isset($groupedCombinations[$idCombination])) {
                $groupedCombinations[$idCombination] = [
                    'reference' => $combination['reference'] ?? '',
                    'price_impact' => (float) ($combination['price'] ?? 0),
                    'attributes' => [],
                ];
            }

            $groupedCombinations[$idCombination]['attributes'][] = [
                'group' => $combination['group_name'],
                'value' => $combination['attribute_name'],
            ];
        }

        return array_values($groupedCombinations);
    }
}
