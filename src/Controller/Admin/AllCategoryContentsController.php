<?php

declare(strict_types=1);

namespace Itrblueboost\Controller\Admin;

use Configuration;
use Itrblueboost\Controller\Admin\Traits\ContentApiSyncTrait;
use Itrblueboost\Controller\Admin\Traits\MultilangHelperTrait;
use Itrblueboost\Controller\Admin\Traits\ResolveLimitTrait;
use Itrblueboost\Entity\CategoryContent;
use Itrblueboost\Service\ApiLogger;
use PrestaShopBundle\Controller\Admin\FrameworkBundleAdminController;
use PrestaShopBundle\Security\Annotation\AdminSecurity;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Controller for all category contents global view.
 */
class AllCategoryContentsController extends FrameworkBundleAdminController
{
    use ResolveLimitTrait;
    use ContentApiSyncTrait;
    use MultilangHelperTrait;

    /** @var ApiLogger */
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

        $contents = CategoryContent::getAllContents(
            $idLang,
            $idShop,
            $statusFilter !== '' ? $statusFilter : null,
            $limit,
            $offset
        );

        $totalContents = CategoryContent::countAllContents(
            $idShop,
            $statusFilter !== '' ? $statusFilter : null
        );

        $totalPages = (int) ceil($totalContents / $limit);

        return $this->render('@Modules/itrblueboost/views/templates/admin/all_category_contents/index.html.twig', [
            'contents' => $contents,
            'totalContents' => $totalContents,
            'currentPage' => $page,
            'totalPages' => $totalPages,
            'statusFilter' => $statusFilter,
            'currentLimit' => $limit,
            'layoutTitle' => $this->trans('All Category Contents', 'Modules.Itrblueboost.Admin'),
        ]);
    }

    /**
     * @AdminSecurity("is_granted('update', request.get('_legacy_controller'))")
     */
    public function acceptAction(Request $request, int $id): JsonResponse
    {
        $result = $this->acceptSingleContent($id);

        if ($result === true) {
            return new JsonResponse([
                'success' => true,
                'message' => 'Content accepted and applied to category.',
            ]);
        }

        return new JsonResponse([
            'success' => false,
            'message' => $result,
        ]);
    }

    /**
     * @AdminSecurity("is_granted('update', request.get('_legacy_controller'))")
     */
    public function rejectAction(Request $request, int $id): JsonResponse
    {
        $rejectionReason = $request->request->get('rejection_reason', '');
        $result = $this->rejectSingleContent($id, (string) $rejectionReason);

        if ($result === true) {
            return new JsonResponse([
                'success' => true,
                'message' => 'Content rejected and deleted.',
            ]);
        }

        return new JsonResponse([
            'success' => false,
            'message' => $result,
        ]);
    }

    /**
     * @AdminSecurity("is_granted('delete', request.get('_legacy_controller'))")
     */
    public function deleteAction(Request $request, int $id): JsonResponse
    {
        $result = $this->deleteSingleContent($id);

        if ($result === true) {
            return new JsonResponse([
                'success' => true,
                'message' => 'Content deleted.',
            ]);
        }

        return new JsonResponse([
            'success' => false,
            'message' => $result,
        ]);
    }

    /**
     * @AdminSecurity("is_granted('update', request.get('_legacy_controller'))")
     */
    public function bulkAcceptAction(Request $request): JsonResponse
    {
        $ids = $this->parseContentIds($request);

        if (empty($ids)) {
            return new JsonResponse(['success' => false, 'message' => 'No content IDs provided.']);
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
     * @AdminSecurity("is_granted('update', request.get('_legacy_controller'))")
     */
    public function bulkRejectAction(Request $request): JsonResponse
    {
        $ids = $this->parseContentIds($request);

        if (empty($ids)) {
            return new JsonResponse(['success' => false, 'message' => 'No content IDs provided.']);
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
     * @AdminSecurity("is_granted('delete', request.get('_legacy_controller'))")
     */
    public function bulkDeleteAction(Request $request): JsonResponse
    {
        $ids = $this->parseContentIds($request);

        if (empty($ids)) {
            return new JsonResponse(['success' => false, 'message' => 'No content IDs provided.']);
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
     * Parse content IDs from request.
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
     * @return true|string
     */
    private function acceptSingleContent(int $contentId)
    {
        $content = new CategoryContent($contentId);

        if (!$content->id) {
            return 'Content not found';
        }

        if ($content->status === CategoryContent::STATUS_ACCEPTED) {
            return 'Already accepted';
        }

        $content->status = CategoryContent::STATUS_ACCEPTED;
        $content->active = true;

        $applyResult = $this->applyContentToCategory($content);
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
     * @return true|string
     */
    private function rejectSingleContent(int $contentId, string $reason)
    {
        $content = new CategoryContent($contentId);

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
     * @return true|string
     */
    private function deleteSingleContent(int $contentId)
    {
        $content = new CategoryContent($contentId);

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
     * Apply content to category.
     *
     * @return array{success: bool, message?: string}
     */
    private function applyContentToCategory(CategoryContent $content): array
    {
        $category = new \Category((int) $content->id_category);
        if (!$category->id) {
            return ['success' => false, 'message' => 'Category not found.'];
        }

        $this->applyDescription($category, $content->generated_content);
        $this->applyAdditionalDescription($category, $content->generated_content_short);

        if (!$category->update()) {
            return ['success' => false, 'message' => 'Error updating category.'];
        }

        return ['success' => true];
    }

    /**
     * Apply description to category.
     *
     * @param \Category $category
     * @param mixed $generatedContent
     */
    private function applyDescription(\Category $category, $generatedContent): void
    {
        if (empty($generatedContent)) {
            return;
        }

        if (is_array($generatedContent)) {
            foreach ($generatedContent as $idLang => $text) {
                $category->description[$idLang] = $text;
            }
        } else {
            $category->description = $generatedContent;
        }
    }

    /**
     * Apply additional description to category.
     *
     * @param \Category $category
     * @param mixed $generatedContentShort
     */
    private function applyAdditionalDescription(\Category $category, $generatedContentShort): void
    {
        if (empty($generatedContentShort)) {
            return;
        }

        if (is_array($generatedContentShort)) {
            foreach ($generatedContentShort as $idLang => $text) {
                $category->additional_description[$idLang] = $text;
            }
        } else {
            $category->additional_description = $generatedContentShort;
        }
    }
}
