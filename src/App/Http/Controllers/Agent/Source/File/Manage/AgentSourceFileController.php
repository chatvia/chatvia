<?php

namespace App\Http\Controllers\Agent\Source\File\Manage;

use Domain\Agent\Data\Resources\Sources\SourceFileDataResource;
use Domain\Agent\Models\Agent;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class AgentSourceFileController
{
    public function __invoke(Request $request, Agent $agent): Collection
    {
        abort_if(
            boolean: $request->user()->cannot('view', $agent),
            code: 404,
        );

        abort_if(
            boolean: ! has_permission_to_agent($agent, 'agent.sources.train.files'),
            code: 403,
        );

        $request->validate([
            'file_type' => ['nullable', 'in:pdf,word,audio,video'],
        ]);

        $fileType = $request->input('file_type');

        if ($fileType) {
            $files = match ($fileType) {
                'pdf' => $agent->pdfFiles()->withCount(['agents', 'chunks'])->get(),
                'word' => $agent->wordFiles()->withCount(['agents', 'chunks'])->get(),
                'audio' => $agent->audioFiles()->withCount(['agents', 'chunks'])->get(),
                'video' => $agent->videoFiles()->withCount(['agents', 'chunks'])->get(),
                default => throw new Exception('Invalid file type'),
            };
        } else {
            // Return only PDF and Word files for the unified Files page (excluding audio/video)
            $files = $agent->files()
                ->whereIn('mime_type', array_merge(
                    config('mimes.pdf'),
                    config('mimes.word')
                ))
                ->withCount(['agents', 'chunks'])
                ->get();
        }

        return SourceFileDataResource::fromFiles($files);
    }
}
