<?php

declare(strict_types=1);

use Codewithkyrian\Whisper\ModelLoader;
use Codewithkyrian\Whisper\SegmentData;
use Codewithkyrian\Whisper\WhisperContext;
use Codewithkyrian\Whisper\WhisperContextParameters;
use Codewithkyrian\Whisper\WhisperException;
use Codewithkyrian\Whisper\WhisperFullParams;

use function Codewithkyrian\Whisper\outputSrt;
use function Codewithkyrian\Whisper\readAudio;
use function Codewithkyrian\Whisper\toTimestamp;

require_once __DIR__.'/../vendor/autoload.php';

ini_set('memory_limit', -1);

$modelPath = ModelLoader::loadModel('tiny.en');
$audioPath = __DIR__.'/sounds/jfk.wav';

try {
    $contextParams = WhisperContextParameters::default();
    $ctx = new WhisperContext($modelPath, $contextParams);

    $fullParams = WhisperFullParams::default();

    $audio = readAudio($audioPath);

    $ctx->fullParallel($audio, $fullParams, 4);

    $segments = [];
    $numSegments = $ctx->nSegments();
    for ($i = 0; $i < $numSegments; $i++) {
        $segment = $ctx->getSegmentText($i);
        $startTimestamp = $ctx->getSegmentStartTime($i);
        $endTimestamp = $ctx->getSegmentEndTime($i);

        // Print to console
        printf(
            "[%s - %s]: %s\n",
            toTimestamp($startTimestamp),
            toTimestamp($endTimestamp),
            $segment
        );

        $segments[] = new SegmentData($i, $startTimestamp, $endTimestamp, $segment);
    }

    // Create output files
    $transcriptionPath = __DIR__.'/outputs/transcription_parallel.srt';
    outputSrt($segments, $transcriptionPath);
    dd(\Codewithkyrian\Whisper\timeUsage());
} catch (WhisperException $e) {
    fprintf(STDERR, "Whisper error: %s\n", $e->getMessage());
    exit(1);
} catch (Exception $e) {
    fprintf(STDERR, "Error: %s\n", $e->getMessage());
    exit(1);
}
