<?php

declare(strict_types=1);

namespace Codewithkyrian\Whisper;

use FFI;
use RuntimeException;

class FFILoader
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

        if (!file_exists($headerPath)) {
            throw new RuntimeException("Header file not found: {$headerPath}");
        }

        if (!file_exists($libPath)) {
            throw new RuntimeException("Library file not found: {$libPath}");
        }

        return FFI::cdef(
            file_get_contents($headerPath),
            $libPath
        );
    }

    private static function getPlatformDetector(): PlatformDetector
    {
        if (self::$platformDetector === null) {
            self::$platformDetector = new PlatformDetector();
        }
        return self::$platformDetector;
    }

    private static function getHeaderPath(string $headerFile): string
    {
        return dirname(__DIR__)."/include/{$headerFile}";
    }

    private static function getLibraryPath(
        string $prefix,
        string $extension,
        string $platform
    ): string
    {
        return dirname(__DIR__)."/lib/{$platform}/{$prefix}.{$extension}";
    }
}
