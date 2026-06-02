<?php

namespace App\Http\Controllers\KnowledgeFiles;

use App\Workspace\Models\User;
use Domain\Source\File\Actions\ListUserKnowledgeFilesAction;
use Illuminate\Http\Request;

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
