<?php

namespace App\Http\Controllers\Api\V1\Source;

use App\Http\Requests\Api\V1\Source\UploadSourceFileRequest;
use App\Http\Resources\Api\V1\Source\SourceData;
use Dedoc\Scramble\Attributes\Group;
use Domain\Agent\Models\Agent;
use Domain\Knowledge\Source\File\Actions\DeleteSourceFileFromAgentAction;
use Domain\Knowledge\Source\File\Actions\UploadKnowledgeSourceFileAction;
use Domain\Knowledge\Source\File\Models\SourceFile;
use Illuminate\Http\JsonResponse;

/**
 * Upload a file (PDF / Word / audio / video) as a knowledge source.
 */
#[Group(name: 'Sources', weight: 70)]
class SourceFileController
{
    /**
     * Add file source
     *
     * Only the file types the administrator turned on are accepted. Any other
     * file gets a 422 response.
     */
    public function store(UploadSourceFileRequest $request, Agent $agent, UploadKnowledgeSourceFileAction $upload): JsonResponse
    {
        return response()->json(['data' => SourceData::fromModel($upload->execute($agent, $request->toData()))], 201);
    }

    /**
     * Delete file source
     */
    public function destroy(Agent $agent, SourceFile $file, DeleteSourceFileFromAgentAction $deleteFile): JsonResponse
    {
        // {file} is looked up through the agent's own files, so a file of
        // another agent is a 404.
        $deleteFile->execute($agent, $file);

        return response()->json(null, 204);
    }
}
