<?php

namespace App\Http\Controllers\KnowledgeFiles;

use App\Identity\Workspace\Models\User;
use Domain\Knowledge\Source\File\Actions\ListUserKnowledgeFilesAction;
use Illuminate\Http\Request;

/**
 * Lists every knowledge file of the caller's workspace with the agents that
 * use it (the account-wide knowledge base page).
 */
class KnowledgeFilesListController
{
    /**
     * @return array<int, mixed>
     */
    public function __invoke(Request $request, ListUserKnowledgeFilesAction $listFiles): array
    {
        /** @var User $user */
        $user = $request->user();

        return $listFiles->execute($user);
    }
}
