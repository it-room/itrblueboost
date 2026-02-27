<?php

declare(strict_types=1);

namespace Itrblueboost\Controller\Admin;

use Configuration;
use Context;
use Db;
use Itrblueboost\Controller\Admin\Traits\FaqApiSyncTrait;
use Itrblueboost\Controller\Admin\Traits\MultilangHelperTrait;
use Itrblueboost\Controller\Admin\Traits\ResolveLimitTrait;
use Itrblueboost\Entity\ProductFaq;
use Itrblueboost\Service\ApiLogger;
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
            $whereClause .= ' AND pf.status = "' . pSQL($statusFilter) . '"';
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
            'currentLimit' => $limit,
            'layoutTitle' => $this->trans('All Product FAQs', 'Modules.Itrblueboost.Admin'),
        ]);
    }

    /**
     * @AdminSecurity("is_granted('update', request.get('_legacy_controller'))")
     */
    public function acceptAction(Request $request, int $faqId): JsonResponse
    {
        $faq = new ProductFaq($faqId);

        if (!$faq->id) {
            return new JsonResponse([
                'success' => false,
                'message' => 'FAQ not found.',
            ]);
        }

        if ($faq->status === ProductFaq::STATUS_ACCEPTED) {
            return new JsonResponse([
                'success' => false,
                'message' => 'FAQ is already accepted.',
            ]);
        }

        $faq->status = ProductFaq::STATUS_ACCEPTED;
        $faq->active = true;

        // Sync with API if has API ID
        if ($faq->hasApiFaqId()) {
            $idLang = (int) Configuration::get('PS_LANG_DEFAULT');

            $apiResult = $this->updateFaqOnApi((int) $faq->api_faq_id, [
                'status' => 'accepted',
                'is_enabled' => true,
                'question' => $this->resolveMultilangText($faq->question, $idLang),
                'answer' => $this->resolveMultilangText($faq->answer, $idLang),
            ], 'product_faq');

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
        $faq = new ProductFaq($faqId);

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
            ], 'product_faq');

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
        $faq = new ProductFaq($faqId);

        if (!$faq->id) {
            return new JsonResponse([
                'success' => false,
                'message' => 'FAQ not found.',
            ]);
        }

        // Only allow toggling active for accepted FAQs
        if ($faq->status !== ProductFaq::STATUS_ACCEPTED) {
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
            ], 'product_faq');
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
        $faq = new ProductFaq($faqId);

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
            ], 'product_faq');
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

    /**
     * Bulk accept FAQs.
     *
     * @AdminSecurity("is_granted('update', request.get('_legacy_controller'))")
     */
    public function bulkAcceptAction(Request $request): JsonResponse
    {
        $ids = $this->parseFaqIds($request);

        if (empty($ids)) {
            return new JsonResponse([
                'success' => false,
                'message' => 'No FAQ IDs provided.',
            ]);
        }

        $errors = [];
        $processed = 0;

        foreach ($ids as $id) {
            $result = $this->acceptSingleFaq($id);
            if ($result === true) {
                ++$processed;
            } else {
                $errors[] = 'FAQ ' . $id . ': ' . $result;
            }
        }

        return new JsonResponse([
            'success' => $processed > 0,
            'message' => $processed . ' FAQ(s) accepted.',
            'processed' => $processed,
            'errors' => $errors,
        ]);
    }

    /**
     * Bulk reject FAQs.
     *
     * @AdminSecurity("is_granted('update', request.get('_legacy_controller'))")
     */
    public function bulkRejectAction(Request $request): JsonResponse
    {
        $ids = $this->parseFaqIds($request);

        if (empty($ids)) {
            return new JsonResponse([
                'success' => false,
                'message' => 'No FAQ IDs provided.',
            ]);
        }

        $rejectionReason = (string) $request->request->get('rejection_reason', '');
        $errors = [];
        $processed = 0;

        foreach ($ids as $id) {
            $result = $this->rejectSingleFaq($id, $rejectionReason);
            if ($result === true) {
                ++$processed;
            } else {
                $errors[] = 'FAQ ' . $id . ': ' . $result;
            }
        }

        return new JsonResponse([
            'success' => $processed > 0,
            'message' => $processed . ' FAQ(s) rejected.',
            'processed' => $processed,
            'errors' => $errors,
        ]);
    }

    /**
     * Bulk delete FAQs.
     *
     * @AdminSecurity("is_granted('delete', request.get('_legacy_controller'))")
     */
    public function bulkDeleteAction(Request $request): JsonResponse
    {
        $ids = $this->parseFaqIds($request);

        if (empty($ids)) {
            return new JsonResponse([
                'success' => false,
                'message' => 'No FAQ IDs provided.',
            ]);
        }

        $errors = [];
        $processed = 0;

        foreach ($ids as $id) {
            $result = $this->deleteSingleFaq($id);
            if ($result === true) {
                ++$processed;
            } else {
                $errors[] = 'FAQ ' . $id . ': ' . $result;
            }
        }

        return new JsonResponse([
            'success' => $processed > 0,
            'message' => $processed . ' FAQ(s) deleted.',
            'processed' => $processed,
            'errors' => $errors,
        ]);
    }

    /**
     * Parse and validate FAQ IDs from request.
     *
     * @param Request $request HTTP request
     *
     * @return int[]
     */
    private function parseFaqIds(Request $request): array
    {
        $rawIds = (string) $request->request->get('faq_ids', '');

        if (empty($rawIds)) {
            return [];
        }

        $ids = array_map('intval', explode(',', $rawIds));

        return array_filter($ids, function (int $id): bool {
            return $id > 0;
        });
    }

    /**
     * Accept a single FAQ.
     *
     * @param int $faqId FAQ ID
     *
     * @return true|string True on success, error message on failure
     */
    private function acceptSingleFaq(int $faqId)
    {
        $faq = new ProductFaq($faqId);

        if (!$faq->id) {
            return 'FAQ not found';
        }

        if ($faq->status === ProductFaq::STATUS_ACCEPTED) {
            return 'Already accepted';
        }

        $faq->status = ProductFaq::STATUS_ACCEPTED;
        $faq->active = true;

        if ($faq->hasApiFaqId()) {
            $idLang = (int) Configuration::get('PS_LANG_DEFAULT');
            $apiResult = $this->updateFaqOnApi((int) $faq->api_faq_id, [
                'status' => 'accepted',
                'is_enabled' => true,
                'question' => $this->resolveMultilangText($faq->question, $idLang),
                'answer' => $this->resolveMultilangText($faq->answer, $idLang),
            ], 'product_faq');

            if (!$apiResult['success']) {
                return 'API error - ' . ($apiResult['message'] ?? 'Unknown');
            }
        }

        if (!$faq->update()) {
            return 'Update failed';
        }

        return true;
    }

    /**
     * Reject a single FAQ.
     *
     * @param int $faqId FAQ ID
     * @param string $reason Rejection reason
     *
     * @return true|string True on success, error message on failure
     */
    private function rejectSingleFaq(int $faqId, string $reason)
    {
        $faq = new ProductFaq($faqId);

        if (!$faq->id) {
            return 'FAQ not found';
        }

        if ($faq->hasApiFaqId()) {
            $apiResult = $this->updateFaqOnApi((int) $faq->api_faq_id, [
                'status' => 'rejected',
                'rejection_reason' => $reason,
                'is_enabled' => false,
            ], 'product_faq');

            if (!$apiResult['success']) {
                return 'API error - ' . ($apiResult['message'] ?? 'Unknown');
            }
        }

        if (!$faq->delete()) {
            return 'Delete failed';
        }

        return true;
    }

    /**
     * Delete a single FAQ.
     *
     * @param int $faqId FAQ ID
     *
     * @return true|string True on success, error message on failure
     */
    private function deleteSingleFaq(int $faqId)
    {
        $faq = new ProductFaq($faqId);

        if (!$faq->id) {
            return 'FAQ not found';
        }

        if ($faq->hasApiFaqId()) {
            $this->updateFaqOnApi((int) $faq->api_faq_id, [
                'status' => 'rejected',
                'rejection_reason' => 'Deleted by user',
                'is_enabled' => false,
            ], 'product_faq');
        }

        if (!$faq->delete()) {
            return 'Delete failed';
        }

        return true;
    }
}
