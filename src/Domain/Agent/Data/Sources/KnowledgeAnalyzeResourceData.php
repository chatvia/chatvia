<?php

declare(strict_types=1);

namespace Domain\Agent\Data\Sources;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * What the upload analyzer found out about a knowledge file before it is
 * trained: its size, type and whether it is audio or video.
 */
#[TypeScript]
class KnowledgeAnalyzeResourceData extends Data
{
    public function __construct(
        public int $length,
        public string $mime_type,
        public string $filetype,
        public bool $is_audio_file,
        public bool $is_video_file,
    ) {}
}
