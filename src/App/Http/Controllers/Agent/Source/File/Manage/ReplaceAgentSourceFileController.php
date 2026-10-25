<?php

declare(strict_types=1);

namespace App\Http\Controllers\Agent\Source\File\Manage;

use App\Http\Requests\Agent\Source\File\ReplaceKnowledgeFileRequest;
use Domain\Agent\Models\Agent;
use Domain\Knowledge\Source\File\Actions\ReplaceSourceFileAction;
use Domain\Knowledge\Source\File\Models\SourceFile;

/**
 * Replaces one of the agent's knowledge files with a new version and trains
 * it again.
 */
class ReplaceAgentSourceFileController
{
    /**
     * @return array{id: int}
     */
    public function __invoke(ReplaceKnowledgeFileRequest $request, Agent $agent, SourceFile $file, ReplaceSourceFileAction $replace): array
    {
        // {file} binds globally by id: only a file this agent uses may be replaced through it.
        abort_unless($agent->files()->whereKey($file->id)->exists(), 404);

        return ['id' => $replace->execute($agent, $file, $request->toData())->id];
    }
}
