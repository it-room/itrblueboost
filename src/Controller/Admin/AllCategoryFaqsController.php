<?php

declare(strict_types=1);

namespace Itrblueboost\Controller\Admin;

use Context;
use Db;
use Itrblueboost\Entity\CategoryFaq;
use PrestaShopBundle\Controller\Admin\FrameworkBundleAdminController;
use PrestaShopBundle\Security\Annotation\AdminSecurity;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Controller for listing all category FAQs.
 */
class AllCategoryFaqsController extends FrameworkBundleAdminController
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
        if ($statusFilter && in_array($statusFilter, ['pending', 'accepted', 'rejected'], true)) {
            $whereClause .= ' AND cf.status = "' . pSQL($statusFilter) . '"';
        }

        // Get total count
        $totalQuery = 'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'itrblueboost_category_faq` cf' . $whereClause;
        $totalFaqs = (int) Db::getInstance()->getValue($totalQuery);

        // Get FAQs with category info
        $sql = 'SELECT cf.*, cfl.question, cfl.answer, c.id_category, cl.name as category_name
                FROM `' . _DB_PREFIX_ . 'itrblueboost_category_faq` cf
                LEFT JOIN `' . _DB_PREFIX_ . 'itrblueboost_category_faq_lang` cfl
                    ON cfl.id_itrblueboost_category_faq = cf.id_itrblueboost_category_faq
                    AND cfl.id_lang = ' . $idLang . '
                LEFT JOIN `' . _DB_PREFIX_ . 'category` c ON c.id_category = cf.id_category
                LEFT JOIN `' . _DB_PREFIX_ . 'category_lang` cl ON cl.id_category = c.id_category
                    AND cl.id_lang = ' . $idLang . '
                    AND cl.id_shop = ' . $idShop . '
                ' . $whereClause . '
                ORDER BY cf.date_add DESC
                LIMIT ' . (int) $offset . ', ' . (int) $limit;

        $faqs = Db::getInstance()->executeS($sql) ?: [];

        $totalPages = (int) ceil($totalFaqs / $limit);

        return $this->render('@Modules/itrblueboost/views/templates/admin/all_category_faqs/index.html.twig', [
            'faqs' => $faqs,
            'currentPage' => $page,
            'totalPages' => $totalPages,
            'totalFaqs' => $totalFaqs,
            'statusFilter' => $statusFilter,
            'layoutTitle' => $this->trans('All Category FAQs', 'Modules.Itrblueboost.Admin'),
        ]);
    }

    /**
     * @AdminSecurity("is_granted('update', request.get('_legacy_controller'))")
     */
    public function acceptAction(Request $request, int $faqId): JsonResponse
    {
        $faq = new CategoryFaq($faqId);

        if (!$faq->id) {
            return new JsonResponse([
                'success' => false,
                'message' => 'FAQ not found.',
            ]);
        }

        $faq->status = 'accepted';
        $faq->active = true;

        if (!$faq->update()) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Error updating FAQ.',
            ]);
        }

        return new JsonResponse([
            'success' => true,
            'message' => 'FAQ accepted.',
        ]);
    }

    /**
     * @AdminSecurity("is_granted('update', request.get('_legacy_controller'))")
     */
    public function rejectAction(Request $request, int $faqId): JsonResponse
    {
        $faq = new CategoryFaq($faqId);

        if (!$faq->id) {
            return new JsonResponse([
                'success' => false,
                'message' => 'FAQ not found.',
            ]);
        }

        $faq->status = 'rejected';
        $faq->active = false;

        if (!$faq->update()) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Error updating FAQ.',
            ]);
        }

        return new JsonResponse([
            'success' => true,
            'message' => 'FAQ rejected.',
        ]);
    }

    /**
     * @AdminSecurity("is_granted('update', request.get('_legacy_controller'))")
     */
    public function toggleActiveAction(Request $request, int $faqId): JsonResponse
    {
        $faq = new CategoryFaq($faqId);

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
        $faq = new CategoryFaq($faqId);

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
