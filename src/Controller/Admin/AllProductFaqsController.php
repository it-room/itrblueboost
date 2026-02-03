<?php

declare(strict_types=1);

namespace Itrblueboost\Controller\Admin;

use Context;
use Db;
use Itrblueboost\Entity\ProductFaq;
use PrestaShopBundle\Controller\Admin\FrameworkBundleAdminController;
use PrestaShopBundle\Security\Annotation\AdminSecurity;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Controller for listing all product FAQs.
 */
class AllProductFaqsController extends FrameworkBundleAdminController
{
    /**
     * @AdminSecurity("is_granted('read', request.get('_legacy_controller'))")
     */
    public function indexAction(Request $request): Response
    {
        $page = max(1, (int) $request->query->get('page', 1));
        $limit = 20;
        $offset = ($page - 1) * $limit;

        $statusFilter = $request->query->get('status', '');

        $idLang = (int) Context::getContext()->language->id;
        $idShop = (int) Context::getContext()->shop->id;

        $whereClause = ' WHERE 1=1';
        if ($statusFilter === 'active') {
            $whereClause .= ' AND pf.active = 1';
        } elseif ($statusFilter === 'inactive') {
            $whereClause .= ' AND pf.active = 0';
        }

        // Get total count
        $totalQuery = 'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'itrblueboost_product_faq` pf' . $whereClause;
        $totalFaqs = (int) Db::getInstance()->getValue($totalQuery);

        // Get FAQs with product info (including reference)
        $sql = 'SELECT pf.*, pfl.question, pfl.answer, p.id_product, p.reference as product_reference, pl.name as product_name
                FROM `' . _DB_PREFIX_ . 'itrblueboost_product_faq` pf
                LEFT JOIN `' . _DB_PREFIX_ . 'itrblueboost_product_faq_lang` pfl
                    ON pfl.id_itrblueboost_product_faq = pf.id_itrblueboost_product_faq
                    AND pfl.id_lang = ' . $idLang . '
                LEFT JOIN `' . _DB_PREFIX_ . 'product` p ON p.id_product = pf.id_product
                LEFT JOIN `' . _DB_PREFIX_ . 'product_lang` pl ON pl.id_product = p.id_product
                    AND pl.id_lang = ' . $idLang . '
                    AND pl.id_shop = ' . $idShop . '
                ' . $whereClause . '
                ORDER BY pf.date_add DESC
                LIMIT ' . (int) $offset . ', ' . (int) $limit;

        $faqs = Db::getInstance()->executeS($sql) ?: [];

        $totalPages = (int) ceil($totalFaqs / $limit);

        return $this->render('@Modules/itrblueboost/views/templates/admin/all_product_faqs/index.html.twig', [
            'faqs' => $faqs,
            'currentPage' => $page,
            'totalPages' => $totalPages,
            'totalFaqs' => $totalFaqs,
            'statusFilter' => $statusFilter,
            'layoutTitle' => $this->trans('All Product FAQs', 'Modules.Itrblueboost.Admin'),
        ]);
    }

    /**
     * @AdminSecurity("is_granted('update', request.get('_legacy_controller'))")
     */
    public function toggleActiveAction(Request $request, int $faqId): JsonResponse
    {
        $faq = new ProductFaq($faqId);

        if (!$faq->id) {
            return new JsonResponse([
                'success' => false,
                'message' => 'FAQ not found.',
            ]);
        }

        $faq->active = !$faq->active;

        if (!$faq->update()) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Error updating FAQ.',
            ]);
        }

        return new JsonResponse([
            'success' => true,
            'message' => 'FAQ status updated.',
            'active' => (bool) $faq->active,
        ]);
    }

    /**
     * @AdminSecurity("is_granted('delete', request.get('_legacy_controller'))")
     */
    public function deleteAction(Request $request, int $faqId): JsonResponse
    {
        $faq = new ProductFaq($faqId);

        if (!$faq->id) {
            return new JsonResponse([
                'success' => false,
                'message' => 'FAQ not found.',
            ]);
        }

        if (!$faq->delete()) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Error deleting FAQ.',
            ]);
        }

        return new JsonResponse([
            'success' => true,
            'message' => 'FAQ deleted.',
        ]);
    }
}
