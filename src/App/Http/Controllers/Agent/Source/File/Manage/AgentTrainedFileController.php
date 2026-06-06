<?php

namespace App\Http\Controllers\Agent\Source\File\Manage;

use Domain\Agent\Actions\Sources\UnlinkSourceFileFromAgentAction;
use Domain\Agent\Data\Resources\Sources\AgentSourcesListData;
use Domain\Agent\Models\Agent;
use Domain\Knowledge\Source\File\Models\SourceFile;
use Illuminate\Http\Request;

class AgentTrainedFileController
{
    /**
     * @return AgentSourcesListData[]
     */
    public function index(Request $request, Agent $agent): array
    {
        abort_if($request->user()->cannot('update', $agent), 404);

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

    public function store(Request $request, Agent $agent, int $fileId): void
    {
        abort_if($request->user()->cannot('update', $agent), 404);

        $request->validate(['file_id' => ['required', 'exists:source_files,id']]);

        /** @var SourceFile $file */
        $file = SourceFile::findOrFail($fileId);

        abort_if($request->user()->cannot('view', $file), 403);

        $agent->files()->attach($file->id);
    }

    public function destroy(Request $request, Agent $agent, SourceFile $file, UnlinkSourceFileFromAgentAction $unlink): void
    {
        abort_if($request->user()->cannot('update', $agent), 404);

        if (! $unlink->execute($agent, $file)) {
            abort(400, 'File is linked to multiple agents and cannot be unlinked.');
        }
    }
}
