<?php

declare(strict_types=1);

namespace Itrblueboost\Command;

use Configuration;
use finfo;
use Itrblueboost\Entity\GenerationJob;
use Itrblueboost\Entity\ProductImage;
use Itrblueboost\Service\ApiLogger;
use Shop;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Symfony command to process generation jobs in background.
 *
 * This avoids HTTP 504 timeouts by running long API calls
 * outside of the web request context.
 */
class ProcessGenerationJobCommand extends Command
{
    /** @var ApiLogger */
    private $apiLogger;

    public function __construct()
    {
        $this->apiLogger = new ApiLogger();
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('itrblueboost:process-generation-job')
            ->setDescription('Process an async generation job')
            ->addArgument('job_id', InputArgument::REQUIRED, 'The generation job ID to process');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->initShopContext();

        $jobId = (int) $input->getArgument('job_id');
        $job = new GenerationJob($jobId);

        if (!$job->id) {
            $output->writeln('<error>Job not found: ' . $jobId . '</error>');

            return 1;
        }

        if ($job->status !== GenerationJob::STATUS_PENDING) {
            $output->writeln('<info>Job ' . $jobId . ' is not pending (status: ' . $job->status . ')</info>');

            return 0;
        }

        $output->writeln('Processing job #' . $jobId . ' (type: ' . $job->job_type . ')');

        switch ($job->job_type) {
            case GenerationJob::TYPE_IMAGE:
                return $this->processImageJob($job, $output);
            default:
                $job->markFailed('Unknown job type: ' . $job->job_type);
                $output->writeln('<error>Unknown job type: ' . $job->job_type . '</error>');

                return 1;
        }
    }

    private function processImageJob(GenerationJob $job, OutputInterface $output): int
    {
        $requestData = $job->getRequestDataArray();
        $apiData = $requestData['api_data'] ?? [];
        $idProduct = (int) ($job->id_product ?? 0);
        $promptId = (int) ($apiData['prompt_id'] ?? 0);

        if (empty($apiData) || $idProduct <= 0) {
            $job->markFailed('Invalid job parameters.');
            $output->writeln('<error>Invalid job parameters</error>');

            return 1;
        }

        $job->markProcessing('Sending request to API...');
        $output->writeln('  Calling API...');

        $job->updateProgress(30, 'Waiting for AI response...');

        $response = $this->apiLogger->generateImage($apiData, $idProduct);

        if (!isset($response['success']) || !$response['success']) {
            $errorMessage = $response['message'] ?? 'Unknown API error.';
            $job->markFailed($errorMessage);
            $output->writeln('<error>' . $errorMessage . '</error>');

            return 1;
        }

        $job->updateProgress(60, 'Processing API response...');

        $images = $response['data']['images'] ?? [];
        $apiErrors = $response['data']['errors'] ?? [];

        if (empty($images)) {
            $errorMessage = 'No images returned by API.';
            if (!empty($apiErrors)) {
                $errorMessage = $apiErrors[0]['error'] ?? $errorMessage;
            }

            $job->markFailed($errorMessage);
            $output->writeln('<error>' . $errorMessage . '</error>');

            return 1;
        }

        $job->updateProgress(70, 'Saving generated images...');
        $output->writeln('  Saving ' . count($images) . ' image(s)...');

        $savedImages = [];
        $saveErrors = [];

        foreach ($images as $imageData) {
            $result = $this->saveBase64Image(
                $imageData['base64'] ?? '',
                $imageData['mime_type'] ?? 'image/png',
                $idProduct
            );

            if (!$result['success']) {
                $saveErrors[] = [
                    'index' => $imageData['index'] ?? count($saveErrors),
                    'error' => $result['message'],
                ];
                continue;
            }

            $productImage = new ProductImage();
            $productImage->id_product = $idProduct;
            $productImage->filename = $result['filename'];
            $productImage->status = 'pending';
            $productImage->prompt_id = $promptId;

            if (!$productImage->add()) {
                @unlink($result['filepath']);
                $saveErrors[] = [
                    'index' => $imageData['index'] ?? count($saveErrors),
                    'error' => 'Database save error.',
                ];
                continue;
            }

            $savedImages[] = [
                'id' => $productImage->id,
                'filename' => $result['filename'],
                'index' => $imageData['index'] ?? count($savedImages) - 1,
            ];
        }

        if (empty($savedImages)) {
            $allErrors = array_merge($apiErrors, $saveErrors);
            $errorMessage = !empty($allErrors)
                ? ($allErrors[0]['error'] ?? 'Failed to save any images.')
                : 'Failed to save any images.';

            $job->markFailed($errorMessage);
            $output->writeln('<error>' . $errorMessage . '</error>');

            return 1;
        }

        $job->updateProgress(90, 'Finalizing...');

        $responseData = [
            'images' => $savedImages,
            'total_generated' => count($savedImages),
            'errors' => array_merge($apiErrors, $saveErrors),
            'credits_used' => $response['credits_used'] ?? 0,
            'credits_remaining' => $response['credits_remaining'] ?? 0,
        ];

        $job->markCompleted($responseData);
        $output->writeln('<info>Job completed: ' . count($savedImages) . ' image(s) saved.</info>');

        return 0;
    }

    /**
     * @return array{success: bool, message?: string, filename?: string, filepath?: string}
     */
    private function saveBase64Image(string $base64Data, string $mimeType, int $idProduct): array
    {
        if (empty($base64Data)) {
            return ['success' => false, 'message' => 'Missing base64 data.'];
        }

        $pendingPath = _PS_MODULE_DIR_ . 'itrblueboost/uploads/pending/';

        if (!is_dir($pendingPath) && !mkdir($pendingPath, 0755, true)) {
            return ['success' => false, 'message' => 'Cannot create uploads/pending directory.'];
        }

        $imageData = base64_decode($base64Data, true);
        if ($imageData === false) {
            return ['success' => false, 'message' => 'Invalid base64 data.'];
        }

        $extension = $this->getExtensionFromMime($mimeType);
        $filename = 'product_' . $idProduct . '_' . uniqid() . '_' . time() . '.' . $extension;
        $filepath = $pendingPath . $filename;

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $detectedMime = $finfo->buffer($imageData);

        $allowedMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if (!in_array($detectedMime, $allowedMimes, true)) {
            return ['success' => false, 'message' => 'Invalid image data.'];
        }

        if (file_put_contents($filepath, $imageData) === false) {
            return ['success' => false, 'message' => 'Image save error.'];
        }

        return [
            'success' => true,
            'filename' => $filename,
            'filepath' => $filepath,
        ];
    }

    private function getExtensionFromMime(string $mimeType): string
    {
        switch ($mimeType) {
            case 'image/png':
                return 'png';
            case 'image/gif':
                return 'gif';
            case 'image/webp':
                return 'webp';
            default:
                return 'jpg';
        }
    }

    /**
     * Ensure PrestaShop shop context is set for CLI execution.
     *
     * Without this, Configuration::get() cannot find shop-specific values
     * like the API key when running via bin/console.
     */
    private function initShopContext(): void
    {
        if (Shop::getContext() !== Shop::CONTEXT_SHOP) {
            $shopId = (int) Configuration::getGlobalValue('PS_SHOP_DEFAULT');
            if ($shopId <= 0) {
                $shopId = 1;
            }
            Shop::setContext(Shop::CONTEXT_SHOP, $shopId);
        }
    }
}
