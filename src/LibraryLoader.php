<?php

declare(strict_types=1);

namespace Codewithkyrian\Whisper;

use FFI;
use RuntimeException;
use ZipArchive;

class LibraryLoader
{
    private const LIBRARY_CONFIGS = [
        'whisper' => [
            'header' => 'whisper.h',
            'lib_prefix' => 'libwhisper',
        ],
        'sndfile' => [
            'header' => 'sndfile.h',
            'lib_prefix' => 'libsndfile',
        ],
        'samplerate' => [
            'header' => 'samplerate.h',
            'lib_prefix' => 'libsamplerate',
        ],
    ];

    private const WHISPER_CPP_VERSION = '1.7.2';
    private const DOWNLOAD_URL = 'https://huggingface.co/codewithkyrian/whisper.php/resolve/%s/libs/%s.zip';

    private static array $instances = [];

    private static ?PlatformDetector $platformDetector = null;

    /**
     * Gets the FFI instance for the specified library
     */
    public static function getInstance(string $library): FFI
    {
        if (!isset(self::$instances[$library])) {
            self::$instances[$library] = self::createFFIInstance($library);
        }

        return self::$instances[$library];
    }

    /**
     * Creates a new FFI instance for the specified library
     */
    private static function createFFIInstance(string $library): FFI
    {
        if (!isset(self::LIBRARY_CONFIGS[$library])) {
            throw new RuntimeException("Unsupported library: {$library}");
        }

        $config = self::LIBRARY_CONFIGS[$library];
        $detector = self::getPlatformDetector();

        $headerPath = self::getHeaderPath($config['header']);
        $libPath = self::getLibraryPath(
            $config['lib_prefix'],
            $detector->getLibraryExtension(),
            $detector->getPlatformIdentifier()
        );

        if (!file_exists($libPath)) {
            self::downloadLibraries();
        }

        return FFI::cdef(
            file_get_contents($headerPath),
            $libPath
        );
    }

    private static function getPlatformDetector(): PlatformDetector
    {
        if (self::$platformDetector === null) {
            self::$platformDetector = new PlatformDetector;
        }

        return self::$platformDetector;
    }

    private static function getHeaderPath(string $headerFile): string
    {
        return self::joinPaths(dirname(__DIR__), 'include', $headerFile);
    }

    private static function getLibraryPath(string $prefix, string $extension, string $platform): string
    {
        return self::joinPaths(dirname(__DIR__), 'lib', $platform, "$prefix.$extension");
    }

    /**
     * Download libraries from Hugging Face
     */
    private static function downloadLibraries(): void
    {
        $detector = self::getPlatformDetector();
        $platform = $detector->getPlatformIdentifier();

        $url = sprintf(self::DOWNLOAD_URL, self::WHISPER_CPP_VERSION, $platform);

        $tempFile = tempnam(sys_get_temp_dir(), 'whisper-cpp-libs');

        $ch = curl_init();
        $fp = fopen($tempFile, 'w');
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_FILE, $fp);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_FAILONERROR, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Accept: application/octet-stream',
        ]);

        if (!curl_exec($ch)) {
            fclose($fp);
            unlink($tempFile);
            throw new \RuntimeException(sprintf('Failed to download libraries from %s: %s', $url, curl_error($ch)));
        }

        // Extract ZIP file
        $zip = new ZipArchive;
        if ($zip->open($tempFile) === true) {
            $platformLibDir = self::joinPaths(dirname(__DIR__), 'lib', $platform);
            if (!is_dir($platformLibDir)) {
                mkdir($platformLibDir, 0755, true);
            }
            $zip->extractTo($platformLibDir);
            $zip->close();

            unlink($tempFile);
        } else {
            throw new RuntimeException('Failed to downloaded ZIP');
        }
    }

    private static function joinPaths(string ...$args): string
    {
        $paths = [];

        foreach ($args as $key => $path) {
            if ($path === '') {
                continue;
            } elseif ($key === 0) {
                $paths[$key] = rtrim($path, DIRECTORY_SEPARATOR);
            } elseif ($key === count($paths) - 1) {
                $paths[$key] = ltrim($path, DIRECTORY_SEPARATOR);
            } else {
                $paths[$key] = trim($path, DIRECTORY_SEPARATOR);
            }
        }

        return preg_replace('#(?<!:)//+#', '/', implode(DIRECTORY_SEPARATOR, $paths));
    }
}
