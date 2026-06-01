<?php

namespace App\Http\Controllers\Agents\Analytics\Sources;

use Domain\Agents\Models\Agent;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class AgentSourcesDataController
{
    public function __invoke(Request $request, Agent $agent)
    {
        abort_if(
            boolean: $request->user()->cannot('view', $agent),
            code: 404,
        );

        return response()->json([
            'knowledge' => $this->knowledgeSources($agent),
            'functions' => $this->functions($agent),
            'mcp_tools' => $this->mcpTools($agent),
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function knowledgeSources(Agent $agent): array
    {
        return $agent->mostAccessedSources()
            ->limit(50)
            ->get()
            ->map(fn ($row): array => [
                'name' => $this->sourceName($row),
                'sub_kind' => $row->sub_kind,
                'mime_type' => $row->file_mime_type,
                'count' => (int) $row->count,
                'last_used_at' => $this->toIso($row->last_used_at),
            ])
            ->toArray();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function functions(Agent $agent): array
    {
        return $agent->mostUsedFunctions()
            ->limit(50)
            ->get()
            ->map(fn ($row): array => [
                'name' => $row->name,
                'description' => $row->description,
                'count' => (int) $row->count,
                'last_used_at' => $this->toIso($row->last_used_at),
            ])
            ->toArray();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function mcpTools(Agent $agent): array
    {
        return $agent->mostUsedMcpTools()
            ->limit(50)
            ->get()
            ->map(fn ($row): array => [
                'tool_name' => $row->tool_name,
                'tool_description' => $row->tool_description,
                'server_name' => $row->server_name,
                'server_slug' => $row->server_slug,
                'connector_slug' => $row->connector_slug,
                'count' => (int) $row->count,
                'last_used_at' => $this->toIso($row->last_used_at),
            ])
            ->toArray();
    }

    /**
     * Each source type stores its display label in a different column —
     * pick the best one for what came back on this row. Falls back to the
     * morph alias so a row never renders blank.
     */
    private function sourceName(object $row): string
    {
        return match ($row->sub_kind) {
            'source_file' => (string) ($row->file_name ?? 'File'),
            'source_website' => (string) ($row->website_url ?? 'Website'),
            'source_text' => $this->truncateExcerpt((string) ($row->text_excerpt ?? 'Text snippet')),
            'source_youtube_video' => (string) ($row->youtube_title ?? $row->youtube_url ?? 'YouTube video'),
            'agent_website_url' => (string) ($row->website_url_url ?? 'Web page'),
            default => (string) $row->sub_kind,
        };
    }

    private function truncateExcerpt(string $excerpt): string
    {
        $excerpt = trim(preg_replace('/\s+/', ' ', $excerpt) ?? $excerpt);

        return Str::limit($excerpt, 80);
    }

    /**
     * The pivot timestamps come back from DB::table without a tz suffix.
     * Stamp them as UTC ISO 8601 so the browser's `new Date(...)` parses
     * them correctly instead of treating the string as local time.
     */
    private function toIso(?string $value): ?string
    {
        return $value === null ? null : Carbon::parse($value, 'UTC')->toIso8601ZuluString();
    }
}
