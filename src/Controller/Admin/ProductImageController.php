<?php

declare(strict_types=1);

namespace Itrblueboost\Controller\Admin;

use Configuration;
use Context;
use finfo;
use Image;
use ImageManager;
use ImageType;
use Itrblueboost\Entity\GenerationJob;
use Itrblueboost\Entity\ProductImage;
use Itrblueboost\Service\ApiLogger;
use PrestaShopBundle\Controller\Admin\FrameworkBundleAdminController;
use PrestaShopBundle\Security\Annotation\AdminSecurity;
use Product;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Tools;

/**
 * Controller for AI-generated product images management.
 */
class ProductImageController extends FrameworkBundleAdminController
{
    /** @var ApiLogger */
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
        $product = new Product($id_product, false, (int) Context::getContext()->language->id);

        if (!$product->id) {
            $this->addFlash('error', $this->trans('Product not found.', 'Modules.Itrblueboost.Admin'));

            return $this->redirectToRoute('admin_products_index');
        }

        $pendingImages = ProductImage::getByProduct($id_product, 'pending');
        $acceptedImages = ProductImage::getByProduct($id_product, 'accepted');

        $modulePath = _MODULE_DIR_ . 'itrblueboost/uploads/pending/';
        foreach ($pendingImages as &$image) {
            $image['url'] = $modulePath . $image['filename'];
        }

        $existingImages = $this->getProductImages($id_product);

        return $this->render('@Modules/itrblueboost/views/templates/admin/product_image/index.html.twig', [
            'id_product' => $id_product,
            'product_name' => $product->name,
            'pending_images' => $pendingImages,
            'accepted_images' => $acceptedImages,
            'existing_images' => $existingImages,
            'layoutTitle' => $this->trans('AI Product Images', 'Modules.Itrblueboost.Admin'),
        ]);
    }

    /**
     * @AdminSecurity("is_granted('read', request.get('_legacy_controller'))")
     */
    public function getPromptsAction(): JsonResponse
    {
        $result = $this->apiLogger->getImagePrompts();

        if (!isset($result['success'])) {
            $result['success'] = isset($result['prompts']);
        }

        return new JsonResponse($result);
    }

    /**
     * Async image generation: creates a job and launches background processing.
     *
     * @AdminSecurity("is_granted('create', request.get('_legacy_controller'))")
     */
    public function generateAction(Request $request, int $id_product): JsonResponse
    {
        $apiKey = Configuration::get('ITRBLUEBOOST_API_KEY');

        if (empty($apiKey)) {
            return new JsonResponse([
                'success' => false,
                'message' => 'API key not configured.',
            ]);
        }

        $promptId = (int) $request->request->get('prompt_id');
        if ($promptId <= 0) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Prompt not selected.',
            ]);
        }

        $product = $this->loadProduct($id_product);
        if ($product === null) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Product not found (ID: ' . $id_product . ').',
            ]);
        }

        $apiData = $this->buildApiData($request, $product, $promptId);

        $job = $this->createGenerationJob($id_product, $apiData);
        if ($job === null) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Failed to create generation job.',
            ]);
        }

        return new JsonResponse([
            'success' => true,
            'job_id' => (int) $job->id,
            'message' => 'Generation started.',
        ]);
    }

    /**
     * Process a generation job. Called fire-and-forget by the frontend.
     *
     * @AdminSecurity("is_granted('create', request.get('_legacy_controller'))")
     */
    public function processJobAction(int $jobId): JsonResponse
    {
        @set_time_limit(300);
        @ignore_user_abort(true);

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        $job = new GenerationJob($jobId);

        if (!$job->id) {
            return new JsonResponse(['success' => false, 'message' => 'Job not found.'], 404);
        }

        if ($job->status !== GenerationJob::STATUS_PENDING) {
            return new JsonResponse(['success' => true, 'message' => 'Job already processed.']);
        }

        $this->processJobInline($job);

        return new JsonResponse(['success' => true]);
    }

    /**
     * Poll endpoint for generation job status.
     *
     * @AdminSecurity("is_granted('read', request.get('_legacy_controller'))")
     */
    public function jobStatusAction(int $jobId): JsonResponse
    {
        $job = new GenerationJob($jobId);

        if (!$job->id) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Job not found.',
            ], 404);
        }

        $response = [
            'success' => true,
            'status' => $job->status,
            'progress' => (int) $job->progress,
            'progress_label' => $job->progress_label ?: '',
        ];

        if ($job->status === GenerationJob::STATUS_COMPLETED) {
            $responseData = $job->getResponseDataArray();
            $response['data'] = $this->enrichCompletedJobData($responseData, (int) $job->id_product);
        }

        if ($job->status === GenerationJob::STATUS_FAILED) {
            $response['error_message'] = $job->error_message ?: 'Unknown error.';
        }

        return new JsonResponse($response);
    }

    /**
     * @AdminSecurity("is_granted('update', request.get('_legacy_controller'))")
     */
    public function acceptAction(int $id_product, int $imageId): JsonResponse
    {
        $productImage = new ProductImage($imageId);

        if (!$productImage->id || (int) $productImage->id_product !== $id_product) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Image not found.',
            ]);
        }

        if ($productImage->status !== 'pending') {
            return new JsonResponse([
                'success' => false,
                'message' => 'This image is not pending.',
            ]);
        }

        $sourcePath = $productImage->getPendingFilePath();
        if (!file_exists($sourcePath)) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Source file not found.',
            ]);
        }

        $product = new Product($id_product);
        if (!$product->id) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Product not found.',
            ]);
        }

        $image = new Image();
        $image->id_product = $id_product;
        $image->position = Image::getHighestPosition($id_product) + 1;
        $image->cover = !Image::getCover($id_product);

        if (!$image->add()) {
            return new JsonResponse([
                'success' => false,
                'message' => 'PrestaShop image creation error.',
            ]);
        }

        $destPath = $image->getPathForCreation();

        if (!is_dir(dirname($destPath))) {
            mkdir(dirname($destPath), 0755, true);
        }

        if (!copy($sourcePath, $destPath . '.jpg')) {
            $image->delete();

            return new JsonResponse([
                'success' => false,
                'message' => 'Image copy error.',
            ]);
        }

        $this->generateImageThumbnails($destPath);

        $productImage->status = 'accepted';
        $productImage->id_image = (int) $image->id;

        if (!$productImage->update()) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Status update error.',
            ]);
        }

        @unlink($sourcePath);

        return new JsonResponse([
            'success' => true,
            'message' => 'Image accepted and added to product.',
            'id_image' => $image->id,
        ]);
    }

    /**
     * @AdminSecurity("is_granted('update', request.get('_legacy_controller'))")
     */
    public function rejectAction(Request $request, int $id_product, int $imageId): JsonResponse
    {
        $productImage = new ProductImage($imageId);

        if (!$productImage->id || (int) $productImage->id_product !== $id_product) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Image not found.',
            ]);
        }

        if ($productImage->status !== 'pending') {
            return new JsonResponse([
                'success' => false,
                'message' => 'This image is not pending.',
            ]);
        }

        $rejectionReason = (string) $request->request->get('rejection_reason', '');

        $apiResult = $this->apiLogger->rejectImage(
            (int) $productImage->prompt_id,
            $id_product,
            $rejectionReason
        );

        if (!isset($apiResult['success']) || !$apiResult['success']) {
            return new JsonResponse([
                'success' => false,
                'message' => 'API sync error: ' . ($apiResult['message'] ?? 'Unknown error'),
            ]);
        }

        $productImage->deleteFile();
        $productImage->status = 'rejected';
        $productImage->rejection_reason = $rejectionReason;

        if (!$productImage->update()) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Status update error.',
            ]);
        }

        return new JsonResponse([
            'success' => true,
            'message' => 'Image rejected.',
        ]);
    }

    /**
     * @AdminSecurity("is_granted('delete', request.get('_legacy_controller'))")
     */
    public function deleteAction(int $id_product, int $imageId): JsonResponse
    {
        $productImage = new ProductImage($imageId);

        if (!$productImage->id || (int) $productImage->id_product !== $id_product) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Image not found.',
            ]);
        }

        if ($productImage->status === 'accepted' && $productImage->id_image) {
            $psImage = new Image((int) $productImage->id_image);
            if ($psImage->id) {
                $psImage->delete();
            }
        }

        $productImage->deleteFile();

        if (!$productImage->delete()) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Deletion error.',
            ]);
        }

        return new JsonResponse([
            'success' => true,
            'message' => 'Image deleted.',
        ]);
    }

    /**
     * Bulk image generation: creates a batch job for multiple products.
     *
     * @AdminSecurity("is_granted('create', request.get('_legacy_controller'))")
     */
    public function bulkGenerateAction(Request $request): JsonResponse
    {
        $apiKey = Configuration::get('ITRBLUEBOOST_API_KEY');

        if (empty($apiKey)) {
            return new JsonResponse([
                'success' => false,
                'message' => 'API key not configured.',
            ]);
        }

        $promptId = (int) $request->request->get('prompt_id');
        if ($promptId <= 0) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Prompt not selected.',
            ]);
        }

        $rawIds = $request->request->get('product_ids', '');
        $productIds = array_filter(array_map('intval', explode(',', (string) $rawIds)));

        if (empty($productIds)) {
            return new JsonResponse([
                'success' => false,
                'message' => 'No products selected.',
            ]);
        }

        $job = new GenerationJob();
        $job->job_type = GenerationJob::TYPE_IMAGE;
        $job->status = GenerationJob::STATUS_PENDING;
        $job->progress = 0;
        $job->progress_label = 'Initializing batch...';
        $job->id_product = 0;
        $job->request_data = json_encode([
            'product_ids' => $productIds,
            'prompt_id' => $promptId,
        ]);

        if (!$job->add()) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Failed to create generation job.',
            ]);
        }

        return new JsonResponse([
            'success' => true,
            'job_id' => (int) $job->id,
        ]);
    }

    /**
     * Process a bulk image generation job (fire-and-forget).
     *
     * @AdminSecurity("is_granted('create', request.get('_legacy_controller'))")
     */
    public function bulkProcessJobAction(int $jobId): JsonResponse
    {
        @set_time_limit(0);
        @ignore_user_abort(true);

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        $job = new GenerationJob($jobId);

        if (!$job->id) {
            return new JsonResponse(['success' => false, 'message' => 'Job not found.'], 404);
        }

        if ($job->status !== GenerationJob::STATUS_PENDING) {
            return new JsonResponse(['success' => true, 'message' => 'Job already processed.']);
        }

        $this->processBulkJobInline($job);

        return new JsonResponse(['success' => true]);
    }

    private function processBulkJobInline(GenerationJob $job): void
    {
        $requestData = $job->getRequestDataArray();
        $productIds = $requestData['product_ids'] ?? [];
        $promptId = (int) ($requestData['prompt_id'] ?? 0);

        if (empty($productIds) || $promptId <= 0) {
            $job->markFailed('Invalid job parameters.');

            return;
        }

        $job->markProcessing('Starting batch generation...');

        $context = Context::getContext();
        $idLang = $context->language ? (int) $context->language->id : (int) Configuration::get('PS_LANG_DEFAULT');

        $total = count($productIds);
        $processedItems = [];
        $errors = [];

        /** @var \Symfony\Component\Routing\RouterInterface $router */
        $router = $this->get('router');

        foreach ($productIds as $index => $idProduct) {
            $current = $index + 1;
            $product = $this->loadProduct((int) $idProduct);

            if ($product === null) {
                $errors[] = 'Product ID ' . $idProduct . ' not found.';
                $this->updateBulkProgress($job, $current, $total, 'Product ' . $current . '/' . $total . ' : not found, skipping...');
                continue;
            }

            $productName = $this->getProductName($product, $idLang);
            $this->updateBulkProgress($job, $current, $total, 'Product ' . $current . '/' . $total . ' : ' . $productName . '...');

            $apiData = $this->buildApiDataFromProduct($product, $promptId, $idLang);
            $response = $this->apiLogger->generateImage($apiData, (int) $idProduct);

            if (!isset($response['success']) || !$response['success']) {
                $errors[] = $productName . ': ' . ($response['message'] ?? 'Unknown API error.');
                continue;
            }

            $images = $response['data']['images'] ?? [];

            if (empty($images)) {
                $apiErrors = $response['data']['errors'] ?? [];
                $errors[] = $productName . ': ' . (!empty($apiErrors) ? ($apiErrors[0]['error'] ?? 'No images returned.') : 'No images returned.');
                continue;
            }

            $savedImages = $this->saveGeneratedImages($images, (int) $idProduct, $promptId);

            $imageUrl = $router->generate('itrblueboost_admin_product_image_index', [
                'id_product' => (int) $idProduct,
            ]);

            $processedItems[] = [
                'id' => (int) $idProduct,
                'name' => $productName,
                'image_count' => count($savedImages['saved']),
                'image_url' => $imageUrl,
            ];

            if (!empty($savedImages['errors'])) {
                foreach ($savedImages['errors'] as $saveError) {
                    $errors[] = $productName . ': ' . ($saveError['error'] ?? 'Save error.');
                }
            }
        }

        if (empty($processedItems) && !empty($errors)) {
            $job->markFailed(implode(' | ', $errors));

            return;
        }

        $job->markCompleted([
            'processed_items' => $processedItems,
            'errors' => $errors,
            'total_products' => $total,
            'total_processed' => count($processedItems),
        ]);
    }

    private function updateBulkProgress(GenerationJob $job, int $current, int $total, string $label): void
    {
        $progress = (int) round(($current / $total) * 90) + 5;
        $job->updateProgress(min($progress, 95), $label);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildApiDataFromProduct(Product $product, int $promptId, int $idLang): array
    {
        $productName = $this->getProductName($product, $idLang);

        return [
            'prompt_id' => $promptId,
            'product_name' => $productName,
        ];
    }

    private function getProductName(Product $product, int $idLang): string
    {
        if (is_array($product->name)) {
            return $product->name[$idLang] ?? reset($product->name);
        }

        return (string) $product->name;
    }

    private function loadProduct(int $idProduct): ?Product
    {
        $context = Context::getContext();
        $idLang = $context->language ? (int) $context->language->id : (int) Configuration::get('PS_LANG_DEFAULT');

        $product = new Product($idProduct, false, $idLang);

        if (!$product->id || !\Validate::isLoadedObject($product)) {
            return null;
        }

        return $product;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildApiData(Request $request, Product $product, int $promptId): array
    {
        $context = Context::getContext();
        $idLang = $context->language ? (int) $context->language->id : (int) Configuration::get('PS_LANG_DEFAULT');

        $productName = is_array($product->name)
            ? ($product->name[$idLang] ?? reset($product->name))
            : $product->name;

        $apiData = [
            'prompt_id' => $promptId,
            'product_name' => $productName,
        ];

        $contextText = $request->request->get('context');
        if (!empty($contextText)) {
            $apiData['context'] = $contextText;
        }

        $baseImageId = $request->request->get('base_image_id');
        if (!empty($baseImageId)) {
            $baseImage = new Image((int) $baseImageId);
            if ($baseImage->id) {
                $apiData['image_url'] = $this->getImageUrl($baseImage);
            }
        }

        return $apiData;
    }

    /**
     * @param array<string, mixed> $apiData
     */
    private function createGenerationJob(int $idProduct, array $apiData): ?GenerationJob
    {
        $job = new GenerationJob();
        $job->job_type = GenerationJob::TYPE_IMAGE;
        $job->status = GenerationJob::STATUS_PENDING;
        $job->progress = 0;
        $job->progress_label = 'Initializing...';
        $job->id_product = $idProduct;
        $job->request_data = json_encode([
            'api_data' => $apiData,
        ]);

        if (!$job->add()) {
            return null;
        }

        return $job;
    }

    private function processJobInline(GenerationJob $job): void
    {
        $requestData = $job->getRequestDataArray();
        $apiData = $requestData['api_data'] ?? [];
        $idProduct = (int) $job->id_product;
        $promptId = (int) ($apiData['prompt_id'] ?? 0);

        if (empty($apiData) || $idProduct <= 0) {
            $job->markFailed('Invalid job parameters.');

            return;
        }

        $job->markProcessing('Sending request to API...');
        $job->updateProgress(30, 'Waiting for AI response...');

        $response = $this->apiLogger->generateImage($apiData, $idProduct);

        if (!isset($response['success']) || !$response['success']) {
            $job->markFailed($response['message'] ?? 'Unknown API error.');

            return;
        }

        $job->updateProgress(60, 'Processing API response...');

        $images = $response['data']['images'] ?? [];
        $apiErrors = $response['data']['errors'] ?? [];

        if (empty($images)) {
            $errorMsg = !empty($apiErrors) ? ($apiErrors[0]['error'] ?? 'No images returned.') : 'No images returned.';
            $job->markFailed($errorMsg);

            return;
        }

        $job->updateProgress(70, 'Saving generated images...');

        $savedImages = $this->saveGeneratedImages($images, $idProduct, $promptId);

        if (empty($savedImages['saved'])) {
            $allErrors = array_merge($apiErrors, $savedImages['errors']);
            $errorMsg = !empty($allErrors) ? ($allErrors[0]['error'] ?? 'Failed to save images.') : 'Failed to save images.';
            $job->markFailed($errorMsg);

            return;
        }

        $job->markCompleted([
            'images' => $savedImages['saved'],
            'total_generated' => count($savedImages['saved']),
            'errors' => array_merge($apiErrors, $savedImages['errors']),
            'credits_used' => $response['credits_used'] ?? 0,
            'credits_remaining' => $response['credits_remaining'] ?? 0,
        ]);
    }

    /**
     * @param array<int, array<string, mixed>> $images
     *
     * @return array{saved: array, errors: array}
     */
    private function saveGeneratedImages(array $images, int $idProduct, int $promptId): array
    {
        $savedImages = [];
        $saveErrors = [];

        foreach ($images as $imageData) {
            $base64 = $imageData['base64'] ?? null;
            $mimeType = $imageData['mime_type'] ?? 'image/png';

            if (empty($base64)) {
                $saveErrors[] = [
                    'index' => $imageData['index'] ?? count($saveErrors),
                    'error' => 'Missing base64 data.',
                ];
                continue;
            }

            $saveResult = $this->saveBase64Image($base64, $mimeType, $idProduct);
            if (!$saveResult['success']) {
                $saveErrors[] = [
                    'index' => $imageData['index'] ?? count($saveErrors),
                    'error' => $saveResult['message'],
                ];
                continue;
            }

            $productImage = new ProductImage();
            $productImage->id_product = $idProduct;
            $productImage->filename = $saveResult['filename'];
            $productImage->status = 'pending';
            $productImage->prompt_id = $promptId;

            if (!$productImage->add()) {
                @unlink($saveResult['filepath']);
                $saveErrors[] = [
                    'index' => $imageData['index'] ?? count($saveErrors),
                    'error' => 'Database save error.',
                ];
                continue;
            }

            $savedImages[] = [
                'id' => $productImage->id,
                'filename' => $saveResult['filename'],
                'index' => $imageData['index'] ?? count($savedImages) - 1,
            ];
        }

        return ['saved' => $savedImages, 'errors' => $saveErrors];
    }

    /**
     * @return array{success: bool, message?: string, filename?: string, filepath?: string}
     */
    private function saveBase64Image(string $base64Data, string $mimeType, int $idProduct): array
    {
        $pendingPath = _PS_MODULE_DIR_ . 'itrblueboost/uploads/pending/';

        if (!is_dir($pendingPath) && !mkdir($pendingPath, 0755, true)) {
            return [
                'success' => false,
                'message' => 'Cannot create uploads/pending directory.',
            ];
        }

        $imageData = base64_decode($base64Data, true);
        if ($imageData === false) {
            return [
                'success' => false,
                'message' => 'Invalid base64 data.',
            ];
        }

        switch ($mimeType) {
            case 'image/png':
                $extension = 'png';
                break;
            case 'image/gif':
                $extension = 'gif';
                break;
            case 'image/webp':
                $extension = 'webp';
                break;
            default:
                $extension = 'jpg';
        }

        $filename = 'product_' . $idProduct . '_' . uniqid() . '_' . time() . '.' . $extension;
        $filepath = $pendingPath . $filename;

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $detectedMime = $finfo->buffer($imageData);

        $allowedMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if (!in_array($detectedMime, $allowedMimes, true)) {
            return [
                'success' => false,
                'message' => 'Invalid image data.',
            ];
        }

        if (file_put_contents($filepath, $imageData) === false) {
            return [
                'success' => false,
                'message' => 'Image save error.',
            ];
        }

        return [
            'success' => true,
            'filename' => $filename,
            'filepath' => $filepath,
        ];
    }

    private function generateImageThumbnails(string $destPath): void
    {
        $imageTypes = ImageType::getImagesTypes('products');
        foreach ($imageTypes as $imageType) {
            $width = (int) $imageType['width'];
            $height = (int) $imageType['height'];

            ImageManager::resize(
                $destPath . '.jpg',
                $destPath . '-' . $imageType['name'] . '.jpg',
                $width,
                $height,
                'jpg'
            );

            if (function_exists('imagewebp')) {
                ImageManager::resize(
                    $destPath . '.jpg',
                    $destPath . '-' . $imageType['name'] . '.webp',
                    $width,
                    $height,
                    'webp'
                );
            }
        }
    }

    /**
     * @param array<string, mixed> $responseData
     *
     * @return array<string, mixed>
     */
    private function enrichCompletedJobData(array $responseData, int $idProduct): array
    {
        if ($idProduct === 0) {
            return $responseData;
        }

        $modulePath = _MODULE_DIR_ . 'itrblueboost/uploads/pending/';
        $images = $responseData['images'] ?? [];

        foreach ($images as &$img) {
            $img['url'] = $modulePath . ($img['filename'] ?? '');
        }

        $responseData['images'] = $images;

        return $responseData;
    }

    private function getImageUrl(Image $image): string
    {
        $link = Context::getContext()->link;
        $imageUrl = $link->getImageLink(
            'product',
            $image->id_product . '-' . $image->id,
            ImageType::getFormattedName('large')
        );

        if (strpos($imageUrl, 'http') !== 0) {
            $imageUrl = Tools::getShopDomainSsl(true) . $imageUrl;
        }

        return $imageUrl;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function getProductImages(int $idProduct): array
    {
        $images = Image::getImages(Context::getContext()->language->id, $idProduct);
        $result = [];

        foreach ($images as $img) {
            $image = new Image((int) $img['id_image']);
            $result[] = [
                'id_image' => $image->id,
                'url' => $this->getImageUrl($image),
                'cover' => (bool) $image->cover,
                'position' => $image->position,
            ];
        }

        return $result;
    }
}
