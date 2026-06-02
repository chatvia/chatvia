<?php

namespace Domain\Source\File\Actions;

use App\Workspace\Models\User;
use Domain\Source\File\Data\KnowledgeBase\KnowledgeFileData;
use Domain\Source\File\Models\SourceFile;

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
