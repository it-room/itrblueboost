<?php

declare(strict_types=1);

namespace Itrblueboost\Controller\Admin;

use Category;
use Configuration;
use Context;
use Itrblueboost\Entity\CategoryFaq;
use Itrblueboost\Form\CategoryFaqType;
use Itrblueboost\Grid\Definition\Factory\CategoryFaqGridDefinitionFactory;
use Itrblueboost\Service\ApiLogger;
use PrestaShop\PrestaShop\Core\Grid\Search\SearchCriteria;
use PrestaShopBundle\Controller\Admin\FrameworkBundleAdminController;
use PrestaShopBundle\Security\Annotation\AdminSecurity;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Controller for category FAQ management.
 */
class CategoryFaqController extends FrameworkBundleAdminController
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
    public function indexAction(Request $request, int $id_category): Response
    {
        $gridFactory = $this->get('itrblueboost.grid.factory.category_faq');

        $filters = [
            'id_category' => $id_category,
        ];

        $searchCriteria = new SearchCriteria(
            $filters,
            'position',
            'asc',
            0,
            50
        );

        $grid = $gridFactory->getGrid($searchCriteria);

        $baseUrl = $request->getBaseUrl();
        $token = $this->get('security.csrf.token_manager')->getToken('_token')->getValue();
        $categoryEditUrl = $baseUrl . '/sell/catalog/categories/' . $id_category . '/edit?_token=' . $token;

        return $this->render('@Modules/itrblueboost/views/templates/admin/category_faq/index.html.twig', [
            'grid' => $this->presentGrid($grid),
            'id_category' => $id_category,
            'category_edit_url' => $categoryEditUrl,
            'layoutTitle' => $this->trans('Category FAQ', 'Modules.Itrblueboost.Admin'),
        ]);
    }

    /**
     * @AdminSecurity("is_granted('read', request.get('_legacy_controller'))")
     */
    public function filterAction(Request $request, int $id_category): RedirectResponse
    {
        $filterId = CategoryFaqGridDefinitionFactory::GRID_ID . '_' . $id_category;

        return $this->redirectToRoute('itrblueboost_admin_category_faq_index', ['id_category' => $id_category]);
    }

    /**
     * @AdminSecurity("is_granted('create', request.get('_legacy_controller'))")
     */
    public function createAction(Request $request, int $id_category): Response
    {
        $form = $this->createForm(CategoryFaqType::class, [
            'id_category' => $id_category,
            'active' => false,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();

            try {
                $faq = new CategoryFaq();
                $faq->id_category = (int) $data['id_category'];
                $faq->active = (bool) $data['active'];
                $faq->status = $data['active'] ? CategoryFaq::STATUS_ACCEPTED : CategoryFaq::STATUS_PENDING;
                $faq->question = $data['question'];
                $faq->answer = $data['answer'];

                if ($faq->add()) {
                    $this->addFlash('success', $this->trans('FAQ created successfully.', 'Modules.Itrblueboost.Admin'));
                    return $this->redirectToRoute('itrblueboost_admin_category_faq_index', ['id_category' => $id_category]);
                }
                $this->addFlash('error', $this->trans('Error creating FAQ.', 'Modules.Itrblueboost.Admin'));
            } catch (\Exception $e) {
                $this->addFlash('error', $e->getMessage());
            }
        }

        return $this->render('@Modules/itrblueboost/views/templates/admin/category_faq/form.html.twig', [
            'form' => $form->createView(),
            'id_category' => $id_category,
            'layoutTitle' => $this->trans('Add FAQ', 'Modules.Itrblueboost.Admin'),
        ]);
    }

    /**
     * @AdminSecurity("is_granted('update', request.get('_legacy_controller'))")
     */
    public function editAction(Request $request, int $id_category, int $faqId): Response
    {
        $faq = new CategoryFaq($faqId);

        if (!$faq->id) {
            $this->addFlash('error', $this->trans('FAQ not found.', 'Modules.Itrblueboost.Admin'));
            return $this->redirectToRoute('itrblueboost_admin_category_faq_index', ['id_category' => $id_category]);
        }

        $formData = [
            'id_category' => $faq->id_category,
            'question' => $faq->question,
            'answer' => $faq->answer,
            'active' => (bool) $faq->active,
            'modification_reason' => '',
        ];

        $form = $this->createForm(CategoryFaqType::class, $formData, [
            'show_modification_reason' => $faq->hasApiFaqId(),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();

            try {
                // Check if question or answer changed
                $questionChanged = $this->hasMultilangChanged($faq->question, $data['question']);
                $answerChanged = $this->hasMultilangChanged($faq->answer, $data['answer']);
                $contentChanged = $questionChanged || $answerChanged;

                $faq->active = (bool) $data['active'];
                $faq->question = $data['question'];
                $faq->answer = $data['answer'];

                // If FAQ has API ID and content changed, sync with API
                if ($faq->hasApiFaqId() && $contentChanged) {
                    $modificationReason = $data['modification_reason'] ?? '';

                    // Require modification reason when content is changed for API-linked FAQs
                    if (empty(trim($modificationReason))) {
                        $this->addFlash('error', $this->trans('La raison de la modification est obligatoire car vous avez modifié la question ou la réponse.', 'Modules.Itrblueboost.Admin'));
                        return $this->render('@Modules/itrblueboost/views/templates/admin/category_faq/form.html.twig', [
                            'form' => $form->createView(),
                            'id_category' => $id_category,
                            'faqId' => $faqId,
                            'faq' => $faq,
                            'layoutTitle' => $this->trans('Edit FAQ', 'Modules.Itrblueboost.Admin'),
                        ]);
                    }

                    $idLang = (int) Configuration::get('PS_LANG_DEFAULT');

                    $questionText = is_array($data['question']) ? ($data['question'][$idLang] ?? reset($data['question'])) : $data['question'];
                    $answerText = is_array($data['answer']) ? ($data['answer'][$idLang] ?? reset($data['answer'])) : $data['answer'];

                    $apiData = [
                        'question' => $questionText,
                        'answer' => $answerText,
                        'is_enabled' => (bool) $faq->active,
                        'modification_reason' => $modificationReason,
                    ];

                    $this->updateFaqOnApi((int) $faq->api_faq_id, $apiData);
                }

                if ($faq->update()) {
                    $this->addFlash('success', $this->trans('FAQ updated.', 'Modules.Itrblueboost.Admin'));
                    return $this->redirectToRoute('itrblueboost_admin_category_faq_index', ['id_category' => $id_category]);
                }
                $this->addFlash('error', $this->trans('Error updating FAQ.', 'Modules.Itrblueboost.Admin'));
            } catch (\Exception $e) {
                $this->addFlash('error', $e->getMessage());
            }
        }

        return $this->render('@Modules/itrblueboost/views/templates/admin/category_faq/form.html.twig', [
            'form' => $form->createView(),
            'id_category' => $id_category,
            'faqId' => $faqId,
            'faq' => $faq,
            'layoutTitle' => $this->trans('Edit FAQ', 'Modules.Itrblueboost.Admin'),
        ]);
    }

    /**
     * Check if multilang field has changed.
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
     * @AdminSecurity("is_granted('update', request.get('_legacy_controller'))")
     */
    public function acceptAction(Request $request, int $id_category, int $faqId): JsonResponse
    {
        $faq = new CategoryFaq($faqId);

        if (!$faq->id || (int) $faq->id_category !== $id_category) {
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

        // Update local status
        $faq->status = CategoryFaq::STATUS_ACCEPTED;
        $faq->active = true;

        // Sync with API if has API ID
        if ($faq->hasApiFaqId()) {
            $idLang = (int) Configuration::get('PS_LANG_DEFAULT');
            $questionText = is_array($faq->question) ? ($faq->question[$idLang] ?? reset($faq->question)) : $faq->question;
            $answerText = is_array($faq->answer) ? ($faq->answer[$idLang] ?? reset($faq->answer)) : $faq->answer;

            $apiResult = $this->updateFaqOnApi((int) $faq->api_faq_id, [
                'status' => 'accepted',
                'is_enabled' => true,
                'question' => $questionText,
                'answer' => $answerText,
            ]);

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
            'message' => 'FAQ accepted and activated.',
        ]);
    }

    /**
     * @AdminSecurity("is_granted('update', request.get('_legacy_controller'))")
     */
    public function toggleActiveAction(Request $request, int $id_category, int $faqId): JsonResponse
    {
        $faq = new CategoryFaq($faqId);

        if (!$faq->id || (int) $faq->id_category !== $id_category) {
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

        // Toggle the active status
        $faq->active = !$faq->active;

        // Sync with API if has API ID
        if ($faq->hasApiFaqId()) {
            $this->updateFaqOnApi((int) $faq->api_faq_id, [
                'is_enabled' => (bool) $faq->active,
            ]);
        }

        if (!$faq->update()) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Error updating FAQ.',
            ]);
        }

        return new JsonResponse([
            'success' => true,
            'message' => $faq->active ? 'FAQ activated.' : 'FAQ deactivated.',
            'active' => (bool) $faq->active,
        ]);
    }

    /**
     * @AdminSecurity("is_granted('update', request.get('_legacy_controller'))")
     */
    public function rejectAction(Request $request, int $id_category, int $faqId): JsonResponse
    {
        $faq = new CategoryFaq($faqId);

        if (!$faq->id || (int) $faq->id_category !== $id_category) {
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
            ]);

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
     * @AdminSecurity("is_granted('delete', request.get('_legacy_controller'))")
     */
    public function deleteAction(Request $request, int $id_category, int $faqId): RedirectResponse
    {
        $faq = new CategoryFaq($faqId);

        if (!$faq->id) {
            $this->addFlash('error', $this->trans('FAQ not found.', 'Modules.Itrblueboost.Admin'));
            return $this->redirectToRoute('itrblueboost_admin_category_faq_index', ['id_category' => $id_category]);
        }

        try {
            // If has API ID, sync deletion as rejection
            if ($faq->hasApiFaqId()) {
                $this->updateFaqOnApi((int) $faq->api_faq_id, [
                    'status' => 'rejected',
                    'rejection_reason' => 'Deleted by user',
                    'is_enabled' => false,
                ]);
            }

            if ($faq->delete()) {
                $this->addFlash('success', $this->trans('FAQ deleted.', 'Modules.Itrblueboost.Admin'));
            } else {
                $this->addFlash('error', $this->trans('Error deleting FAQ.', 'Modules.Itrblueboost.Admin'));
            }
        } catch (\Exception $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('itrblueboost_admin_category_faq_index', ['id_category' => $id_category]);
    }

    /**
     * @AdminSecurity("is_granted('update', request.get('_legacy_controller'))")
     */
    public function bulkAcceptAction(Request $request): RedirectResponse
    {
        $faqIds = $request->request->all()['itrblueboost_category_faq_bulk'] ?? [];
        $idCategory = (int) $request->request->get('id_category', 0);

        if ($idCategory <= 0) {
            $idCategory = $this->getIdCategoryFromFaqs($faqIds);
        }

        if (empty($faqIds)) {
            $this->addFlash('warning', $this->trans('No FAQ selected.', 'Modules.Itrblueboost.Admin'));
            return $this->redirectToRoute('itrblueboost_admin_category_faq_index', ['id_category' => $idCategory]);
        }

        $count = 0;
        foreach ($faqIds as $faqId) {
            $faq = new CategoryFaq((int) $faqId);
            if ($faq->id && $faq->status === CategoryFaq::STATUS_PENDING) {
                $faq->status = CategoryFaq::STATUS_ACCEPTED;
                $faq->active = true;

                if ($faq->hasApiFaqId()) {
                    $idLang = (int) Configuration::get('PS_LANG_DEFAULT');
                    $questionText = is_array($faq->question) ? ($faq->question[$idLang] ?? reset($faq->question)) : $faq->question;
                    $answerText = is_array($faq->answer) ? ($faq->answer[$idLang] ?? reset($faq->answer)) : $faq->answer;

                    $this->updateFaqOnApi((int) $faq->api_faq_id, [
                        'status' => 'accepted',
                        'is_enabled' => true,
                        'question' => $questionText,
                        'answer' => $answerText,
                    ]);
                }

                if ($faq->update()) {
                    $count++;
                }
            }
        }

        $this->addFlash('success', sprintf($this->trans('%d FAQs accepted.', 'Modules.Itrblueboost.Admin'), $count));

        return $this->redirectToRoute('itrblueboost_admin_category_faq_index', ['id_category' => $idCategory]);
    }

    /**
     * @AdminSecurity("is_granted('update', request.get('_legacy_controller'))")
     *
     * @return RedirectResponse|JsonResponse
     */
    public function bulkRejectAction(Request $request)
    {
        $isAjax = $request->isXmlHttpRequest();

        // Handle both form submission and AJAX
        $faqIds = $request->request->all()['itrblueboost_category_faq_bulk'] ?? [];
        if (empty($faqIds)) {
            // Try AJAX format (comma-separated string)
            $faqIdsString = $request->request->get('faq_ids', '');
            if (!empty($faqIdsString)) {
                $faqIds = explode(',', $faqIdsString);
            }
        }

        $idCategory = (int) $request->request->get('id_category', 0);
        $rejectionReason = $request->request->get('rejection_reason', 'Bulk rejection');

        if ($idCategory <= 0) {
            $idCategory = $this->getIdCategoryFromFaqs($faqIds);
        }

        if (empty($faqIds)) {
            if ($isAjax) {
                return new JsonResponse([
                    'success' => false,
                    'message' => $this->trans('No FAQ selected.', 'Modules.Itrblueboost.Admin'),
                ]);
            }
            $this->addFlash('warning', $this->trans('No FAQ selected.', 'Modules.Itrblueboost.Admin'));
            return $this->redirectToRoute('itrblueboost_admin_category_faq_index', ['id_category' => $idCategory]);
        }

        $count = 0;
        foreach ($faqIds as $faqId) {
            $faq = new CategoryFaq((int) $faqId);
            if ($faq->id) {
                if ($faq->hasApiFaqId()) {
                    $this->updateFaqOnApi((int) $faq->api_faq_id, [
                        'status' => 'rejected',
                        'rejection_reason' => $rejectionReason,
                        'is_enabled' => false,
                    ]);
                }

                if ($faq->delete()) {
                    $count++;
                }
            }
        }

        if ($isAjax) {
            return new JsonResponse([
                'success' => true,
                'message' => sprintf($this->trans('%d FAQs rejected.', 'Modules.Itrblueboost.Admin'), $count),
            ]);
        }

        $this->addFlash('success', sprintf($this->trans('%d FAQs rejected.', 'Modules.Itrblueboost.Admin'), $count));

        return $this->redirectToRoute('itrblueboost_admin_category_faq_index', ['id_category' => $idCategory]);
    }

    /**
     * @AdminSecurity("is_granted('delete', request.get('_legacy_controller'))")
     */
    public function bulkDeleteAction(Request $request): RedirectResponse
    {
        return $this->bulkRejectAction($request);
    }

    /**
     * @AdminSecurity("is_granted('create', request.get('_legacy_controller'))")
     */
    public function bulkGenerateAction(Request $request): JsonResponse
    {
        $promptId = (int) $request->request->get('prompt_id');
        if ($promptId <= 0) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Prompt not selected.',
            ]);
        }

        $categoryIds = $request->request->get('category_ids', '');
        if (empty($categoryIds)) {
            return new JsonResponse([
                'success' => false,
                'message' => 'No categories selected.',
            ]);
        }

        $categoryIdsArray = array_map('intval', explode(',', $categoryIds));
        $categoryIdsArray = array_filter($categoryIdsArray, function ($id) {
            return $id > 0;
        });

        if (empty($categoryIdsArray)) {
            return new JsonResponse([
                'success' => false,
                'message' => 'No valid categories selected.',
            ]);
        }

        $context = Context::getContext();
        $idLang = $context->language ? (int) $context->language->id : (int) Configuration::get('PS_LANG_DEFAULT');

        $totalCreated = 0;
        $totalCreditsUsed = 0;
        $creditsRemaining = 0;
        $errors = [];
        $processedItems = [];

        /** @var \Symfony\Component\Routing\RouterInterface $router */
        $router = $this->get('router');

        foreach ($categoryIdsArray as $idCategory) {
            $category = new Category($idCategory, $idLang);
            if (!$category->id || !\Validate::isLoadedObject($category)) {
                $errors[] = sprintf('Category ID %d not found.', $idCategory);
                continue;
            }

            $categoryData = $this->buildCategoryData($category, $idLang);

            $response = $this->apiLogger->generateCategoryFaq($promptId, $categoryData, $idCategory);

            if (!isset($response['success']) || !$response['success']) {
                $errorMessage = $response['message'] ?? 'Error generating FAQs.';
                $errors[] = sprintf('Category "%s" (ID %d): %s', $category->name, $idCategory, $errorMessage);
                continue;
            }

            $faqData = $response['data']['mainEntity'] ?? [];
            $createdCount = 0;

            foreach ($faqData as $item) {
                if (!isset($item['name']) || !isset($item['id'])) {
                    continue;
                }

                $faq = new CategoryFaq();
                $faq->id_category = $idCategory;
                $faq->api_faq_id = (int) $item['id'];
                $faq->status = CategoryFaq::STATUS_PENDING;
                $faq->active = false;

                $faq->question = [];
                $faq->answer = [];

                $answerText = $item['acceptedAnswer']['text'] ?? '';

                foreach (\Language::getLanguages(false) as $lang) {
                    $faq->question[$lang['id_lang']] = $item['name'];
                    $faq->answer[$lang['id_lang']] = $answerText;
                }

                if ($faq->add()) {
                    $createdCount++;
                }
            }

            $totalCreated += $createdCount;
            $totalCreditsUsed += $response['credits_used'] ?? 0;
            $creditsRemaining = $response['credits_remaining'] ?? 0;

            $processedItems[] = [
                'id' => $idCategory,
                'name' => $category->name,
                'faq_count' => $createdCount,
                'faq_url' => $router->generate('itrblueboost_admin_category_faq_index', [
                    'id_category' => $idCategory,
                ]),
            ];
        }

        $message = sprintf('%d FAQs generated for %d categories.', $totalCreated, count($processedItems));

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
            'categories_processed' => count($processedItems),
            'categories_failed' => count($errors),
        ]);
    }

    /**
     * Get id_category from the first FAQ in the list.
     *
     * @param array<int, mixed> $faqIds
     * @return int
     */
    private function getIdCategoryFromFaqs(array $faqIds): int
    {
        if (empty($faqIds)) {
            return 0;
        }

        $firstFaq = new CategoryFaq((int) reset($faqIds));
        return $firstFaq->id ? (int) $firstFaq->id_category : 0;
    }

    /**
     * @AdminSecurity("is_granted('read', request.get('_legacy_controller'))")
     */
    public function getPromptsAction(): JsonResponse
    {
        $response = $this->apiLogger->getFaqPrompts('category_faq');

        if (!isset($response['success']) || !$response['success']) {
            return new JsonResponse([
                'success' => false,
                'message' => $response['message'] ?? 'Error retrieving prompts.',
            ]);
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

        $response = $this->apiLogger->generateCategoryFaq($promptId, $categoryData, $id_category);

        if (!isset($response['success']) || !$response['success']) {
            $errorMessage = $response['message'] ?? 'Error generating FAQs.';
            return new JsonResponse([
                'success' => false,
                'message' => $errorMessage,
            ]);
        }

        $faqData = $response['data']['mainEntity'] ?? [];
        $createdCount = 0;

        foreach ($faqData as $item) {
            if (!isset($item['name']) || !isset($item['id'])) {
                continue;
            }

            $faq = new CategoryFaq();
            $faq->id_category = $id_category;
            $faq->api_faq_id = (int) $item['id'];
            $faq->status = CategoryFaq::STATUS_PENDING;
            $faq->active = false; // Disabled by default, must be accepted

            $faq->question = [];
            $faq->answer = [];

            $answerText = $item['acceptedAnswer']['text'] ?? '';

            foreach (\Language::getLanguages(false) as $lang) {
                $faq->question[$lang['id_lang']] = $item['name'];
                $faq->answer[$lang['id_lang']] = $answerText;
            }

            if ($faq->add()) {
                $createdCount++;
            }
        }

        return new JsonResponse([
            'success' => true,
            'message' => sprintf('%d FAQs generated (pending approval).', $createdCount),
            'credits_used' => $response['credits_used'] ?? 0,
            'credits_remaining' => $response['credits_remaining'] ?? 0,
        ]);
    }

    /**
     * Update FAQ on API.
     *
     * @param int $apiFaqId API FAQ ID
     * @param array<string, mixed> $data Data to send
     *
     * @return array{success: bool, message?: string}
     */
    private function updateFaqOnApi(int $apiFaqId, array $data): array
    {
        $response = $this->apiLogger->updateFaq($apiFaqId, $data, 'category_faq');

        if (!isset($response['success']) || !$response['success']) {
            return ['success' => false, 'message' => $response['message'] ?? 'Unknown error'];
        }

        return ['success' => true];
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
        // Get parent category name
        $parentName = '';
        if ($category->id_parent > 0) {
            $parentCategory = new Category($category->id_parent, $idLang);
            if ($parentCategory->id) {
                $parentName = $parentCategory->name;
            }
        }

        // Get subcategory names
        $subcategories = [];
        $subCategoryIds = Category::getChildren((int) $category->id, $idLang, true);
        if (!empty($subCategoryIds)) {
            foreach ($subCategoryIds as $subCat) {
                $subcategories[] = $subCat['name'];
            }
        }

        // Get product count
        $productCount = $category->getProducts($idLang, 1, 1, null, null, true);

        // Get category URL
        $categoryUrl = $category->getLink();

        $data = [
            'name' => $category->name,
            'description' => strip_tags($category->description ?? ''),
            'parent' => $parentName,
            'subcategories' => $subcategories,
            'product_count' => (int) $productCount,
            'url' => $categoryUrl,
        ];

        return $data;
    }
}
