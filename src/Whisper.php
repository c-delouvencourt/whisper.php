<?php

declare(strict_types=1);

namespace Codewithkyrian\Whisper;

/**
 * A High-Level API for Whisper that simplifies the typical transcription workflow
 * and exposes the main features of Whisper
 */
class Whisper
{
    private WhisperContext $context;
    private WhisperFullParams $params;
    private bool $noState;
    private array $transcript = [];
    private bool $contextInitialized = false;

    // Prevent direct instantiation
    private function __construct() {}

    /**
     * Available models that can be downloaded
     */
    public const MODELS = [
        'tiny.en',
        'tiny',
        'base.en',
        'base',
        'small.en',
        'small',
        'medium.en',
        'medium',
        'large-v1',
        'large-v2',
        'large-v3',
        'large',
    ];

    /**
     * Create a Whisper instance from a pretrained model
     *
     * @param string $modelName Name/path of the pretrained model. Can be one of: 'tiny.en', 'tiny', 'base.en', 'base', 'small.en', 'small', 'medium.en', 'medium', 'large-v1', 'large-v2', 'large-v3', 'large'
     * @param WhisperFullParams|null $params Parameters to use when running the model
     * @param string|null $baseDir Base directory to store the model. Defaults to the "$XDG_DATA_HOME/whisper.cpp" directory
     */
    public static function fromPretrained(
        string            $modelName,
        WhisperFullParams $params = null,
        ?string           $baseDir = null
    ): self
    {
        if (!in_array($modelName, self::MODELS) && !is_file($modelName)) {
            throw new \RuntimeException(
                sprintf(
                    "'%s' is not a valid pre-converted model or a file path. Choose one of %s",
                    $modelName,
                    implode(', ', self::MODELS)
                )
            );
        }

        $instance = new self();

        if (in_array($modelName, self::MODELS)) {
            $modelPath = $instance->downloadModel($modelName, $baseDir);
        } else {
            $modelPath = $modelName;
        }

        $contextParams = WhisperContextParameters::default();
        $instance->context = new WhisperContext($modelPath, $contextParams);
        $instance->params = $params ?? WhisperFullParams::default();

        $instance->context->resetTimings();

        return $instance;
    }

    /**
     * Transcribe audio data
     *
     * @param float[]|string $audio Audio data as float32 array or path to audio file
     * @param int $nThreads Number of threads to use when processing audio
     *
     * @return SegmentData[] An array of transcribed segment data
     */
    public function transcribe(string|array $audio, int $nThreads = 1): array
    {
        if (is_string($audio)) {
            if (!file_exists($audio)) {
                throw new \RuntimeException("File not found: $audio");
            }

            $audio = readAudio($audio);
        }

        if (!isset($this->context)) {
            throw new \RuntimeException('Context is not initialized. Please call Whisper::fromPretrained() first.');
        }

        $this->params = $this->params->withNThreads($nThreads);

        $state = $this->context->createState();
        $state->full($audio, $this->params);

        $segments = [];

        $numSegments = $state->nSegments();
        for ($i = 0; $i < $numSegments; $i++) {
            $segments[] = new SegmentData($i, $state->getSegmentStartTime($i), $state->getSegmentEndTime($i), $state->getSegmentText($i));
        }

        return $segments;
    }

    /**
     * Download a model from the official repository
     */
    public static function downloadModel(string $modelName, ?string $baseDir = null): string
    {
        $baseDir ??= self::getDefaultModelDir();

        if (!is_dir($baseDir)) {
            mkdir($baseDir, 0755, true);
        }

        $modelPath = $baseDir.DIRECTORY_SEPARATOR.'ggml-'.$modelName.'.bin';
        if (file_exists($modelPath)) {
            return $modelPath;
        }

        $url = sprintf('https://huggingface.co/ggerganov/whisper.cpp/resolve/main/ggml-%s.bin', $modelName);

        $tempFile = tempnam(sys_get_temp_dir(), 'whisper');

        $ch = curl_init();
        $fp = fopen($tempFile, 'w');

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_FILE, $fp);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_FAILONERROR, true);

        if (!curl_exec($ch)) {
            fclose($fp);
            unlink($tempFile);
            throw new \RuntimeException(sprintf("Failed to download model from %s: %s", $url, curl_error($ch)));
        }

        curl_close($ch);
        fclose($fp);

        if (!rename($tempFile, $modelPath)) {
            unlink($tempFile);
            throw new \RuntimeException("Failed to move downloaded model to $modelPath");
        }

        return $modelPath;
    }

    /**
     * Get the default directory for storing models
     */
    public static function getDefaultModelDir(): string
    {
        if (PHP_OS_FAMILY === 'Windows') {
            return getenv('LOCALAPPDATA').DIRECTORY_SEPARATOR.'whisper.cpp';
        }

        $xdgData = getenv('XDG_DATA_HOME');
        if ($xdgData) {
            return $xdgData.DIRECTORY_SEPARATOR.'whisper.cpp';
        }

        return getenv('HOME').DIRECTORY_SEPARATOR.'.local'.
            DIRECTORY_SEPARATOR.'share'.DIRECTORY_SEPARATOR.'whisper.cpp';
    }

    /**
     * Get the underlying context
     */
    public function getContext(): WhisperContext
    {
        return $this->context;
    }

    /**
     * Get the current parameters
     */
    public function getParams(): WhisperFullParams
    {
        return $this->params;
    }

    /**
     * Set new parameters
     */
    public function setParams(WhisperFullParams $params): self
    {
        $this->params = $params;
        return $this;
    }
}
