<?php

declare(strict_types=1);

namespace Itrblueboost\Controller\Admin;

use Category;
use Configuration;
use Context;
use Itrblueboost\Controller\Admin\Traits\ContentApiSyncTrait;
use Itrblueboost\Controller\Admin\Traits\MultilangHelperTrait;
use Itrblueboost\Entity\CategoryContent;
use Itrblueboost\Form\CategoryContentType;
use Itrblueboost\Service\ApiLogger;
use PrestaShop\PrestaShop\Core\Grid\Search\SearchCriteria;
use PrestaShopBundle\Controller\Admin\FrameworkBundleAdminController;
use PrestaShopBundle\Security\Annotation\AdminSecurity;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Controller for category content management.
 */
class CategoryContentController extends FrameworkBundleAdminController
{
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
    public function indexAction(Request $request, int $id_category): Response
    {
        $gridFactory = $this->get('itrblueboost.grid.factory.category_content');

        $searchCriteria = new SearchCriteria(
            ['id_category' => $id_category],
            'id_itrblueboost_category_content',
            'desc',
            0,
            50
        );

        $grid = $gridFactory->getGrid($searchCriteria);

        $baseUrl = $request->getBaseUrl();
        $token = $this->get('security.csrf.token_manager')->getToken('_token')->getValue();
        $categoryEditUrl = $baseUrl . '/sell/catalog/categories/' . $id_category . '/edit?_token=' . $token;

        return $this->render('@Modules/itrblueboost/views/templates/admin/category_content/index.html.twig', [
            'grid' => $this->presentGrid($grid),
            'id_category' => $id_category,
            'category_edit_url' => $categoryEditUrl,
            'layoutTitle' => $this->trans('Category Content', 'Modules.Itrblueboost.Admin'),
        ]);
    }

    /**
     * @AdminSecurity("is_granted('read', request.get('_legacy_controller'))")
     */
    public function getPromptsAction(): JsonResponse
    {
        $response = $this->apiLogger->getCategoryContentPrompts();

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
    public function generateAction(Request $request, int $id_category): JsonResponse
    {
        $promptId = (int) $request->request->get('prompt_id');

        if ($promptId <= 0) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Prompt not selected.',
            ]);
        }

        $context = Context::getContext();
        $idLang = $context->language ? (int) $context->language->id : (int) Configuration::get('PS_LANG_DEFAULT');

        $category = new Category($id_category, $idLang);
        if (!$category->id || !\Validate::isLoadedObject($category)) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Category not found (ID: ' . $id_category . ').',
            ]);
        }

        $categoryData = $this->buildCategoryData($category, $idLang);

        $response = $this->apiLogger->generateCategoryContent($promptId, $categoryData, $id_category);

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

        $seoData = [
            'meta_title' => $firstDesc['meta_title'] ?? '',
            'meta_description' => $firstDesc['meta_description'] ?? '',
            'meta_keywords' => $firstDesc['meta_keywords'] ?? '',
            'original_meta_title' => $firstDesc['original_meta_title'] ?? '',
            'original_meta_description' => $firstDesc['original_meta_description'] ?? '',
            'original_meta_keywords' => $firstDesc['original_meta_keywords'] ?? '',
        ];

        $languages = \Language::getLanguages(false);

        $content = $this->saveCategoryContent(
            $id_category,
            $apiContentId,
            $promptId,
            $descLong,
            $descShort,
            $languages,
            $seoData
        );

        if (!$content) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Error saving content.',
            ]);
        }

        return new JsonResponse([
            'success' => true,
            'message' => 'Content generated (pending approval).',
            'content_id' => (int) $content->id,
            'description' => $descLong,
            'description_short' => $descShort,
            'credits_used' => $response['credits_used'] ?? 0,
            'credits_remaining' => $response['credits_remaining'] ?? 0,
        ]);
    }

    /**
     * @AdminSecurity("is_granted('update', request.get('_legacy_controller'))")
     */
    public function editAction(Request $request, int $id_category, int $contentId): Response
    {
        $content = new CategoryContent($contentId);

        if (!$content->id || (int) $content->id_category !== $id_category) {
            $this->addFlash('error', $this->trans('Content not found.', 'Modules.Itrblueboost.Admin'));
            return $this->redirectToRoute('itrblueboost_admin_category_content_index', ['id_category' => $id_category]);
        }

        $languages = \Language::getLanguages(false);
        $emptyMultilang = [];
        foreach ($languages as $lang) {
            $emptyMultilang[(int) $lang['id_lang']] = '';
        }

        $generatedContent = is_array($content->generated_content) ? $content->generated_content : $emptyMultilang;
        $generatedContentShort = is_array($content->generated_content_short) ? $content->generated_content_short : $emptyMultilang;
        $metaTitle = is_array($content->meta_title) ? $content->meta_title : $emptyMultilang;
        $metaDescription = is_array($content->meta_description) ? $content->meta_description : $emptyMultilang;
        $metaKeywords = is_array($content->meta_keywords) ? $content->meta_keywords : $emptyMultilang;

        $formData = [
            'id_category' => $content->id_category,
            'generated_content' => $generatedContent,
            'generated_content_short' => $generatedContentShort,
            'meta_title' => $metaTitle,
            'meta_description' => $metaDescription,
            'meta_keywords' => $metaKeywords,
            'modification_reason' => '',
        ];

        $form = $this->createForm(CategoryContentType::class, $formData, [
            'show_modification_reason' => $content->hasApiContentId(),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            return $this->handleEditFormSubmission($form, $content, $id_category, $contentId);
        }

        return $this->render('@Modules/itrblueboost/views/templates/admin/category_content/form.html.twig', [
            'form' => $form->createView(),
            'id_category' => $id_category,
            'contentId' => $contentId,
            'content' => $content,
            'layoutTitle' => $this->trans('Edit Content', 'Modules.Itrblueboost.Admin'),
        ]);
    }

    /**
     * Insert (apply) selected content fields to category.
     *
     * @AdminSecurity("is_granted('update', request.get('_legacy_controller'))")
     */
    public function insertAction(Request $request, int $id_category, int $contentId): JsonResponse
    {
        $content = new CategoryContent($contentId);

        if (!$content->id || (int) $content->id_category !== $id_category) {
            return new JsonResponse(['success' => false, 'message' => 'Content not found.']);
        }

        $applyDescription = (bool) $request->request->get('apply_description', false);
        $applyShortDescription = (bool) $request->request->get('apply_description_short', false);
        $applySeo = (bool) $request->request->get('apply_seo', false);

        if (!$applyDescription && !$applyShortDescription && !$applySeo) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Please select at least one content to insert.',
            ]);
        }

        $category = new Category((int) $content->id_category);
        if (!$category->id) {
            return new JsonResponse(['success' => false, 'message' => 'Category not found.']);
        }

        if ($applyDescription) {
            $this->applyCategoryDescription($category, $content->generated_content);
        }

        if ($applyShortDescription) {
            $this->applyCategoryAdditionalDescription($category, $content->generated_content_short);
        }

        if ($applySeo) {
            $this->applyCategorySeo($category, $content);
        }

        if (!$category->update()) {
            return new JsonResponse(['success' => false, 'message' => 'Error updating category.']);
        }

        $content->status = CategoryContent::STATUS_ACCEPTED;

        if ($content->hasApiContentId()) {
            $idLang = (int) Configuration::get('PS_LANG_DEFAULT');
            $this->updateContentOnApi((int) $content->api_content_id, [
                'status' => 'accepted',
                'content' => $this->resolveMultilangText($content->generated_content, $idLang),
            ]);
        }

        $content->update();

        return new JsonResponse([
            'success' => true,
            'message' => 'Content inserted into category.',
        ]);
    }

    /**
     * @AdminSecurity("is_granted('update', request.get('_legacy_controller'))")
     */
    public function acceptAction(Request $request, int $id_category, int $contentId): JsonResponse
    {
        $content = new CategoryContent($contentId);

        if (!$content->id || (int) $content->id_category !== $id_category) {
            return new JsonResponse(['success' => false, 'message' => 'Content not found.']);
        }

        if ($content->status === CategoryContent::STATUS_ACCEPTED) {
            return new JsonResponse(['success' => false, 'message' => 'Content is already accepted.']);
        }

        $content->status = CategoryContent::STATUS_ACCEPTED;
        $content->active = true;

        $applyResult = $this->applyContentToCategory($content);
        if (!$applyResult['success']) {
            return new JsonResponse(['success' => false, 'message' => $applyResult['message']]);
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
            return new JsonResponse(['success' => false, 'message' => 'Error updating content.']);
        }

        return new JsonResponse([
            'success' => true,
            'message' => 'Content accepted and applied to category.',
        ]);
    }

    /**
     * @AdminSecurity("is_granted('update', request.get('_legacy_controller'))")
     */
    public function rejectAction(Request $request, int $id_category, int $contentId): JsonResponse
    {
        $content = new CategoryContent($contentId);

        if (!$content->id || (int) $content->id_category !== $id_category) {
            return new JsonResponse(['success' => false, 'message' => 'Content not found.']);
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
            return new JsonResponse(['success' => false, 'message' => 'Error deleting content.']);
        }

        return new JsonResponse(['success' => true, 'message' => 'Content rejected and deleted.']);
    }

    /**
     * @AdminSecurity("is_granted('delete', request.get('_legacy_controller'))")
     */
    public function deleteAction(Request $request, int $id_category, int $contentId): RedirectResponse
    {
        $content = new CategoryContent($contentId);

        if (!$content->id) {
            $this->addFlash('error', $this->trans('Content not found.', 'Modules.Itrblueboost.Admin'));
            return $this->redirectToRoute('itrblueboost_admin_category_content_index', ['id_category' => $id_category]);
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

        return $this->redirectToRoute('itrblueboost_admin_category_content_index', ['id_category' => $id_category]);
    }

    /**
     * @AdminSecurity("is_granted('create', request.get('_legacy_controller'))")
     */
    public function bulkGenerateAction(Request $request): JsonResponse
    {
        $promptId = (int) $request->request->get('prompt_id');

        if ($promptId <= 0) {
            return new JsonResponse(['success' => false, 'message' => 'Prompt not selected.']);
        }

        $categoryIds = $request->request->get('category_ids', '');
        if (empty($categoryIds)) {
            return new JsonResponse(['success' => false, 'message' => 'No categories selected.']);
        }

        $categoryIdsArray = array_filter(array_map('intval', explode(',', $categoryIds)), function ($id) {
            return $id > 0;
        });

        if (empty($categoryIdsArray)) {
            return new JsonResponse(['success' => false, 'message' => 'No valid categories selected.']);
        }

        $context = Context::getContext();
        $idLang = $context->language ? (int) $context->language->id : (int) Configuration::get('PS_LANG_DEFAULT');

        /** @var \Symfony\Component\Routing\RouterInterface $router */
        $router = $this->get('router');

        return $this->processBulkGeneration($categoryIdsArray, $promptId, $idLang, $router);
    }

    /**
     * Build structured category data for API.
     *
     * @param Category $category Category instance
     * @param int $idLang Language ID
     *
     * @return array<string, mixed>
     */
    private function buildCategoryData(Category $category, int $idLang): array
    {
        $parentName = '';
        if ($category->id_parent > 0) {
            $parentCategory = new Category($category->id_parent, $idLang);
            if ($parentCategory->id) {
                $parentName = $parentCategory->name;
            }
        }

        $subcategories = [];
        $subCategoryIds = Category::getChildren((int) $category->id, $idLang, true);
        if (!empty($subCategoryIds)) {
            foreach ($subCategoryIds as $subCat) {
                $subcategories[] = $subCat['name'];
            }
        }

        $productCount = $category->getProducts($idLang, 1, 1, null, null, true);

        return [
            'name' => $category->name,
            'description' => strip_tags($category->description ?? ''),
            'additional_description' => strip_tags($category->additional_description ?? ''),
            'meta_title' => $category->meta_title ?? '',
            'meta_description' => $category->meta_description ?? '',
            'meta_keywords' => $category->meta_keywords ?? '',
            'link_rewrite' => $category->link_rewrite ?? '',
            'parent_category' => $parentName,
            'subcategories' => $subcategories,
            'product_count' => (int) $productCount,
        ];
    }

    /**
     * Extract descriptions from API response.
     *
     * @param array<string, mixed> $response
     *
     * @return array<int, array<string, mixed>>
     */
    private function extractDescriptions(array $response): array
    {
        // Root-level descriptions (new format)
        if (!empty($response['descriptions']) && is_array($response['descriptions'])) {
            return $response['descriptions'];
        }

        // Nested under data key (legacy format)
        $data = $response['data'] ?? [];

        if (!empty($data['descriptions']) && is_array($data['descriptions'])) {
            return $data['descriptions'];
        }

        if (!empty($data['description_long']) || !empty($data['description_short'])) {
            return [$data];
        }

        return [];
    }

    /**
     * Save a CategoryContent entity.
     *
     * @param int $idCategory Category ID
     * @param int|null $apiContentId API content ID
     * @param int $promptId Prompt ID
     * @param string $descriptionText Description text
     * @param string $descriptionShortText Short description text
     * @param array<int, array<string, mixed>> $languages Languages list
     * @param array<string, string> $seoData SEO data (meta_title, meta_description, meta_keywords, original_meta_*)
     *
     * @return CategoryContent|null
     */
    private function saveCategoryContent(
        int $idCategory,
        ?int $apiContentId,
        int $promptId,
        string $descriptionText,
        string $descriptionShortText,
        array $languages,
        array $seoData = []
    ): ?CategoryContent {
        $content = new CategoryContent();
        $content->id_category = $idCategory;
        $content->api_content_id = ($apiContentId !== null && $apiContentId > 0) ? (int) $apiContentId : 0;
        $content->content_type = CategoryContent::CONTENT_TYPE_DESCRIPTION;
        $content->status = CategoryContent::STATUS_PENDING;
        $content->prompt_id = $promptId;
        $content->active = 0;

        $content->original_meta_title = $seoData['original_meta_title'] ?? '';
        $content->original_meta_description = $seoData['original_meta_description'] ?? '';
        $content->original_meta_keywords = $seoData['original_meta_keywords'] ?? '';

        $content->generated_content = [];
        $content->generated_content_short = [];
        $content->meta_title = [];
        $content->meta_description = [];
        $content->meta_keywords = [];

        foreach ($languages as $lang) {
            $idLang = (int) $lang['id_lang'];
            $content->generated_content[$idLang] = $descriptionText;
            $content->generated_content_short[$idLang] = $descriptionShortText;
            $content->meta_title[$idLang] = $seoData['meta_title'] ?? '';
            $content->meta_description[$idLang] = $seoData['meta_description'] ?? '';
            $content->meta_keywords[$idLang] = $seoData['meta_keywords'] ?? '';
        }

        if ($content->add()) {
            return $content;
        }

        error_log('[ITRBLUEBOOST] CategoryContent::add() failed: ' . $content->last_error);

        return null;
    }

    /**
     * Apply content to category.
     *
     * @param CategoryContent $content
     *
     * @return array{success: bool, message?: string}
     */
    private function applyContentToCategory(CategoryContent $content): array
    {
        $category = new Category((int) $content->id_category);
        if (!$category->id) {
            return ['success' => false, 'message' => 'Category not found.'];
        }

        $this->applyCategoryDescription($category, $content->generated_content);
        $this->applyCategoryAdditionalDescription($category, $content->generated_content_short);
        $this->applyCategorySeo($category, $content);

        if (!$category->update()) {
            return ['success' => false, 'message' => 'Error updating category.'];
        }

        return ['success' => true];
    }

    /**
     * Apply description to category.
     *
     * @param Category $category
     * @param mixed $generatedContent
     */
    private function applyCategoryDescription(Category $category, $generatedContent): void
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
     * @param Category $category
     * @param mixed $generatedContentShort
     */
    private function applyCategoryAdditionalDescription(Category $category, $generatedContentShort): void
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

    /**
     * Apply SEO fields to category.
     *
     * @param Category $category
     * @param CategoryContent $content
     */
    private function applyCategorySeo(Category $category, CategoryContent $content): void
    {
        $this->applyMultilangField($category, 'meta_title', $content->meta_title);
        $this->applyMultilangField($category, 'meta_description', $content->meta_description);
        $this->applyMultilangField($category, 'meta_keywords', $content->meta_keywords);
    }

    /**
     * Apply a multilang value to a category field.
     *
     * @param Category $category
     * @param string $field
     * @param mixed $value
     */
    private function applyMultilangField(Category $category, string $field, $value): void
    {
        if (empty($value)) {
            return;
        }

        if (is_array($value)) {
            foreach ($value as $idLang => $text) {
                if ($text !== '') {
                    $category->{$field}[$idLang] = $text;
                }
            }
        } else {
            $category->{$field} = $value;
        }
    }

    /**
     * Handle edit form submission.
     *
     * @param \Symfony\Component\Form\FormInterface $form
     * @param CategoryContent $content
     * @param int $idCategory
     * @param int $contentId
     *
     * @return Response
     */
    private function handleEditFormSubmission($form, CategoryContent $content, int $idCategory, int $contentId): Response
    {
        $data = $form->getData();

        try {
            $contentChanged = $this->hasMultilangChanged($content->generated_content, $data['generated_content']);
            $shortChanged = $this->hasMultilangChanged($content->generated_content_short, $data['generated_content_short']);
            $seoChanged = $this->hasMultilangChanged($content->meta_title, $data['meta_title'])
                || $this->hasMultilangChanged($content->meta_description, $data['meta_description'])
                || $this->hasMultilangChanged($content->meta_keywords, $data['meta_keywords']);

            $content->generated_content = $data['generated_content'];
            $content->generated_content_short = $data['generated_content_short'];
            $content->meta_title = $data['meta_title'];
            $content->meta_description = $data['meta_description'];
            $content->meta_keywords = $data['meta_keywords'];

            if ($content->hasApiContentId() && ($contentChanged || $shortChanged || $seoChanged)) {
                $modificationReason = $data['modification_reason'] ?? '';

                if (empty(trim($modificationReason))) {
                    $this->addFlash('error', $this->trans('Modification reason is required when content is changed.', 'Modules.Itrblueboost.Admin'));
                    return $this->render('@Modules/itrblueboost/views/templates/admin/category_content/form.html.twig', [
                        'form' => $form->createView(),
                        'id_category' => $idCategory,
                        'contentId' => $contentId,
                        'content' => $content,
                        'layoutTitle' => $this->trans('Edit Content', 'Modules.Itrblueboost.Admin'),
                    ]);
                }

                $idLang = (int) Configuration::get('PS_LANG_DEFAULT');
                $this->updateContentOnApi((int) $content->api_content_id, [
                    'content' => $this->resolveMultilangText($data['generated_content'], $idLang),
                    'modification_reason' => $modificationReason,
                ]);
            }

            if ($content->update()) {
                $this->addFlash('success', $this->trans('Content updated.', 'Modules.Itrblueboost.Admin'));
                return $this->redirectToRoute('itrblueboost_admin_category_content_index', ['id_category' => $idCategory]);
            }
            $this->addFlash('error', $this->trans('Error updating content.', 'Modules.Itrblueboost.Admin'));
        } catch (\Exception $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->render('@Modules/itrblueboost/views/templates/admin/category_content/form.html.twig', [
            'form' => $form->createView(),
            'id_category' => $idCategory,
            'contentId' => $contentId,
            'content' => $content,
            'layoutTitle' => $this->trans('Edit Content', 'Modules.Itrblueboost.Admin'),
        ]);
    }

    /**
     * Process bulk generation for multiple categories.
     *
     * @param array<int> $categoryIdsArray
     * @param int $promptId
     * @param int $idLang
     * @param \Symfony\Component\Routing\RouterInterface $router
     *
     * @return JsonResponse
     */
    private function processBulkGeneration(array $categoryIdsArray, int $promptId, int $idLang, $router): JsonResponse
    {
        $totalCreated = 0;
        $totalCreditsUsed = 0;
        $creditsRemaining = 0;
        $errors = [];
        $processedItems = [];

        foreach ($categoryIdsArray as $idCategory) {
            $category = new Category($idCategory, $idLang);
            if (!$category->id || !\Validate::isLoadedObject($category)) {
                $errors[] = sprintf('Category ID %d not found.', $idCategory);
                continue;
            }

            $categoryData = $this->buildCategoryData($category, $idLang);
            $response = $this->apiLogger->generateCategoryContent($promptId, $categoryData, $idCategory);

            if (!isset($response['success']) || !$response['success']) {
                $errors[] = sprintf('Category "%s" (ID %d): %s', $category->name, $idCategory, $response['message'] ?? 'Error');
                continue;
            }

            $descriptions = $this->extractDescriptions($response);
            $createdCount = 0;

            if (!empty($descriptions)) {
                $firstDesc = $descriptions[0];
                $languages = \Language::getLanguages(false);

                $bulkSeoData = [
                    'meta_title' => $firstDesc['meta_title'] ?? '',
                    'meta_description' => $firstDesc['meta_description'] ?? '',
                    'meta_keywords' => $firstDesc['meta_keywords'] ?? '',
                    'original_meta_title' => $firstDesc['original_meta_title'] ?? '',
                    'original_meta_description' => $firstDesc['original_meta_description'] ?? '',
                    'original_meta_keywords' => $firstDesc['original_meta_keywords'] ?? '',
                ];

                $saved = $this->saveCategoryContent(
                    $idCategory,
                    $firstDesc['id'] ?? null,
                    $promptId,
                    $firstDesc['description_long'] ?? '',
                    $firstDesc['description_short'] ?? '',
                    $languages,
                    $bulkSeoData
                );

                if ($saved) {
                    $createdCount = 1;
                }
            }

            $totalCreated += $createdCount;
            $totalCreditsUsed += $response['credits_used'] ?? 0;
            $creditsRemaining = $response['credits_remaining'] ?? 0;

            $processedItems[] = [
                'id' => $idCategory,
                'name' => $category->name,
                'content_count' => $createdCount,
                'content_url' => $router->generate('itrblueboost_admin_category_content_index', [
                    'id_category' => $idCategory,
                ]),
            ];
        }

        $message = sprintf('%d contents generated for %d categories.', $totalCreated, count($processedItems));
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
            'processed_items' => $processedItems,
        ]);
    }
}
