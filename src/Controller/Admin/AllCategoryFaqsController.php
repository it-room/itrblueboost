<?php

declare(strict_types=1);

namespace Itrblueboost\Controller\Admin;

use Configuration;
use Context;
use Db;
use Itrblueboost\Controller\Admin\Traits\FaqApiSyncTrait;
use Itrblueboost\Controller\Admin\Traits\MultilangHelperTrait;
use Itrblueboost\Controller\Admin\Traits\ResolveLimitTrait;
use Itrblueboost\Entity\CategoryFaq;
use Itrblueboost\Service\ApiLogger;
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
    use ResolveLimitTrait;
    use FaqApiSyncTrait;
    use MultilangHelperTrait;

    /**
     * @var ApiLogger
     */
    private $apiLogger;

    public function __construct()
    {
        $this->apiLogger = new ApiLogger();
    }

    /**
     * @AdminSecurity("is_granted('read', request.get('_legacy_controller'))")
     */
    public function indexAction(Request $request): Response
    {
        $page = max(1, (int) $request->query->get('page', 1));
        $limit = $this->resolveLimit((int) $request->query->get('limit', 20));
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
            'currentLimit' => $limit,
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

        if ($faq->status === CategoryFaq::STATUS_ACCEPTED) {
            return new JsonResponse([
                'success' => false,
                'message' => 'FAQ is already accepted.',
            ]);
        }

        $faq->status = CategoryFaq::STATUS_ACCEPTED;
        $faq->active = true;

        // Sync with API if has API ID
        if ($faq->hasApiFaqId()) {
            $idLang = (int) Configuration::get('PS_LANG_DEFAULT');

            $apiResult = $this->updateFaqOnApi((int) $faq->api_faq_id, [
                'status' => 'accepted',
                'is_enabled' => true,
                'question' => $this->resolveMultilangText($faq->question, $idLang),
                'answer' => $this->resolveMultilangText($faq->answer, $idLang),
            ], 'category_faq');

            if (!$apiResult['success']) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'API sync error: ' . ($apiResult['message'] ?? 'Unknown error'),
                ]);
            }
        }

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

        $rejectionReason = $request->request->get('rejection_reason', '');

        // Sync rejection with API if has API ID
        if ($faq->hasApiFaqId()) {
            $apiResult = $this->updateFaqOnApi((int) $faq->api_faq_id, [
                'status' => 'rejected',
                'rejection_reason' => $rejectionReason,
                'is_enabled' => false,
            ], 'category_faq');

            if (!$apiResult['success']) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'API sync error: ' . ($apiResult['message'] ?? 'Unknown error'),
                ]);
            }
        }

        // Delete FAQ locally
        if (!$faq->delete()) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Error deleting FAQ.',
            ]);
        }

        return new JsonResponse([
            'success' => true,
            'message' => 'FAQ rejected and deleted.',
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

        // Only allow toggling active for accepted FAQs
        if ($faq->status !== CategoryFaq::STATUS_ACCEPTED) {
            return new JsonResponse([
                'success' => false,
                'message' => 'FAQ must be accepted before it can be activated.',
            ]);
        }

        $faq->active = !$faq->active;

        // Sync with API if has API ID
        if ($faq->hasApiFaqId()) {
            $this->updateFaqOnApi((int) $faq->api_faq_id, [
                'is_enabled' => (bool) $faq->active,
            ], 'category_faq');
        }

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

        // If has API ID, sync deletion as rejection
        if ($faq->hasApiFaqId()) {
            $this->updateFaqOnApi((int) $faq->api_faq_id, [
                'status' => 'rejected',
                'rejection_reason' => 'Deleted by user',
                'is_enabled' => false,
            ], 'category_faq');
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
