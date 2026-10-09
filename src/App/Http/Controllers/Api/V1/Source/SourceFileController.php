<?php

namespace App\Http\Controllers\Api\V1\Source;

use App\Http\Requests\Api\V1\Source\UploadSourceFileRequest;
use App\Http\Resources\Api\V1\Agent\UploadedSourceFileData;
use Dedoc\Scramble\Attributes\Group;
use Domain\Agent\Models\Agent;
use Domain\Knowledge\Source\File\Actions\DeleteSourceFileFromAgentAction;
use Domain\Knowledge\Source\File\Actions\UploadKnowledgeSourceFileAction;
use Domain\Knowledge\Source\File\Models\SourceFile;
use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * Upload a file (PDF / Word / audio / video) as a knowledge source.
 */
#[Group(name: 'Sources', weight: 70)]
class SourceFileController
{
    /**
     * Add file source
     *
     * @return JsonResponse|array{data: UploadedSourceFileData}
     */
    public function store(UploadSourceFileRequest $request, Agent $agent, UploadKnowledgeSourceFileAction $upload): JsonResponse|array
    {
        try {
            $sourceFile = $upload->execute($agent, $request->toData());
        } catch (RuntimeException $e) {
            // Unsupported types, and MIME types the file categories do not know.
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return ['data' => UploadedSourceFileData::fromModel($sourceFile)];
    }

    /**
     * Delete file source
     */
    public function destroy(Agent $agent, int $source, DeleteSourceFileFromAgentAction $deleteFile): JsonResponse
    {
        /** @var SourceFile $file */
        $file = $agent->files()->where('source_files.id', $source)->firstOrFail();

        $deleteFile->execute($agent, $file);

        return response()->json(null, 204);
    }
}
