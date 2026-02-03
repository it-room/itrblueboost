<?php

declare(strict_types=1);

namespace Itrblueboost\Controller\Admin;

use Configuration;
use Context;
use Itrblueboost\Entity\ProductFaq;
use Itrblueboost\Form\ProductFaqType;
use Itrblueboost\Grid\Definition\Factory\ProductFaqGridDefinitionFactory;
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
 * Controller for product FAQ management.
 */
class ProductFaqController extends FrameworkBundleAdminController
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
        $gridFactory = $this->get('itrblueboost.grid.factory.product_faq');

        $filters = [
            'id_product' => $id_product,
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
        $productEditUrl = $baseUrl . '/sell/catalog/products-v2/' . $id_product . '/edit?_token=' . $token;

        return $this->render('@Modules/itrblueboost/views/templates/admin/product_faq/index.html.twig', [
            'grid' => $this->presentGrid($grid),
            'id_product' => $id_product,
            'product_edit_url' => $productEditUrl,
            'layoutTitle' => $this->trans('Product FAQ', 'Modules.Itrblueboost.Admin'),
        ]);
    }

    /**
     * @AdminSecurity("is_granted('read', request.get('_legacy_controller'))")
     */
    public function filterAction(Request $request, int $id_product): RedirectResponse
    {
        $filterId = ProductFaqGridDefinitionFactory::GRID_ID . '_' . $id_product;

        return $this->redirectToRoute('itrblueboost_admin_product_faq_index', ['id_product' => $id_product]);
    }

    /**
     * @AdminSecurity("is_granted('create', request.get('_legacy_controller'))")
     */
    public function createAction(Request $request, int $id_product): Response
    {
        $form = $this->createForm(ProductFaqType::class, [
            'id_product' => $id_product,
            'active' => false,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();

            try {
                $faq = new ProductFaq();
                $faq->id_product = (int) $data['id_product'];
                $faq->active = (bool) $data['active'];
                $faq->status = $data['active'] ? ProductFaq::STATUS_ACCEPTED : ProductFaq::STATUS_PENDING;
                $faq->question = $data['question'];
                $faq->answer = $data['answer'];

                if ($faq->add()) {
                    $this->addFlash('success', $this->trans('FAQ created successfully.', 'Modules.Itrblueboost.Admin'));
                    return $this->redirectToRoute('itrblueboost_admin_product_faq_index', ['id_product' => $id_product]);
                }
                $this->addFlash('error', $this->trans('Error creating FAQ.', 'Modules.Itrblueboost.Admin'));
            } catch (\Exception $e) {
                $this->addFlash('error', $e->getMessage());
            }
        }

        return $this->render('@Modules/itrblueboost/views/templates/admin/product_faq/form.html.twig', [
            'form' => $form->createView(),
            'id_product' => $id_product,
            'layoutTitle' => $this->trans('Add FAQ', 'Modules.Itrblueboost.Admin'),
        ]);
    }

    /**
     * @AdminSecurity("is_granted('update', request.get('_legacy_controller'))")
     */
    public function editAction(Request $request, int $id_product, int $faqId): Response
    {
        $faq = new ProductFaq($faqId);

        if (!$faq->id) {
            $this->addFlash('error', $this->trans('FAQ not found.', 'Modules.Itrblueboost.Admin'));
            return $this->redirectToRoute('itrblueboost_admin_product_faq_index', ['id_product' => $id_product]);
        }

        $formData = [
            'id_product' => $faq->id_product,
            'question' => $faq->question,
            'answer' => $faq->answer,
            'active' => (bool) $faq->active,
            'modification_reason' => '',
        ];

        $form = $this->createForm(ProductFaqType::class, $formData, [
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
                        return $this->render('@Modules/itrblueboost/views/templates/admin/product_faq/form.html.twig', [
                            'form' => $form->createView(),
                            'id_product' => $id_product,
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
                    return $this->redirectToRoute('itrblueboost_admin_product_faq_index', ['id_product' => $id_product]);
                }
                $this->addFlash('error', $this->trans('Error updating FAQ.', 'Modules.Itrblueboost.Admin'));
            } catch (\Exception $e) {
                $this->addFlash('error', $e->getMessage());
            }
        }

        return $this->render('@Modules/itrblueboost/views/templates/admin/product_faq/form.html.twig', [
            'form' => $form->createView(),
            'id_product' => $id_product,
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
    public function acceptAction(Request $request, int $id_product, int $faqId): JsonResponse
    {
        $faq = new ProductFaq($faqId);

        if (!$faq->id || (int) $faq->id_product !== $id_product) {
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

        // Update local status
        $faq->status = ProductFaq::STATUS_ACCEPTED;
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
    public function toggleActiveAction(Request $request, int $id_product, int $faqId): JsonResponse
    {
        $faq = new ProductFaq($faqId);

        if (!$faq->id || (int) $faq->id_product !== $id_product) {
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
    public function rejectAction(Request $request, int $id_product, int $faqId): JsonResponse
    {
        $faq = new ProductFaq($faqId);

        if (!$faq->id || (int) $faq->id_product !== $id_product) {
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
    public function deleteAction(Request $request, int $id_product, int $faqId): RedirectResponse
    {
        $faq = new ProductFaq($faqId);

        if (!$faq->id) {
            $this->addFlash('error', $this->trans('FAQ not found.', 'Modules.Itrblueboost.Admin'));
            return $this->redirectToRoute('itrblueboost_admin_product_faq_index', ['id_product' => $id_product]);
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

        return $this->redirectToRoute('itrblueboost_admin_product_faq_index', ['id_product' => $id_product]);
    }

    /**
     * @AdminSecurity("is_granted('update', request.get('_legacy_controller'))")
     */
    public function bulkAcceptAction(Request $request): RedirectResponse
    {
        $faqIds = $request->request->all()['itrblueboost_product_faq_bulk'] ?? [];
        $idProduct = (int) $request->request->get('id_product', 0);

        if ($idProduct <= 0) {
            $idProduct = $this->getIdProductFromFaqs($faqIds);
        }

        if (empty($faqIds)) {
            $this->addFlash('warning', $this->trans('No FAQ selected.', 'Modules.Itrblueboost.Admin'));
            return $this->redirectToRoute('itrblueboost_admin_product_faq_index', ['id_product' => $idProduct]);
        }

        $count = 0;
        foreach ($faqIds as $faqId) {
            $faq = new ProductFaq((int) $faqId);
            if ($faq->id && $faq->status === ProductFaq::STATUS_PENDING) {
                $faq->status = ProductFaq::STATUS_ACCEPTED;
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

        return $this->redirectToRoute('itrblueboost_admin_product_faq_index', ['id_product' => $idProduct]);
    }

    /**
     * @AdminSecurity("is_granted('update', request.get('_legacy_controller'))")
     */
    public function bulkRejectAction(Request $request)
    {
        $isAjax = $request->isXmlHttpRequest();

        // Handle both form submission and AJAX
        $faqIds = $request->request->all()['itrblueboost_product_faq_bulk'] ?? [];
        if (empty($faqIds)) {
            // Try AJAX format (comma-separated string)
            $faqIdsString = $request->request->get('faq_ids', '');
            if (!empty($faqIdsString)) {
                $faqIds = explode(',', $faqIdsString);
            }
        }

        $idProduct = (int) $request->request->get('id_product', 0);
        $rejectionReason = $request->request->get('rejection_reason', 'Bulk rejection');

        if ($idProduct <= 0) {
            $idProduct = $this->getIdProductFromFaqs($faqIds);
        }

        if (empty($faqIds)) {
            if ($isAjax) {
                return new JsonResponse([
                    'success' => false,
                    'message' => $this->trans('No FAQ selected.', 'Modules.Itrblueboost.Admin'),
                ]);
            }
            $this->addFlash('warning', $this->trans('No FAQ selected.', 'Modules.Itrblueboost.Admin'));
            return $this->redirectToRoute('itrblueboost_admin_product_faq_index', ['id_product' => $idProduct]);
        }

        $count = 0;
        foreach ($faqIds as $faqId) {
            $faq = new ProductFaq((int) $faqId);
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

        return $this->redirectToRoute('itrblueboost_admin_product_faq_index', ['id_product' => $idProduct]);
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

            $response = $this->apiLogger->generateProductFaq($promptId, $productData, $idProduct);

            if (!isset($response['success']) || !$response['success']) {
                $errorMessage = $response['message'] ?? 'Error generating FAQs.';
                $errors[] = sprintf('Product "%s" (ID %d): %s', $product->name, $idProduct, $errorMessage);
                continue;
            }

            $faqData = $response['data']['mainEntity'] ?? [];
            $createdCount = 0;

            foreach ($faqData as $item) {
                if (!isset($item['name']) || !isset($item['id'])) {
                    continue;
                }

                $faq = new ProductFaq();
                $faq->id_product = $idProduct;
                $faq->api_faq_id = (int) $item['id'];
                $faq->status = ProductFaq::STATUS_PENDING;
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
        }

        $message = sprintf('%d FAQs generated for %d products.', $totalCreated, count($productIdsArray) - count($errors));

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
            'products_processed' => count($productIdsArray) - count($errors),
            'products_failed' => count($errors),
        ]);
    }

    /**
     * Get id_product from the first FAQ in the list.
     *
     * @param array<int, mixed> $faqIds
     * @return int
     */
    private function getIdProductFromFaqs(array $faqIds): int
    {
        if (empty($faqIds)) {
            return 0;
        }

        $firstFaq = new ProductFaq((int) reset($faqIds));
        return $firstFaq->id ? (int) $firstFaq->id_product : 0;
    }

    /**
     * @AdminSecurity("is_granted('read', request.get('_legacy_controller'))")
     */
    public function getPromptsAction(): JsonResponse
    {
        $response = $this->apiLogger->getFaqPrompts('product_faq');

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
    public function generateAction(Request $request, int $id_product): JsonResponse
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

        $product = new Product($id_product, false, $idLang);
        if (!$product->id || !\Validate::isLoadedObject($product)) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Product not found (ID: ' . $id_product . ').',
            ]);
        }

        $productData = $this->buildProductData($product, $idLang);

        $response = $this->apiLogger->generateProductFaq($promptId, $productData, $id_product);

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

            $faq = new ProductFaq();
            $faq->id_product = $id_product;
            $faq->api_faq_id = (int) $item['id'];
            $faq->status = ProductFaq::STATUS_PENDING;
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
        $response = $this->apiLogger->updateFaq($apiFaqId, $data, 'product_faq');

        if (!isset($response['success']) || !$response['success']) {
            return ['success' => false, 'message' => $response['message'] ?? 'Unknown error'];
        }

        return ['success' => true];
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
        // Get manufacturer/brand name
        $brandName = '';
        if ($product->id_manufacturer > 0) {
            $manufacturer = new \Manufacturer($product->id_manufacturer, $idLang);
            if ($manufacturer->id) {
                $brandName = $manufacturer->name;
            }
        }

        // Get default category name
        $categoryName = '';
        if ($product->id_category_default > 0) {
            $category = new \Category($product->id_category_default, $idLang);
            if ($category->id) {
                $categoryName = $category->name;
            }
        }

        // Get product features
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

        // Get combinations (variants)
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

        // Get product price
        $productPrice = $product->getPrice(true, null, 2);

        // Get product URL
        $productUrl = $product->getLink();

        $data = [
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

        return $data;
    }
}
