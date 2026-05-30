<?php

namespace App\Http\Controllers\Api\V1\Sources;

use App\Http\Requests\Api\V1\Sources\UploadSourceFileRequest;
use App\Http\Resources\Api\V1\UploadedSourceFileData;
use Dedoc\Scramble\Attributes\Group;
use Domain\Agents\Models\Agent;
use Domain\Sources\Files\Actions\UploadKnowledgeSourceFileAction;
use Domain\Sources\Files\Models\SourceFile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Upload a file (PDF / Word / audio / video) as a knowledge source.
 */
#[Group(name: 'Knowledge Sources', weight: 70)]
class SourceFileController
{
    /** @return JsonResponse|array{data: UploadedSourceFileData} */
    public function store(UploadSourceFileRequest $request, Agent $agent, UploadKnowledgeSourceFileAction $upload): JsonResponse|array
    {
        try {
            $sourceFile = $upload->execute($agent, $request->file('file'));
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return ['data' => UploadedSourceFileData::fromModel($sourceFile)];
    }

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
