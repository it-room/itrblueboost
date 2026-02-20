<?php

declare(strict_types=1);

namespace Itrblueboost\Controller\Admin;

use Configuration;
use Context;
use Itrblueboost\Entity\ProductContent;
use Itrblueboost\Form\ProductContentType;
use Itrblueboost\Grid\Definition\Factory\ProductContentGridDefinitionFactory;
use Itrblueboost\Service\ApiLogger;
use PrestaShop\PrestaShop\Core\Grid\Search\SearchCriteria;
use PrestaShopBundle\Controller\Admin\FrameworkBundleAdminController;
use PrestaShopBundle\Security\Annotation\AdminSecurity;
use Product;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Controller for product content management.
 */
class ProductContentController extends FrameworkBundleAdminController
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
    public function indexAction(Request $request, int $id_product): Response
    {
        $gridFactory = $this->get('itrblueboost.grid.factory.product_content');

        $filters = [
            'id_product' => $id_product,
        ];

        $searchCriteria = new SearchCriteria(
            $filters,
            'id_itrblueboost_product_content',
            'desc',
            0,
            50
        );

        $grid = $gridFactory->getGrid($searchCriteria);

        $baseUrl = $request->getBaseUrl();
        $token = $this->get('security.csrf.token_manager')->getToken('_token')->getValue();
        $productEditUrl = $baseUrl . '/sell/catalog/products-v2/' . $id_product . '/edit?_token=' . $token;

        return $this->render('@Modules/itrblueboost/views/templates/admin/product_content/index.html.twig', [
            'grid' => $this->presentGrid($grid),
            'id_product' => $id_product,
            'product_edit_url' => $productEditUrl,
            'layoutTitle' => $this->trans('Product Content', 'Modules.Itrblueboost.Admin'),
        ]);
    }

    /**
     * @AdminSecurity("is_granted('read', request.get('_legacy_controller'))")
     */
    public function getPromptsAction(): JsonResponse
    {
        $response = $this->apiLogger->getContentPrompts();

        if (!isset($response['success']) || !$response['success']) {
            return new JsonResponse([
                'success' => false,
                'message' => $response['message'] ?? 'Error retrieving prompts.',
            ]);
        }

        if (!isset($response['credits_remaining'])) {
            $creditsValue = Configuration::get('ITRBLUEBOOST_CREDITS_REMAINING');
            if ($creditsValue !== false && $creditsValue !== '') {
                $response['credits_remaining'] = (int) $creditsValue;
            }
        }

        return new JsonResponse($response);
    }

    /**
     * @AdminSecurity("is_granted('create', request.get('_legacy_controller'))")
     */
    public function generateAction(Request $request, int $id_product): JsonResponse
    {
        $promptId = (int) $request->request->get('prompt_id');
        $contentType = $request->request->get('content_type', 'description');

        if ($promptId <= 0) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Prompt not selected.',
            ]);
        }

        if (!in_array($contentType, ['description', 'description_short'], true)) {
            $contentType = 'description';
        }

        $context = Context::getContext();
        $idLang = $context->language ? (int) $context->language->id : (int) Configuration::get('PS_LANG_DEFAULT');

        $product = new Product($id_product, false, $idLang);
        if (!$product->id || !\Validate::isLoadedObject($product)) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Product not found (ID: ' . $id_product . ').',
            ]);
        }

        $productData = $this->buildProductData($product, $idLang);

        $response = $this->apiLogger->generateProductContent($promptId, $productData, $contentType, $id_product);

        if (!isset($response['success']) || !$response['success']) {
            return new JsonResponse([
                'success' => false,
                'message' => $response['message'] ?? 'Error generating content.',
            ]);
        }

        $descriptions = $this->extractDescriptions($response);

        if (empty($descriptions)) {
            return new JsonResponse([
                'success' => false,
                'message' => 'No content generated.',
            ]);
        }

        $firstDesc = $descriptions[0];
        $apiContentId = $firstDesc['id'] ?? null;
        $descLong = $firstDesc['description_long'] ?? '';
        $descShort = $firstDesc['description_short'] ?? '';

        $languages = \Language::getLanguages(false);
        $savedIds = [];
        $saveErrors = [];
        $previewContent = '';
        $requestedContentId = null;

        // Save description_long if available
        if (!empty($descLong)) {
            $content = $this->saveProductContent(
                $id_product,
                $apiContentId,
                ProductContent::CONTENT_TYPE_DESCRIPTION,
                $promptId,
                $descLong,
                $languages
            );
            if ($content) {
                $savedIds[] = $content->id;
                if ($contentType === 'description') {
                    $previewContent = $descLong;
                    $requestedContentId = (int) $content->id;
                }
            } else {
                $saveErrors[] = 'description: ' . $this->getLastSaveError();
            }
        }

        // Save description_short if available
        if (!empty($descShort)) {
            $content = $this->saveProductContent(
                $id_product,
                $apiContentId,
                ProductContent::CONTENT_TYPE_SHORT_DESCRIPTION,
                $promptId,
                $descShort,
                $languages
            );
            if ($content) {
                $savedIds[] = $content->id;
                if ($contentType === 'description_short') {
                    $previewContent = $descShort;
                    $requestedContentId = (int) $content->id;
                }
            } else {
                $saveErrors[] = 'description_short: ' . $this->getLastSaveError();
            }
        }

        if (empty($savedIds)) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Error saving content. ' . implode(' | ', $saveErrors),
            ]);
        }

        // Fallback preview: use whichever was requested, or the first available
        if (empty($previewContent)) {
            $previewContent = $contentType === 'description_short' ? $descShort : $descLong;
        }

        return new JsonResponse([
            'success' => true,
            'message' => 'Content generated (pending approval).',
            'content_id' => $requestedContentId ?? $savedIds[0],
            'content' => $previewContent,
            'credits_used' => $response['credits_used'] ?? 0,
            'credits_remaining' => $response['credits_remaining'] ?? 0,
        ]);
    }

    /**
     * @AdminSecurity("is_granted('update', request.get('_legacy_controller'))")
     */
    public function editAction(Request $request, int $id_product, int $contentId): Response
    {
        $content = new ProductContent($contentId);

        if (!$content->id || (int) $content->id_product !== $id_product) {
            $this->addFlash('error', $this->trans('Content not found.', 'Modules.Itrblueboost.Admin'));
            return $this->redirectToRoute('itrblueboost_admin_product_content_index', ['id_product' => $id_product]);
        }

        $formData = [
            'id_product' => $content->id_product,
            'content_type' => $content->content_type,
            'generated_content' => $content->generated_content,
            'active' => (bool) $content->active,
            'modification_reason' => '',
        ];

        $form = $this->createForm(ProductContentType::class, $formData, [
            'show_modification_reason' => $content->hasApiContentId(),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();

            try {
                $contentChanged = $this->hasMultilangChanged($content->generated_content, $data['generated_content']);

                $content->active = (bool) $data['active'];
                $content->content_type = $data['content_type'];
                $content->generated_content = $data['generated_content'];

                if ($content->hasApiContentId() && $contentChanged) {
                    $modificationReason = $data['modification_reason'] ?? '';

                    if (empty(trim($modificationReason))) {
                        $this->addFlash('error', $this->trans('Modification reason is required when content is changed.', 'Modules.Itrblueboost.Admin'));
                        return $this->render('@Modules/itrblueboost/views/templates/admin/product_content/form.html.twig', [
                            'form' => $form->createView(),
                            'id_product' => $id_product,
                            'contentId' => $contentId,
                            'content' => $content,
                            'layoutTitle' => $this->trans('Edit Content', 'Modules.Itrblueboost.Admin'),
                        ]);
                    }

                    $idLang = (int) Configuration::get('PS_LANG_DEFAULT');
                    $contentText = is_array($data['generated_content'])
                        ? ($data['generated_content'][$idLang] ?? reset($data['generated_content']))
                        : $data['generated_content'];

                    $this->updateContentOnApi((int) $content->api_content_id, [
                        'content' => $contentText,
                        'is_enabled' => (bool) $content->active,
                        'modification_reason' => $modificationReason,
                    ]);
                }

                if ($content->update()) {
                    $this->addFlash('success', $this->trans('Content updated.', 'Modules.Itrblueboost.Admin'));
                    return $this->redirectToRoute('itrblueboost_admin_product_content_index', ['id_product' => $id_product]);
                }
                $this->addFlash('error', $this->trans('Error updating content.', 'Modules.Itrblueboost.Admin'));
            } catch (\Exception $e) {
                $this->addFlash('error', $e->getMessage());
            }
        }

        return $this->render('@Modules/itrblueboost/views/templates/admin/product_content/form.html.twig', [
            'form' => $form->createView(),
            'id_product' => $id_product,
            'contentId' => $contentId,
            'content' => $content,
            'layoutTitle' => $this->trans('Edit Content', 'Modules.Itrblueboost.Admin'),
        ]);
    }

    /**
     * @AdminSecurity("is_granted('update', request.get('_legacy_controller'))")
     */
    public function acceptAction(Request $request, int $id_product, int $contentId): JsonResponse
    {
        $content = new ProductContent($contentId);

        if (!$content->id || (int) $content->id_product !== $id_product) {
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
    public function rejectAction(Request $request, int $id_product, int $contentId): JsonResponse
    {
        $content = new ProductContent($contentId);

        if (!$content->id || (int) $content->id_product !== $id_product) {
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
    public function deleteAction(Request $request, int $id_product, int $contentId): RedirectResponse
    {
        $content = new ProductContent($contentId);

        if (!$content->id) {
            $this->addFlash('error', $this->trans('Content not found.', 'Modules.Itrblueboost.Admin'));
            return $this->redirectToRoute('itrblueboost_admin_product_content_index', ['id_product' => $id_product]);
        }

        try {
            if ($content->hasApiContentId()) {
                $this->updateContentOnApi((int) $content->api_content_id, [
                    'status' => 'rejected',
                    'rejection_reason' => 'Deleted by user',
                    'is_enabled' => false,
                ]);
            }

            if ($content->delete()) {
                $this->addFlash('success', $this->trans('Content deleted.', 'Modules.Itrblueboost.Admin'));
            } else {
                $this->addFlash('error', $this->trans('Error deleting content.', 'Modules.Itrblueboost.Admin'));
            }
        } catch (\Exception $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('itrblueboost_admin_product_content_index', ['id_product' => $id_product]);
    }

    /**
     * @AdminSecurity("is_granted('create', request.get('_legacy_controller'))")
     */
    public function bulkGenerateAction(Request $request): JsonResponse
    {
        $promptId = (int) $request->request->get('prompt_id');
        $contentType = $request->request->get('content_type', 'description');

        if ($promptId <= 0) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Prompt not selected.',
            ]);
        }

        $productIds = $request->request->get('product_ids', '');
        if (empty($productIds)) {
            return new JsonResponse([
                'success' => false,
                'message' => 'No products selected.',
            ]);
        }

        $productIdsArray = array_map('intval', explode(',', $productIds));
        $productIdsArray = array_filter($productIdsArray, function ($id) {
            return $id > 0;
        });

        if (empty($productIdsArray)) {
            return new JsonResponse([
                'success' => false,
                'message' => 'No valid products selected.',
            ]);
        }

        $context = Context::getContext();
        $idLang = $context->language ? (int) $context->language->id : (int) Configuration::get('PS_LANG_DEFAULT');

        $totalCreated = 0;
        $totalCreditsUsed = 0;
        $creditsRemaining = 0;
        $errors = [];

        foreach ($productIdsArray as $idProduct) {
            $product = new Product($idProduct, false, $idLang);
            if (!$product->id || !\Validate::isLoadedObject($product)) {
                $errors[] = sprintf('Product ID %d not found.', $idProduct);
                continue;
            }

            $productData = $this->buildProductData($product, $idLang);
            $response = $this->apiLogger->generateProductContent($promptId, $productData, $contentType, $idProduct);

            if (!isset($response['success']) || !$response['success']) {
                $errors[] = sprintf('Product "%s" (ID %d): %s', $product->name, $idProduct, $response['message'] ?? 'Error');
                continue;
            }

            $descriptions = $this->extractDescriptions($response);

            if (!empty($descriptions)) {
                $firstDesc = $descriptions[0];
                $apiContentId = $firstDesc['id'] ?? null;
                $descLong = $firstDesc['description_long'] ?? '';
                $descShort = $firstDesc['description_short'] ?? '';
                $languages = \Language::getLanguages(false);

                if (!empty($descLong)) {
                    $saved = $this->saveProductContent(
                        $idProduct,
                        $apiContentId,
                        ProductContent::CONTENT_TYPE_DESCRIPTION,
                        $promptId,
                        $descLong,
                        $languages
                    );
                    if ($saved) {
                        $totalCreated++;
                    }
                }

                if (!empty($descShort)) {
                    $saved = $this->saveProductContent(
                        $idProduct,
                        $apiContentId,
                        ProductContent::CONTENT_TYPE_SHORT_DESCRIPTION,
                        $promptId,
                        $descShort,
                        $languages
                    );
                    if ($saved) {
                        $totalCreated++;
                    }
                }
            }

            $totalCreditsUsed += $response['credits_used'] ?? 0;
            $creditsRemaining = $response['credits_remaining'] ?? 0;
        }

        $message = sprintf('%d contents generated for %d products.', $totalCreated, count($productIdsArray) - count($errors));
        if (!empty($errors)) {
            $message .= ' Errors: ' . count($errors);
        }

        return new JsonResponse([
            'success' => true,
            'message' => $message,
            'total_created' => $totalCreated,
            'credits_used' => $totalCreditsUsed,
            'credits_remaining' => $creditsRemaining,
            'errors' => $errors,
        ]);
    }

    /**
     * Extract descriptions array from API response.
     *
     * @param array<string, mixed> $response API response
     *
     * @return array<int, array<string, mixed>>
     */
    private function extractDescriptions(array $response): array
    {
        $data = $response['data'] ?? [];

        // Format: data.descriptions[...]
        if (!empty($data['descriptions']) && is_array($data['descriptions'])) {
            return $data['descriptions'];
        }

        // Fallback: data itself is a single description
        if (!empty($data['description_long']) || !empty($data['description_short'])) {
            return [$data];
        }

        return [];
    }

    /**
     * Save a ProductContent entity.
     *
     * @param int $idProduct Product ID
     * @param int|null $apiContentId API content ID
     * @param string $contentType Content type
     * @param int $promptId Prompt ID
     * @param string $generatedText Generated text
     * @param array<int, array<string, mixed>> $languages Languages list
     *
     * @return ProductContent|null
     */
    private function saveProductContent(
        int $idProduct,
        ?int $apiContentId,
        string $contentType,
        int $promptId,
        string $generatedText,
        array $languages
    ): ?ProductContent {
        $content = new ProductContent();
        $content->id_product = $idProduct;
        $content->api_content_id = ($apiContentId !== null && $apiContentId > 0) ? (int) $apiContentId : 0;
        $content->content_type = $contentType;
        $content->status = ProductContent::STATUS_PENDING;
        $content->prompt_id = $promptId;
        $content->active = 0;

        $content->generated_content = [];
        foreach ($languages as $lang) {
            $content->generated_content[(int) $lang['id_lang']] = $generatedText;
        }

        if ($content->add()) {
            return $content;
        }

        error_log('[ITRBLUEBOOST] ProductContent::add() failed: ' . $content->last_error);

        return null;
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
        $product = new Product((int) $content->id_product);
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
     * Check if multilang field has changed.
     *
     * @param mixed $old
     * @param mixed $new
     *
     * @return bool
     */
    private function hasMultilangChanged($old, $new): bool
    {
        if (!is_array($old) || !is_array($new)) {
            return $old !== $new;
        }

        foreach ($new as $langId => $value) {
            if (!isset($old[$langId]) || $old[$langId] !== $value) {
                return true;
            }
        }

        return false;
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

    /**
     * Get last save error from error log.
     *
     * @return string
     */
    private function getLastSaveError(): string
    {
        return \Db::getInstance()->getMsgError() ?: 'Unknown error';
    }

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
        $brandName = '';
        if ($product->id_manufacturer > 0) {
            $manufacturer = new \Manufacturer($product->id_manufacturer, $idLang);
            if ($manufacturer->id) {
                $brandName = $manufacturer->name;
            }
        }

        $categoryName = '';
        if ($product->id_category_default > 0) {
            $category = new \Category($product->id_category_default, $idLang);
            if ($category->id) {
                $categoryName = $category->name;
            }
        }

        $features = [];
        $productFeatures = $product->getFrontFeatures($idLang);
        if (!empty($productFeatures)) {
            foreach ($productFeatures as $feature) {
                $features[] = [
                    'name' => $feature['name'],
                    'value' => $feature['value'],
                ];
            }
        }

        $combinations = [];
        if ($product->hasAttributes()) {
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

            $combinations = array_values($groupedCombinations);
        }

        $productPrice = $product->getPrice(true, null, 2);
        $productUrl = $product->getLink();

        return [
            'name' => $product->name,
            'description' => strip_tags($product->description ?? ''),
            'description_short' => strip_tags($product->description_short ?? ''),
            'brand' => $brandName,
            'category' => $categoryName,
            'reference' => $product->reference ?? '',
            'features' => $features,
            'combinations' => $combinations,
            'price' => (float) $productPrice,
            'url' => $productUrl,
        ];
    }
}
