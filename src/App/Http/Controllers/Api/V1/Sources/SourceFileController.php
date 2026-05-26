<?php

namespace App\Http\Controllers\Api\V1\Sources;

use Domain\Agents\Models\Agent;
use Domain\Sources\Files\Enums\AgentFileStatusEnum;
use Domain\Sources\Files\Models\SourceFile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @tags Knowledge Sources
 *
 * Upload a file (PDF / Word / audio / video) as a knowledge source. The
 * file is persisted, attached to the agent, and queued for ingestion.
 * Allowed MIME types match the dashboard upload — anything else is rejected.
 */
class SourceFileController
{
    /**
     * Upload file
     *
     * Multipart form upload. Field name: `file`. Max size enforced by the
     * server's upload limit and the per-user page quota.
     */
    public function store(Request $request, Agent $agent): JsonResponse
    {
        $request->validate(['file' => ['required', 'file']]);

        $file = $request->file('file');

        if (! is_pdf_file($file) && ! is_word_file($file) && ! is_audio_file($file) && ! is_video_file($file)) {
            return response()->json([
                'message' => 'Unsupported file type. Accepted: PDF, Word, audio, video.',
            ], 422);
        }

        $sourceFile = $agent->addFile($file, AgentFileStatusEnum::UPLOADED);

        return response()->json([
            'source' => [
                'id' => $sourceFile->id,
                'kind' => 'file',
                'name' => $sourceFile->filename,
                'mime_type' => $sourceFile->mime_type,
                'size_kb' => $sourceFile->size_in_kb,
                'status' => $sourceFile->status,
            ],
        ], 201);
    }

    /**
     * Delete file source
     */
    public function destroy(Request $request, Agent $agent, int $source): JsonResponse
    {
        /** @var SourceFile $file */
        $file = $agent->files()->where('source_files.id', $source)->firstOrFail();

        $file->agents()->detach($agent->id);
        $file->chunks()->delete();
        $file->delete();

        return response()->json(null, 204);
    }
}
