<?php

declare(strict_types=1);

namespace App\Http\Controllers\Agent\Source\File\Manage;

use App\Http\Requests\Agent\Source\File\BulkDestroySourceFilesRequest;
use Domain\Agent\Models\Agent;
use Domain\Knowledge\Source\File\Actions\RemoveSourceFilesFromAgentAction;
use Illuminate\Http\JsonResponse;

/**
 * Removes several of the agent's files in one call: a file another agent
 * also uses is unlinked, the rest are deleted. The `agent.sources.delete`
 * permission is checked by the route middleware; the request checks that
 * every file is one the agent uses.
 */
class BulkDestroyAgentSourceFilesController
{
    public function __invoke(BulkDestroySourceFilesRequest $request, Agent $agent, RemoveSourceFilesFromAgentAction $remove): JsonResponse
    {
        return response()->json(['count' => $remove->execute($agent, $request->fileIds())]);
    }
}
