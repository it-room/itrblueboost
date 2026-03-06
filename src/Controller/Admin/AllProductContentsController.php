<?php

declare(strict_types=1);

namespace Itrblueboost\Controller\Admin;

use Configuration;
use Itrblueboost\Controller\Admin\Traits\ContentApiSyncTrait;
use Itrblueboost\Controller\Admin\Traits\MultilangHelperTrait;
use Itrblueboost\Controller\Admin\Traits\ResolveLimitTrait;
use Itrblueboost\Entity\ProductContent;
use Itrblueboost\Service\ApiLogger;
use PrestaShopBundle\Controller\Admin\FrameworkBundleAdminController;
use PrestaShopBundle\Security\Annotation\AdminSecurity;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Controller for all product contents global view.
 */
class AllProductContentsController extends FrameworkBundleAdminController
{
    use ResolveLimitTrait;
    use ContentApiSyncTrait;
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

        $idLang = (int) $this->getContext()->language->id;
        $idShop = (int) $this->getContext()->shop->id;

        $contents = ProductContent::getAllContents(
            $idLang,
            $idShop,
            $statusFilter !== '' ? $statusFilter : null,
            $limit,
            $offset
        );

        $totalContents = ProductContent::countAllContents(
            $idShop,
            $statusFilter !== '' ? $statusFilter : null
        );

        $totalPages = (int) ceil($totalContents / $limit);

        return $this->render('@Modules/itrblueboost/views/templates/admin/all_product_contents/index.html.twig', [
            'contents' => $contents,
            'totalContents' => $totalContents,
            'currentPage' => $page,
            'totalPages' => $totalPages,
            'statusFilter' => $statusFilter,
            'currentLimit' => $limit,
            'layoutTitle' => $this->trans('All Product Contents', 'Modules.Itrblueboost.Admin'),
        ]);
    }

    /**
     * @AdminSecurity("is_granted('update', request.get('_legacy_controller'))")
     */
    public function acceptAction(Request $request, int $id): JsonResponse
    {
        $content = new ProductContent($id);

        if (!$content->id) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Content not found.',
            ]);
        }

        if ($content->status === ProductContent::STATUS_ACCEPTED) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Content is already accepted.',
            ]);
        }

        $content->status = ProductContent::STATUS_ACCEPTED;
        $content->active = true;

        // Apply content to product
        $applyResult = $this->applyContentToProduct($content);
        if (!$applyResult['success']) {
            return new JsonResponse([
                'success' => false,
                'message' => $applyResult['message'],
            ]);
        }

        if ($content->hasApiContentId()) {
            $idLang = (int) Configuration::get('PS_LANG_DEFAULT');

            $apiResult = $this->updateContentOnApi((int) $content->api_content_id, [
                'status' => 'accepted',
                'is_enabled' => true,
                'content' => $this->resolveMultilangText($content->generated_content, $idLang),
            ]);

            if (!$apiResult['success']) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'API sync error: ' . ($apiResult['message'] ?? 'Unknown error'),
                ]);
            }
        }

        if (!$content->update()) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Error updating content.',
            ]);
        }

        return new JsonResponse([
            'success' => true,
            'message' => 'Content accepted and applied to product.',
        ]);
    }

    /**
     * @AdminSecurity("is_granted('update', request.get('_legacy_controller'))")
     */
    public function rejectAction(Request $request, int $id): JsonResponse
    {
        $content = new ProductContent($id);

        if (!$content->id) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Content not found.',
            ]);
        }

        $rejectionReason = $request->request->get('rejection_reason', '');

        if ($content->hasApiContentId()) {
            $apiResult = $this->updateContentOnApi((int) $content->api_content_id, [
                'status' => 'rejected',
                'rejection_reason' => $rejectionReason,
                'is_enabled' => false,
            ]);

            if (!$apiResult['success']) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'API sync error: ' . ($apiResult['message'] ?? 'Unknown error'),
                ]);
            }
        }

        if (!$content->delete()) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Error deleting content.',
            ]);
        }

        return new JsonResponse([
            'success' => true,
            'message' => 'Content rejected and deleted.',
        ]);
    }

    /**
     * @AdminSecurity("is_granted('delete', request.get('_legacy_controller'))")
     */
    public function deleteAction(Request $request, int $id): JsonResponse
    {
        $content = new ProductContent($id);

        if (!$content->id) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Content not found.',
            ]);
        }

        if ($content->hasApiContentId()) {
            $this->updateContentOnApi((int) $content->api_content_id, [
                'status' => 'rejected',
                'rejection_reason' => 'Deleted by user',
                'is_enabled' => false,
            ]);
        }

        if (!$content->delete()) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Error deleting content.',
            ]);
        }

        return new JsonResponse([
            'success' => true,
            'message' => 'Content deleted.',
        ]);
    }

    /**
     * Bulk accept contents.
     *
     * @AdminSecurity("is_granted('update', request.get('_legacy_controller'))")
     */
    public function bulkAcceptAction(Request $request): JsonResponse
    {
        $ids = $this->parseContentIds($request);

        if (empty($ids)) {
            return new JsonResponse([
                'success' => false,
                'message' => 'No content IDs provided.',
            ]);
        }

        $errors = [];
        $processed = 0;

        foreach ($ids as $id) {
            $result = $this->acceptSingleContent($id);
            if ($result === true) {
                ++$processed;
            } else {
                $errors[] = 'Content ' . $id . ': ' . $result;
            }
        }

        return new JsonResponse([
            'success' => $processed > 0,
            'message' => $processed . ' content(s) accepted.',
            'processed' => $processed,
            'errors' => $errors,
        ]);
    }

    /**
     * Bulk reject contents.
     *
     * @AdminSecurity("is_granted('update', request.get('_legacy_controller'))")
     */
    public function bulkRejectAction(Request $request): JsonResponse
    {
        $ids = $this->parseContentIds($request);

        if (empty($ids)) {
            return new JsonResponse([
                'success' => false,
                'message' => 'No content IDs provided.',
            ]);
        }

        $rejectionReason = (string) $request->request->get('rejection_reason', '');
        $errors = [];
        $processed = 0;

        foreach ($ids as $id) {
            $result = $this->rejectSingleContent($id, $rejectionReason);
            if ($result === true) {
                ++$processed;
            } else {
                $errors[] = 'Content ' . $id . ': ' . $result;
            }
        }

        return new JsonResponse([
            'success' => $processed > 0,
            'message' => $processed . ' content(s) rejected.',
            'processed' => $processed,
            'errors' => $errors,
        ]);
    }

    /**
     * Bulk delete contents.
     *
     * @AdminSecurity("is_granted('delete', request.get('_legacy_controller'))")
     */
    public function bulkDeleteAction(Request $request): JsonResponse
    {
        $ids = $this->parseContentIds($request);

        if (empty($ids)) {
            return new JsonResponse([
                'success' => false,
                'message' => 'No content IDs provided.',
            ]);
        }

        $errors = [];
        $processed = 0;

        foreach ($ids as $id) {
            $result = $this->deleteSingleContent($id);
            if ($result === true) {
                ++$processed;
            } else {
                $errors[] = 'Content ' . $id . ': ' . $result;
            }
        }

        return new JsonResponse([
            'success' => $processed > 0,
            'message' => $processed . ' content(s) deleted.',
            'processed' => $processed,
            'errors' => $errors,
        ]);
    }

    /**
     * Parse and validate content IDs from request.
     *
     * @param Request $request HTTP request
     *
     * @return int[]
     */
    private function parseContentIds(Request $request): array
    {
        $rawIds = (string) $request->request->get('content_ids', '');

        if (empty($rawIds)) {
            return [];
        }

        $ids = array_map('intval', explode(',', $rawIds));

        return array_filter($ids, function (int $id): bool {
            return $id > 0;
        });
    }

    /**
     * Accept a single content.
     *
     * @param int $contentId Content ID
     *
     * @return true|string True on success, error message on failure
     */
    private function acceptSingleContent(int $contentId)
    {
        $content = new ProductContent($contentId);

        if (!$content->id) {
            return 'Content not found';
        }

        if ($content->status === ProductContent::STATUS_ACCEPTED) {
            return 'Already accepted';
        }

        $content->status = ProductContent::STATUS_ACCEPTED;
        $content->active = true;

        $applyResult = $this->applyContentToProduct($content);
        if (!$applyResult['success']) {
            return $applyResult['message'] ?? 'Apply failed';
        }

        if ($content->hasApiContentId()) {
            $idLang = (int) Configuration::get('PS_LANG_DEFAULT');
            $apiResult = $this->updateContentOnApi((int) $content->api_content_id, [
                'status' => 'accepted',
                'is_enabled' => true,
                'content' => $this->resolveMultilangText($content->generated_content, $idLang),
            ]);

            if (!$apiResult['success']) {
                return 'API error - ' . ($apiResult['message'] ?? 'Unknown');
            }
        }

        if (!$content->update()) {
            return 'Update failed';
        }

        return true;
    }

    /**
     * Reject a single content.
     *
     * @param int $contentId Content ID
     * @param string $reason Rejection reason
     *
     * @return true|string True on success, error message on failure
     */
    private function rejectSingleContent(int $contentId, string $reason)
    {
        $content = new ProductContent($contentId);

        if (!$content->id) {
            return 'Content not found';
        }

        if ($content->hasApiContentId()) {
            $apiResult = $this->updateContentOnApi((int) $content->api_content_id, [
                'status' => 'rejected',
                'rejection_reason' => $reason,
                'is_enabled' => false,
            ]);

            if (!$apiResult['success']) {
                return 'API error - ' . ($apiResult['message'] ?? 'Unknown');
            }
        }

        if (!$content->delete()) {
            return 'Delete failed';
        }

        return true;
    }

    /**
     * Delete a single content.
     *
     * @param int $contentId Content ID
     *
     * @return true|string True on success, error message on failure
     */
    private function deleteSingleContent(int $contentId)
    {
        $content = new ProductContent($contentId);

        if (!$content->id) {
            return 'Content not found';
        }

        if ($content->hasApiContentId()) {
            $this->updateContentOnApi((int) $content->api_content_id, [
                'status' => 'rejected',
                'rejection_reason' => 'Deleted by user',
                'is_enabled' => false,
            ]);
        }

        if (!$content->delete()) {
            return 'Delete failed';
        }

        return true;
    }

    /**
     * Apply content to product.
     *
     * @param ProductContent $content
     *
     * @return array{success: bool, message?: string}
     */
    private function applyContentToProduct(ProductContent $content): array
    {
        $product = new \Product((int) $content->id_product);
        if (!$product->id) {
            return ['success' => false, 'message' => 'Product not found.'];
        }

        $this->applyDescription($product, $content->generated_content);
        $this->applyDescriptionShort($product, $content->generated_content_short);

        if (!$product->update()) {
            return ['success' => false, 'message' => 'Error updating product.'];
        }

        return ['success' => true];
    }

    /**
     * Apply description to product.
     *
     * @param \Product $product
     * @param mixed $generatedContent
     */
    private function applyDescription(\Product $product, $generatedContent): void
    {
        if (empty($generatedContent)) {
            return;
        }

        if (is_array($generatedContent)) {
            foreach ($generatedContent as $idLang => $text) {
                $product->description[$idLang] = $text;
            }
        } else {
            $product->description = $generatedContent;
        }
    }

    /**
     * Apply short description to product.
     *
     * @param \Product $product
     * @param mixed $generatedContentShort
     */
    private function applyDescriptionShort(\Product $product, $generatedContentShort): void
    {
        if (empty($generatedContentShort)) {
            return;
        }

        if (is_array($generatedContentShort)) {
            foreach ($generatedContentShort as $idLang => $text) {
                $product->description_short[$idLang] = $text;
            }
        } else {
            $product->description_short = $generatedContentShort;
        }
    }

}
