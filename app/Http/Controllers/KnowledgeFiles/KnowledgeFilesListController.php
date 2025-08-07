<?php

namespace App\Http\Controllers\KnowledgeFiles;

use App\Data\KnowledgeBase\KnowledgeFileData;
use Illuminate\Http\Request;

class KnowledgeFilesListController
{
    public function __invoke(Request $request)
    {
        $data = $request
            ->user()
            ->agentFiles()
            ->select([
                'agent_files.id',
                'agent_id',
                'filename',
                'filepath',
                'size_in_kb',
                'mime_type',
                'length',
                'status',
                'retrieval_count',
                'agent_files.created_at',
                'agent_files.updated_at',
            ])
            ->with('agent', function ($query) {
                $query->select(['id', 'name', 'sub_domain']);
            })
            ->get();

        return KnowledgeFileData::fromCollection($data);
    }
}
