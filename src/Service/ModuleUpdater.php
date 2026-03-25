<?php

declare(strict_types=1);

namespace Itrblueboost\Service;

use Configuration;
use Module;
use Tools;
use ZipArchive;

/**
 * Service to check and perform module updates from GitHub releases.
 */
class ModuleUpdater
{
    private const GITHUB_API_URL = 'https://api.github.com/repos/it-room/itrblueboost/releases/latest';
    private const CACHE_TTL = 3600;
    private const CONFIG_KEY = 'ITRBLUEBOOST_UPDATE_CACHE';
    private const CHECK_TIMEOUT = 30;
    private const DOWNLOAD_TIMEOUT = 120;

    /**
     * @return array|null
     */
    public function getCachedUpdateInfo()
    {
        $cached = Configuration::get(self::CONFIG_KEY);
        if (empty($cached)) {
            return null;
        }

        $data = json_decode($cached, true);
        if (!is_array($data)) {
            return null;
        }

        return $data;
    }

    /**
     * @return array
     */
    public function checkForUpdate(): array
    {
        $cached = $this->getCachedUpdateInfo();
        if ($cached !== null && $this->isCacheFresh($cached)) {
            return $cached;
        }

        return $this->fetchFromGitHub($cached);
    }

    /**
     * @param string $zipUrl
     *
     * @return string
     *
     * @throws \RuntimeException
     */
    public function downloadRelease(string $zipUrl): string
    {
        $zipPath = _PS_CACHE_DIR_ . 'itrblueboost_update_' . time() . '.zip';

        $content = $this->httpGet($zipUrl, self::DOWNLOAD_TIMEOUT, true);
        if ($content === false) {
            throw new \RuntimeException('Failed to download release ZIP from GitHub.');
        }

        $written = file_put_contents($zipPath, $content);
        if ($written === false) {
            throw new \RuntimeException('Failed to write ZIP file to cache directory.');
        }

        return $zipPath;
    }

    /**
     * @param string $zipPath
     *
     * @return void
     *
     * @throws \RuntimeException
     */
    public function extractAndReplace(string $zipPath): void
    {
        $this->validatePreConditions($zipPath);

        $zip = new ZipArchive();
        $result = $zip->open($zipPath);
        if ($result !== true) {
            $this->cleanupFile($zipPath);
            throw new \RuntimeException('Failed to open ZIP archive (error code: ' . $result . ').');
        }

        $rootFolder = $this->detectRootFolder($zip);
        $tempDir = _PS_CACHE_DIR_ . 'itrblueboost_extract_' . time();

        if (!$zip->extractTo($tempDir)) {
            $zip->close();
            $this->cleanupDirectory($tempDir);
            $this->cleanupFile($zipPath);
            throw new \RuntimeException('Failed to extract ZIP archive.');
        }

        $zip->close();

        $sourceDir = $tempDir . '/' . $rootFolder;
        $targetDir = _PS_MODULE_DIR_ . 'itrblueboost';

        $this->copyDirectory($sourceDir, $targetDir);
        $this->cleanupDirectory($tempDir);
        $this->cleanupFile($zipPath);
    }

    /**
     * @return array
     *
     * @throws \RuntimeException
     */
    public function runUpgrade(): array
    {
        $module = Module::getInstanceByName('itrblueboost');
        if (!$module) {
            throw new \RuntimeException('Cannot instantiate module itrblueboost.');
        }

        try {
            $result = $module->runUpgradeModule();
        } catch (\TypeError $e) {
            // PS 8.x + PHP 8: runUpgradeModule() may call count(null)
            // when no upgrade files are found — treat as success
            $result = ['success' => true];
        }

        $this->clearCache();

        return [
            'success' => (bool) ($result['success'] ?? true),
            'version' => $module->version,
            'upgradedFrom' => $result['upgraded_from'] ?? null,
        ];
    }

    /**
     * @return void
     */
    public function clearCache(): void
    {
        Configuration::deleteByName(self::CONFIG_KEY);
    }

    /**
     * @param array $cached
     *
     * @return bool
     */
    private function isCacheFresh(array $cached): bool
    {
        if (!isset($cached['checkedAt'])) {
            return false;
        }

        return (time() - (int) $cached['checkedAt']) < self::CACHE_TTL;
    }

    /**
     * @param array|null $fallback
     *
     * @return array
     */
    private function fetchFromGitHub($fallback): array
    {
        $currentVersion = $this->getCurrentVersion();
        $response = $this->httpGet(self::GITHUB_API_URL, self::CHECK_TIMEOUT);

        if ($response === false) {
            return $this->handleGitHubError($fallback);
        }

        $release = json_decode($response, true);
        if (!is_array($release) || !isset($release['tag_name'])) {
            return $this->handleGitHubError($fallback);
        }

        $latestVersion = ltrim($release['tag_name'], 'vV');
        $hasUpdate = version_compare($latestVersion, $currentVersion, '>');

        $data = [
            'hasUpdate' => $hasUpdate,
            'latestVersion' => $latestVersion,
            'releaseNotes' => $release['body'] ?? '',
            'zipUrl' => $release['zipball_url'] ?? '',
            'checkedAt' => time(),
            'htmlUrl' => $release['html_url'] ?? '',
        ];

        Configuration::updateValue(self::CONFIG_KEY, json_encode($data));

        return $data;
    }

    /**
     * @param array|null $fallback
     *
     * @return array
     */
    private function handleGitHubError($fallback): array
    {
        if ($fallback !== null) {
            $fallback['warning'] = 'GitHub unreachable, returning cached data.';
            return $fallback;
        }

        return [
            'hasUpdate' => false,
            'latestVersion' => $this->getCurrentVersion(),
            'releaseNotes' => '',
            'zipUrl' => '',
            'checkedAt' => time(),
            'warning' => 'GitHub unreachable, no cached data available.',
        ];
    }

    /**
     * @return string
     */
    private function getCurrentVersion(): string
    {
        $module = Module::getInstanceByName('itrblueboost');
        if ($module) {
            return $module->version;
        }

        return '0.0.0';
    }

    /**
     * @param string $url
     * @param int    $timeout
     * @param bool   $followRedirects
     *
     * @return string|false
     */
    private function httpGet(string $url, int $timeout = 30, bool $followRedirects = false)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'User-Agent: itrblueboost-prestashop-module',
            'Accept: application/vnd.github.v3+json',
        ]);

        if ($followRedirects) {
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
        }

        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($result === false || $httpCode >= 400) {
            return false;
        }

        return $result;
    }

    /**
     * @param string $zipPath
     *
     * @return void
     *
     * @throws \RuntimeException
     */
    private function validatePreConditions(string $zipPath): void
    {
        if (!class_exists('ZipArchive')) {
            $this->cleanupFile($zipPath);
            throw new \RuntimeException('PHP ZipArchive extension is not available.');
        }

        $moduleDir = _PS_MODULE_DIR_ . 'itrblueboost';
        if (!is_writable($moduleDir)) {
            $this->cleanupFile($zipPath);
            throw new \RuntimeException('Module directory is not writable: ' . $moduleDir);
        }
    }

    /**
     * @param ZipArchive $zip
     *
     * @return string
     *
     * @throws \RuntimeException
     */
    private function detectRootFolder(ZipArchive $zip): string
    {
        $firstName = $zip->getNameIndex(0);
        if ($firstName === false) {
            throw new \RuntimeException('ZIP archive is empty.');
        }

        $parts = explode('/', $firstName);

        return $parts[0];
    }

    /**
     * @param string $source
     * @param string $target
     *
     * @return void
     *
     * @throws \RuntimeException
     */
    private function copyDirectory(string $source, string $target): void
    {
        if (!is_dir($source)) {
            throw new \RuntimeException('Source directory does not exist: ' . $source);
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($source, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            $relativePath = substr($item->getPathname(), strlen($source));
            $targetPath = $target . $relativePath;

            if ($item->isDir()) {
                if (!is_dir($targetPath) && !mkdir($targetPath, 0755, true)) {
                    throw new \RuntimeException('Failed to create directory: ' . $targetPath);
                }
                continue;
            }

            $targetDir = dirname($targetPath);
            if (!is_dir($targetDir) && !mkdir($targetDir, 0755, true)) {
                throw new \RuntimeException('Failed to create directory: ' . $targetDir);
            }

            if (!copy($item->getPathname(), $targetPath)) {
                throw new \RuntimeException('Failed to copy file: ' . $item->getPathname());
            }
        }
    }

    /**
     * @param string $path
     *
     * @return void
     */
    private function cleanupFile(string $path): void
    {
        if (file_exists($path)) {
            unlink($path);
        }
    }

    /**
     * @param string $dir
     *
     * @return void
     */
    private function cleanupDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $item) {
            if ($item->isDir()) {
                rmdir($item->getPathname());
            } else {
                unlink($item->getPathname());
            }
        }

        rmdir($dir);
    }
}
