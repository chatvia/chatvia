<?php

namespace App\Http\Controllers\Agents\Sources;

use App\Data\Resources\Agent\SourceFileDataResource;
use App\Models\Agent;
use Exception;
use Illuminate\Http\Request;

class AgentSourcesFilesController
{
    public function __invoke(Request $request, Agent $agent)
    {
        abort_if(
            boolean: $request->user()->cannot('view', $agent),
            code: 404,
        );

        $request->validate([
            'file_type' => ['nullable', 'in:pdf,word,audio,video'],
        ]);

        $fileType = $request->input('file_type');

        if ($fileType) {
            // Filter by specific file type
            $files = match ($fileType) {
                'pdf' => $agent->pdfFiles()->withCount('agents')->get(),
                'word' => $agent->wordFiles()->withCount('agents')->get(),
                'audio' => $agent->audioFiles()->withCount('agents')->get(),
                'video' => $agent->videoFiles()->withCount('agents')->get(),
                default => throw new Exception('Invalid file type'),
            };
        } else {
            // Return all file types for the unified Files page
            $files = $agent->files()->withCount('agents')->get();
        }

        return SourceFileDataResource::fromFiles($files);
    }
}
