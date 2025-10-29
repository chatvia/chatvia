<?php

namespace App\Data\Agent;

use Spatie\LaravelData\Data;

class KnowledgeAnalyzeData extends Data
{
    public function __construct(
        public int $length,
        public string $mime_type,
        public string $filetype,
        public bool $is_audio_file,
        public bool $is_video_file,
    ) {}
}
