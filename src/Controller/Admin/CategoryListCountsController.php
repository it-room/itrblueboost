<?php

declare(strict_types=1);

namespace Itrblueboost\Controller\Admin;

use Db;
use PrestaShopBundle\Controller\Admin\FrameworkBundleAdminController;
use PrestaShopBundle\Security\Annotation\AdminSecurity;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Controller returning FAQ counts for category listing.
 */
class CategoryListCountsController extends FrameworkBundleAdminController
{
    /**
     * Return counts of FAQ for a set of category IDs.
     *
     * @AdminSecurity("is_granted('read', request.get('_legacy_controller'))")
     */
    public function countsAction(Request $request): JsonResponse
    {
        $rawIds = $request->request->get('category_ids', '');

        if (empty($rawIds)) {
            return new JsonResponse(['success' => false]);
        }

        $categoryIds = $this->sanitizeCategoryIds((string) $rawIds);

        if (empty($categoryIds)) {
            return new JsonResponse(['success' => false]);
        }

        $idShop = (int) \Context::getContext()->shop->id;
        $idList = implode(',', $categoryIds);

        $faqCounts = $this->fetchFaqCounts($idList, $idShop);
        $counts = $this->buildCounts($categoryIds, $faqCounts);

        return new JsonResponse([
            'success' => true,
            'counts' => $counts,
        ]);
    }

    /**
     * Sanitize and filter category IDs from the request.
     *
     * @param string $rawIds Comma-separated category IDs
     *
     * @return array<int>
     */
    private function sanitizeCategoryIds(string $rawIds): array
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
     * Fetch FAQ counts for categories.
     *
     * @param string $idList Comma-separated category IDs
     * @param int $idShop Shop ID
     *
     * @return array<int, int>
     */
    private function fetchFaqCounts(string $idList, int $idShop): array
    {
        $sql = 'SELECT f.id_category, COUNT(*) AS cnt
                FROM `' . _DB_PREFIX_ . 'itrblueboost_category_faq` f
                INNER JOIN `' . _DB_PREFIX_ . 'itrblueboost_category_faq_shop` fs
                    ON f.id_itrblueboost_category_faq = fs.id_itrblueboost_category_faq
                    AND fs.id_shop = ' . $idShop . '
                WHERE f.id_category IN (' . $idList . ')
                GROUP BY f.id_category';

        $results = Db::getInstance()->executeS($sql);
        $map = [];

        if (is_array($results)) {
            foreach ($results as $row) {
                $map[(int) $row['id_category']] = (int) $row['cnt'];
            }
        }

        return $map;
    }

    /**
     * Build counts structure keyed by category ID.
     *
     * @param array<int> $categoryIds Category IDs
     * @param array<int, int> $faqCounts FAQ counts
     *
     * @return array<int, array<string, int>>
     */
    private function buildCounts(array $categoryIds, array $faqCounts): array
    {
        $counts = [];

        foreach ($categoryIds as $id) {
            $faq = $faqCounts[$id] ?? 0;

            if ($faq > 0) {
                $counts[$id] = [
                    'faq' => $faq,
                ];
            }
        }

        return $counts;
    }
}
