<?php

namespace App\Http\Controllers\Agent\Source\File\Manage;

use App\Identity\Workspace\Models\User;
use Domain\Agent\Models\Agent;
use Domain\Knowledge\Source\File\Actions\DeleteAgentUploadedFilesAction;
use Illuminate\Http\Request;

class AgentKnowledgeUploadedFilesDeleteController
{
    public function __invoke(Request $request, Agent $agent, DeleteAgentUploadedFilesAction $delete): void
    {
        abort_if($request->user()->cannot('view', $agent), 404);

        /** @var User $user */
        $user = $request->user();

        $delete->execute($agent, $user);
    }
}
