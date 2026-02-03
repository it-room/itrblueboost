<?php

declare(strict_types=1);

namespace Itrblueboost\Controller\Admin;

use Configuration;
use Context;
use Image;
use ImageManager;
use ImageType;
use Itrblueboost\Entity\ProductImage;
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
    private const API_BASE_URL = 'https://apitr-sf.itroom.fr';

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
        $apiKey = Configuration::get('ITRBLUEBOOST_API_KEY');

        if (empty($apiKey)) {
            return new JsonResponse([
                'success' => false,
                'message' => 'API key not configured.',
            ]);
        }

        $response = $this->callApi('GET', '/api/image/prompts', $apiKey);

        if ($response === null) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Error retrieving prompts.',
            ]);
        }

        return new JsonResponse($response);
    }

    /**
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

        $context = Context::getContext();
        $idLang = $context->language ? (int) $context->language->id : (int) Configuration::get('PS_LANG_DEFAULT');

        $product = new Product($id_product, false, $idLang);
        if (!$product->id || !\Validate::isLoadedObject($product)) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Product not found (ID: ' . $id_product . ').',
            ]);
        }

        $baseImageId = $request->request->get('base_image_id');
        $baseImageUrl = null;

        if (!empty($baseImageId)) {
            $baseImage = new Image((int) $baseImageId);
            if ($baseImage->id) {
                $baseImageUrl = $this->getImageUrl($baseImage);
            }
        }

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

        if (!empty($baseImageUrl)) {
            $apiData['image_url'] = $baseImageUrl;
        }

        $response = $this->callApiWithError('POST', '/api/image', $apiKey, $apiData);

        if ($response['error'] !== null) {
            return new JsonResponse([
                'success' => false,
                'message' => 'API error: ' . $response['error'],
            ]);
        }

        $response = $response['data'];

        if (!isset($response['success']) || !$response['success']) {
            $errorMessage = $response['message'] ?? 'Unknown API error.';

            return new JsonResponse([
                'success' => false,
                'message' => $errorMessage,
            ]);
        }

        $images = $response['data']['images'] ?? [];
        $apiErrors = $response['data']['errors'] ?? [];

        if (empty($images)) {
            $errorMessage = 'No images returned by API.';
            if (!empty($apiErrors)) {
                $errorMessage = $apiErrors[0]['error'] ?? $errorMessage;
            }

            return new JsonResponse([
                'success' => false,
                'message' => $errorMessage,
            ]);
        }

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

            $saveResult = $this->saveBase64Image($base64, $mimeType, $id_product);
            if (!$saveResult['success']) {
                $saveErrors[] = [
                    'index' => $imageData['index'] ?? count($saveErrors),
                    'error' => $saveResult['message'],
                ];
                continue;
            }

            $productImage = new ProductImage();
            $productImage->id_product = $id_product;
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
                'url' => _MODULE_DIR_ . 'itrblueboost/uploads/pending/' . $saveResult['filename'],
                'index' => $imageData['index'] ?? count($savedImages) - 1,
            ];
        }

        $allErrors = array_merge($apiErrors, $saveErrors);

        if (empty($savedImages)) {
            $errorMessage = 'Failed to save any images.';
            if (!empty($allErrors)) {
                $errorMessage = $allErrors[0]['error'] ?? $errorMessage;
            }

            return new JsonResponse([
                'success' => false,
                'message' => $errorMessage,
                'errors' => $allErrors,
            ]);
        }

        return new JsonResponse([
            'success' => true,
            'message' => count($savedImages) === 1
                ? 'Image generated successfully.'
                : count($savedImages) . ' images generated successfully.',
            'images' => $savedImages,
            'total_generated' => count($savedImages),
            'total_requested' => $response['data']['total_requested'] ?? count($savedImages),
            'errors' => $allErrors,
            'credits_used' => $response['credits_used'] ?? 0,
            'credits_remaining' => $response['credits_remaining'] ?? 0,
        ]);
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
    public function rejectAction(int $id_product, int $imageId): JsonResponse
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

        $productImage->deleteFile();
        $productImage->status = 'rejected';

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
     * @return array{success: bool, message?: string, filename?: string, filepath?: string}
     */
    private function downloadImage(string $url, int $idProduct): array
    {
        $pendingPath = _PS_MODULE_DIR_ . 'itrblueboost/uploads/pending/';

        if (!is_dir($pendingPath) && !mkdir($pendingPath, 0755, true)) {
            return [
                'success' => false,
                'message' => 'Cannot create uploads/pending directory.',
            ];
        }

        $filename = 'product_' . $idProduct . '_' . uniqid() . '_' . time() . '.jpg';
        $filepath = $pendingPath . $filename;

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $imageData = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($imageData === false || $httpCode !== 200) {
            return [
                'success' => false,
                'message' => 'Download error: ' . ($error ?: 'HTTP ' . $httpCode),
            ];
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->buffer($imageData);

        $allowedMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if (!in_array($mimeType, $allowedMimes, true)) {
            return [
                'success' => false,
                'message' => 'Downloaded file is not a valid image.',
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

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
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

    /**
     * @return array{data: array<string, mixed>|null, error: string|null}
     */
    private function callApiWithError(string $method, string $endpoint, string $apiKey, ?array $data = null): array
    {
        $ch = curl_init();

        $isImageEndpoint = strpos($endpoint, '/image') !== false;
        $timeout = $isImageEndpoint ? 300 : 120;

        $options = [
            CURLOPT_URL => self::API_BASE_URL . $endpoint,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CONNECTTIMEOUT => 30,
            CURLOPT_HTTPHEADER => [
                'X-API-Key: ' . $apiKey,
                'Accept: application/json',
                'Content-Type: application/json',
            ],
        ];

        if ($method === 'POST') {
            $options[CURLOPT_POST] = true;
            if ($data !== null) {
                $options[CURLOPT_POSTFIELDS] = json_encode($data);
            }
        }

        curl_setopt_array($ch, $options);

        $response = curl_exec($ch);
        $curlError = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false) {
            error_log('ITRBLUEBOOST API curl error: ' . $curlError);
            return ['data' => null, 'error' => 'Connection failed: ' . $curlError];
        }

        // Try to decode response even on HTTP errors to get detailed error message
        $decoded = json_decode($response, true);

        if ($httpCode < 200 || $httpCode >= 300) {
            $errorMessage = 'HTTP error ' . $httpCode;

            // Extract detailed error message from API response if available
            if (is_array($decoded)) {
                if (!empty($decoded['message'])) {
                    $errorMessage = $decoded['message'];
                } elseif (!empty($decoded['error'])) {
                    $errorMessage = is_array($decoded['error'])
                        ? ($decoded['error']['message'] ?? json_encode($decoded['error']))
                        : $decoded['error'];
                } elseif (!empty($decoded['detail'])) {
                    $errorMessage = $decoded['detail'];
                } elseif (!empty($decoded['errors']) && is_array($decoded['errors'])) {
                    $firstError = reset($decoded['errors']);
                    $errorMessage = is_array($firstError)
                        ? ($firstError['message'] ?? json_encode($firstError))
                        : $firstError;
                }
            }

            error_log('ITRBLUEBOOST API HTTP error ' . $httpCode . ': ' . $errorMessage);
            error_log('ITRBLUEBOOST API response: ' . substr($response, 0, 1000));

            return ['data' => null, 'error' => $errorMessage . ' (HTTP ' . $httpCode . ')'];
        }

        if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
            $errorMsg = json_last_error_msg();
            error_log('ITRBLUEBOOST API JSON decode error: ' . $errorMsg . ' (response length: ' . strlen($response) . ')');
            return ['data' => null, 'error' => 'JSON decode failed: ' . $errorMsg];
        }

        if (!is_array($decoded)) {
            return ['data' => null, 'error' => 'Invalid response format'];
        }

        return ['data' => $decoded, 'error' => null];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function callApi(string $method, string $endpoint, string $apiKey, ?array $data = null): ?array
    {
        $result = $this->callApiWithError($method, $endpoint, $apiKey, $data);
        return $result['data'];
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
