<?php

declare(strict_types=1);

namespace Itrblueboost\Controller\Admin;

use Context;
use Db;
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

/**
 * Controller for listing all generated images.
 */
class GeneratedImagesController extends FrameworkBundleAdminController
{
    /**
     * @AdminSecurity("is_granted('read', request.get('_legacy_controller'))")
     */
    public function indexAction(Request $request): Response
    {
        $page = max(1, (int) $request->query->get('page', 1));
        $limit = 20;
        $offset = ($page - 1) * $limit;

        $statusFilter = $request->query->get('status', '');

        $whereClause = '';
        if ($statusFilter && in_array($statusFilter, ['pending', 'accepted', 'rejected'], true)) {
            $whereClause = ' WHERE pi.status = "' . pSQL($statusFilter) . '"';
        }

        // Get total count
        $totalQuery = 'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'itrblueboost_product_image` pi' . $whereClause;
        $totalImages = (int) Db::getInstance()->getValue($totalQuery);

        // Get images with product info (including reference)
        $sql = 'SELECT pi.*, p.id_product, p.reference as product_reference, pl.name as product_name
                FROM `' . _DB_PREFIX_ . 'itrblueboost_product_image` pi
                LEFT JOIN `' . _DB_PREFIX_ . 'product` p ON p.id_product = pi.id_product
                LEFT JOIN `' . _DB_PREFIX_ . 'product_lang` pl ON pl.id_product = p.id_product
                    AND pl.id_lang = ' . (int) Context::getContext()->language->id . '
                    AND pl.id_shop = ' . (int) Context::getContext()->shop->id . '
                ' . $whereClause . '
                ORDER BY pi.date_add DESC
                LIMIT ' . (int) $offset . ', ' . (int) $limit;

        $images = Db::getInstance()->executeS($sql);

        // Build image URLs
        $modulePath = _MODULE_DIR_ . 'itrblueboost/uploads/pending/';
        foreach ($images as &$image) {
            if ($image['status'] === 'pending') {
                $image['url'] = $modulePath . $image['filename'];
            } elseif ($image['status'] === 'accepted' && !empty($image['id_image'])) {
                $image['url'] = $this->getPrestaShopImageUrl((int) $image['id_image']);
            } else {
                $image['url'] = '';
            }
        }

        $totalPages = (int) ceil($totalImages / $limit);

        return $this->render('@Modules/itrblueboost/views/templates/admin/generated_images/index.html.twig', [
            'images' => $images,
            'currentPage' => $page,
            'totalPages' => $totalPages,
            'totalImages' => $totalImages,
            'statusFilter' => $statusFilter,
            'layoutTitle' => $this->trans('Generated Images', 'Modules.Itrblueboost.Admin'),
        ]);
    }

    /**
     * @AdminSecurity("is_granted('update', request.get('_legacy_controller'))")
     */
    public function acceptAction(Request $request, int $imageId): JsonResponse
    {
        $productImage = new ProductImage($imageId);

        if (!$productImage->id) {
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

        $idProduct = (int) $productImage->id_product;
        $product = new Product($idProduct);
        if (!$product->id) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Product not found.',
            ]);
        }

        $image = new Image();
        $image->id_product = $idProduct;
        $image->position = Image::getHighestPosition($idProduct) + 1;
        $image->cover = !Image::getCover($idProduct);

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
    public function rejectAction(Request $request, int $imageId): JsonResponse
    {
        $productImage = new ProductImage($imageId);

        if (!$productImage->id) {
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

        $rejectionReason = $request->request->get('rejection_reason', '');

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
    public function deleteAction(Request $request, int $imageId): JsonResponse
    {
        $productImage = new ProductImage($imageId);

        if (!$productImage->id) {
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

    private function getPrestaShopImageUrl(int $idImage): string
    {
        $folders = implode('/', str_split((string) $idImage));

        return '/img/p/' . $folders . '/' . $idImage . '.jpg';
    }
}
