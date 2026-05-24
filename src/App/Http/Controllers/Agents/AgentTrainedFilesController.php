<?php

namespace App\Http\Controllers\Agents;

use Domain\Agents\Data\Resources\AgentSourcesListData;
use Domain\Agents\Models\Agent;
use Domain\Sources\Files\Models\SourceFile;
use Illuminate\Http\Request;

class AgentTrainedFilesController
{
    public function index(Request $request, Agent $agent): AgentSourcesListData
    {
        abort_if($request->user()->cannot('update', $agent), 403);

        $request->validate([
            'type' => ['required', 'in:pdf,word,audio,video'],
        ]);

        $data = $request->user()
            ->sourceFiles()
            ->with('agents', fn ($query) => $query->select('agents.id', 'name'))
            ->where('status', 'ingested')
            ->when($request->filled('type'), fn ($q) => $q->where('filetype', $request->input('type')))
            ->whereDoesntHave('agents', fn ($q) => $q->where('agents.id', $agent->id))
            ->select('id', 'filename', 'filetype', 'length')
            ->get();

        return AgentSourcesListData::fromSources($data);
    }

    public function link(Request $request, Agent $agent, int $fileId): void
    {
        abort_if($request->user()->cannot('update', $agent), 403);

        $request->validate([
            'file_id' => ['required', 'exists:source_files,id'],
        ]);

        /** @var SourceFile $file */
        $file = SourceFile::findOrFail($fileId);

        abort_if($request->user()->cannot('view', $file), 403);

        $agent->files()->attach($file->id);
    }

    public function unlink(Request $request, Agent $agent, SourceFile $file): void
    {
        abort_if($request->user()->cannot('update', $agent), 404);

        if ($file->agents()->count() > 1) {
            abort(400, 'File is linked to multiple agents and cannot be unlinked.');
        }

        $agent->files()->detach($file->id);
    }
}
