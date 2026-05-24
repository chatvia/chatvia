<?php

namespace Domain\Agents\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

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
