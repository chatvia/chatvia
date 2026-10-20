<?php

namespace Domain\Agent\Actions\Sources;

use Domain\Agent\Data\Sources\KnowledgeAnalyzeResourceData;
use Domain\Knowledge\Source\File\Services\FileAnalyzer;
use Domain\Knowledge\Source\File\Services\SourceFileTypes;
use Illuminate\Http\UploadedFile;

/**
 * Inspects an uploaded knowledge file before training — its length, MIME type
 * and kind — so the dashboard can show what will be ingested.
 */
class AgentSourceAnalyzeFileAction
{
    public function __construct(
        private readonly FileAnalyzer $fileAnalyzer,
    ) {}

    public function execute(UploadedFile $uploadedFile): KnowledgeAnalyzeResourceData
    {
        $info = $this->fileAnalyzer->analyze($uploadedFile->getPathname(), file_extension($uploadedFile));

        return KnowledgeAnalyzeResourceData::from([
            'length' => $info->length,
            'mime_type' => $uploadedFile->getMimeType(),
            'filetype' => SourceFileTypes::category($uploadedFile),
            'is_audio_file' => is_audio_file($uploadedFile),
            'is_video_file' => is_video_file($uploadedFile),
        ]);
    }
}
