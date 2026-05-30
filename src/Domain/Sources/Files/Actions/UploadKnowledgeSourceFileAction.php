<?php

namespace Domain\Sources\Files\Actions;

use Domain\Agents\Models\Agent;
use Domain\Sources\Files\Enums\AgentFileStatusEnum;
use Domain\Sources\Files\Models\SourceFile;
use Illuminate\Http\UploadedFile;
use RuntimeException;

/**
 * Persist a knowledge-source upload to disk, attach it to the agent, and
 * queue ingestion. Throws when the file type is not one of the supported
 * kinds (PDF / Word / audio / video) — the controller maps that to a 422.
 */
class UploadKnowledgeSourceFileAction
{
    public function execute(Agent $agent, UploadedFile $file): SourceFile
    {
        if (! is_pdf_file($file) && ! is_word_file($file) && ! is_audio_file($file) && ! is_video_file($file)) {
            throw new RuntimeException('Unsupported file type. Accepted: PDF, Word, audio, video.');
        }

        return $agent->addFile($file, AgentFileStatusEnum::UPLOADED);
    }
}
