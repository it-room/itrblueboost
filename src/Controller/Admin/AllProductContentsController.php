<?php

declare(strict_types=1);

namespace Itrblueboost\Controller\Admin;

use Configuration;
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
        $page = (int) $request->query->get('page', 1);
        $limit = 24;
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
            $contentText = is_array($content->generated_content)
                ? ($content->generated_content[$idLang] ?? reset($content->generated_content))
                : $content->generated_content;

            $apiResult = $this->updateContentOnApi((int) $content->api_content_id, [
                'status' => 'accepted',
                'is_enabled' => true,
                'content' => $contentText,
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
     * @AdminSecurity("is_granted('update', request.get('_legacy_controller'))")
     */
    public function toggleActiveAction(Request $request, int $id): JsonResponse
    {
        $content = new ProductContent($id);

        if (!$content->id) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Content not found.',
            ]);
        }

        if ($content->status !== ProductContent::STATUS_ACCEPTED) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Content must be accepted before it can be toggled.',
            ]);
        }

        $content->active = !$content->active;

        if ($content->hasApiContentId()) {
            $this->updateContentOnApi((int) $content->api_content_id, [
                'is_enabled' => (bool) $content->active,
            ]);
        }

        if (!$content->update()) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Error updating content.',
            ]);
        }

        return new JsonResponse([
            'success' => true,
            'message' => $content->active ? 'Content activated.' : 'Content deactivated.',
            'active' => (bool) $content->active,
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

        $generatedContent = $content->generated_content;

        if ($content->content_type === ProductContent::CONTENT_TYPE_SHORT_DESCRIPTION) {
            if (is_array($generatedContent)) {
                foreach ($generatedContent as $idLang => $text) {
                    $product->description_short[$idLang] = $text;
                }
            } else {
                $product->description_short = $generatedContent;
            }
        } else {
            if (is_array($generatedContent)) {
                foreach ($generatedContent as $idLang => $text) {
                    $product->description[$idLang] = $text;
                }
            } else {
                $product->description = $generatedContent;
            }
        }

        if (!$product->update()) {
            return ['success' => false, 'message' => 'Error updating product.'];
        }

        return ['success' => true];
    }

    /**
     * Update content on API.
     *
     * @param int $apiContentId API Content ID
     * @param array<string, mixed> $data Data to send
     *
     * @return array{success: bool, message?: string}
     */
    private function updateContentOnApi(int $apiContentId, array $data): array
    {
        $response = $this->apiLogger->updateContent($apiContentId, $data);

        if (!isset($response['success']) || !$response['success']) {
            return ['success' => false, 'message' => $response['message'] ?? 'Unknown error'];
        }

        return ['success' => true];
    }
}
