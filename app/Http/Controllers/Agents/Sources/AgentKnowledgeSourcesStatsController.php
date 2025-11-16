<?php

namespace App\Http\Controllers\Agents\Sources;

use App\Models\Agent;
use Illuminate\Http\Request;

class AgentKnowledgeSourcesStatsController
{
    public function __invoke(Request $request, Agent $agent)
    {
        return [
            'websites' => $agent->websites()->where('status', 'ingested')->count(),
            'texts' => $agent->texts()->where('status', 'ingested')->count(),
            'pdf' => $agent->files()
                ->where('status', 'ingested')
                ->where('mime_type', 'application/pdf')
                ->count(),
            'word' => $agent->files()
                ->where('status', 'ingested')
                ->whereIn('mime_type', [
                    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                    'application/msword',
                    'application/vnd.oasis.opendocument.text',
                ])
                ->count(),
            'markdown' => $agent->files()
                ->where('status', 'ingested')
                ->where('mime_type', 'text/markdown')
                ->count(),
            'audio' => $agent->files()
                ->where('status', 'ingested')
                ->whereIn('mime_type', [
                    'audio/mpeg',
                    'audio/mp4',
                    'audio/x-m4a',
                    'audio/wav',
                    'audio/x-wav',
                    'audio/webm',
                ])
                ->count(),
            'video' => $agent->files()
                ->where('status', 'ingested')
                ->whereIn('mime_type', [
                    'video/mp4',
                    'video/quicktime',
                    'video/x-msvideo',
                    'video/x-matroska',
                    'video/webm',
                ])
                ->count(),
            'youtube' => $agent->youtubeVideos()->where('status', 'ingested')->count(),
        ];
    }
}
