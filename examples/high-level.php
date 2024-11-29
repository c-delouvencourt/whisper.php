<?php

declare(strict_types=1);

use Codewithkyrian\Whisper\ModelLoader;
use Codewithkyrian\Whisper\SegmentData;
use Codewithkyrian\Whisper\Whisper;
use Codewithkyrian\Whisper\WhisperContext;
use Codewithkyrian\Whisper\WhisperContextParameters;
use Codewithkyrian\Whisper\WhisperException;
use Codewithkyrian\Whisper\WhisperFullParams;

use function Codewithkyrian\Whisper\outputSrt;
use function Codewithkyrian\Whisper\readAudio;
use function Codewithkyrian\Whisper\toTimestamp;

require_once __DIR__.'/../vendor/autoload.php';

try {
    $fullParams = WhisperFullParams::default()
        ->withNThreads(4);

    $whisper = Whisper::fromPretrained('tiny.en', baseDir: __DIR__.'/models');

    $audio = readAudio(__DIR__.'/sounds/jfk.wav');

    $segments = $whisper->transcribe($audio, 4);

    printf('Generated Segments: %d', count($segments));

    // Create output files
    $transcriptionPath = __DIR__.'/outputs/transcription.srt';
    outputSrt($segments, $transcriptionPath);
} catch (WhisperException $e) {
    fprintf(STDERR, "Whisper error: %s\n", $e->getMessage());
    exit(1);
} catch (Exception $e) {
    fprintf(STDERR, "Error: %s\n", $e->getMessage());
    exit(1);
}
