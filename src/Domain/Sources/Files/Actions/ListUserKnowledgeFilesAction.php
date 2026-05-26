<?php

namespace Domain\Sources\Files\Actions;

use App\Auth\Models\User;
use Domain\Sources\Files\Data\KnowledgeBase\KnowledgeFileData;
use Domain\Sources\Files\Models\SourceFile;

class ListUserKnowledgeFilesAction
{
    /**
     * @return array<int, KnowledgeFileData>
     */
    public function execute(User $user): array
    {
        $data = SourceFile::query()
            ->join('agent_sources', function ($join): void {
                $join->on('source_files.id', '=', 'agent_sources.sourceable_id')
                    ->where('agent_sources.sourceable_type', '=', SourceFile::class);
            })
            ->join('agents', 'agents.id', '=', 'agent_sources.agent_id')
            ->where('agents.user_id', $user->id)
            ->select([
                'source_files.*',
                'agents.id as agent_id',
                'agents.name as agent_name',
                'agents.sub_domain as agent_sub_domain',
            ])
            ->get()
            ->map(function (SourceFile $file) {
                $file->setAttribute('agent', (object) [
                    'id' => $file->getAttribute('agent_id'),
                    'name' => $file->getAttribute('agent_name'),
                    'sub_domain' => $file->getAttribute('agent_sub_domain'),
                ]);

                return $file;
            });

        return KnowledgeFileData::fromCollection($data);
    }
}
