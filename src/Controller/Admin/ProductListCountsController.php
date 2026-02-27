<?php

declare(strict_types=1);

namespace Itrblueboost\Controller\Admin;

use Db;
use PrestaShopBundle\Controller\Admin\FrameworkBundleAdminController;
use PrestaShopBundle\Security\Annotation\AdminSecurity;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Controller returning FAQ, Image and Content counts for product listing badges.
 */
class ProductListCountsController extends FrameworkBundleAdminController
{
    /**
     * Return counts of FAQ, Images and Content for a set of product IDs.
     *
     * @AdminSecurity("is_granted('read', request.get('_legacy_controller'))")
     */
    public function countsAction(Request $request): JsonResponse
    {
        $rawIds = $request->request->get('product_ids', '');

        if (empty($rawIds)) {
            return new JsonResponse(['success' => false]);
        }

        $productIds = $this->sanitizeProductIds((string) $rawIds);

        if (empty($productIds)) {
            return new JsonResponse(['success' => false]);
        }

        $idShop = (int) \Context::getContext()->shop->id;
        $idList = implode(',', $productIds);

        $faqCounts = $this->fetchFaqCounts($idList, $idShop);
        $imageCounts = $this->fetchImageCounts($idList, $idShop);
        $contentCounts = $this->fetchContentCounts($idList, $idShop);

        $counts = $this->mergeCounts($productIds, $faqCounts, $imageCounts, $contentCounts);

        return new JsonResponse([
            'success' => true,
            'counts' => $counts,
        ]);
    }

    /**
     * Sanitize and filter product IDs from the request.
     *
     * @param string $rawIds Comma-separated product IDs
     *
     * @return array<int>
     */
    private function sanitizeProductIds(string $rawIds): array
    {
        $ids = [];

        foreach (explode(',', $rawIds) as $raw) {
            $id = (int) trim($raw);

            if ($id > 0) {
                $ids[] = $id;
            }
        }

        return array_unique($ids);
    }

    /**
     * Fetch FAQ counts for products.
     *
     * @param string $idList Comma-separated product IDs
     * @param int $idShop Shop ID
     *
     * @return array<int, int>
     */
    private function fetchFaqCounts(string $idList, int $idShop): array
    {
        $sql = 'SELECT f.id_product, COUNT(*) AS cnt
                FROM `' . _DB_PREFIX_ . 'itrblueboost_product_faq` f
                INNER JOIN `' . _DB_PREFIX_ . 'itrblueboost_product_faq_shop` fs
                    ON f.id_itrblueboost_product_faq = fs.id_itrblueboost_product_faq
                    AND fs.id_shop = ' . $idShop . '
                WHERE f.id_product IN (' . $idList . ')
                GROUP BY f.id_product';

        return $this->queryCountMap($sql);
    }

    /**
     * Fetch Image counts for products.
     *
     * @param string $idList Comma-separated product IDs
     * @param int $idShop Shop ID
     *
     * @return array<int, int>
     */
    private function fetchImageCounts(string $idList, int $idShop): array
    {
        $sql = 'SELECT i.id_product, COUNT(*) AS cnt
                FROM `' . _DB_PREFIX_ . 'itrblueboost_product_image` i
                INNER JOIN `' . _DB_PREFIX_ . 'itrblueboost_product_image_shop` ish
                    ON i.id_itrblueboost_product_image = ish.id_itrblueboost_product_image
                    AND ish.id_shop = ' . $idShop . '
                WHERE i.id_product IN (' . $idList . ')
                GROUP BY i.id_product';

        return $this->queryCountMap($sql);
    }

    /**
     * Fetch Content counts for products.
     *
     * @param string $idList Comma-separated product IDs
     * @param int $idShop Shop ID
     *
     * @return array<int, int>
     */
    private function fetchContentCounts(string $idList, int $idShop): array
    {
        $sql = 'SELECT c.id_product, COUNT(*) AS cnt
                FROM `' . _DB_PREFIX_ . 'itrblueboost_product_content` c
                INNER JOIN `' . _DB_PREFIX_ . 'itrblueboost_product_content_shop` cs
                    ON c.id_itrblueboost_product_content = cs.id_itrblueboost_product_content
                    AND cs.id_shop = ' . $idShop . '
                WHERE c.id_product IN (' . $idList . ')
                GROUP BY c.id_product';

        return $this->queryCountMap($sql);
    }

    /**
     * Execute a count query and return a map of id_product => count.
     *
     * @param string $sql SQL query
     *
     * @return array<int, int>
     */
    private function queryCountMap(string $sql): array
    {
        $results = Db::getInstance()->executeS($sql);
        $map = [];

        if (is_array($results)) {
            foreach ($results as $row) {
                $map[(int) $row['id_product']] = (int) $row['cnt'];
            }
        }

        return $map;
    }

    /**
     * Merge all count maps into a single structure keyed by product ID.
     *
     * @param array<int> $productIds Product IDs
     * @param array<int, int> $faqCounts FAQ counts
     * @param array<int, int> $imageCounts Image counts
     * @param array<int, int> $contentCounts Content counts
     *
     * @return array<int, array<string, int>>
     */
    private function mergeCounts(
        array $productIds,
        array $faqCounts,
        array $imageCounts,
        array $contentCounts
    ): array {
        $counts = [];

        foreach ($productIds as $id) {
            $faq = $faqCounts[$id] ?? 0;
            $images = $imageCounts[$id] ?? 0;
            $content = $contentCounts[$id] ?? 0;

            if ($faq > 0 || $images > 0 || $content > 0) {
                $counts[$id] = [
                    'faq' => $faq,
                    'images' => $images,
                    'content' => $content,
                ];
            }
        }

        return $counts;
    }
}
