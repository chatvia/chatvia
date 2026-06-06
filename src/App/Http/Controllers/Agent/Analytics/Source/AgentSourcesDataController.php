<?php

namespace App\Http\Controllers\Agent\Analytics\Source;

use Domain\Agent\Models\Agent;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

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
        // Every field — including the denormalised name/mime — comes from the
        // durable analytics rollup, so a DELETED source still shows with its
        // real label. The generic fallback only covers legacy rows recorded
        // before the name column existed.
        return $agent->mostAccessedSources()
            ->limit(50)
            ->get()
            ->map(fn ($row): array => [
                'name' => (string) ($row->name ?: $this->deletedLabel($row->sub_kind)),
                'sub_kind' => $row->sub_kind,
                'mime_type' => $row->mime_type,
                'count' => (int) $row->count,
                'last_used_at' => $this->toIso($row->last_used_at),
            ])
            ->toArray();
    }

    private function deletedLabel(string $subKind): string
    {
        return match ($subKind) {
            'source_file' => 'Deleted file',
            'source_website' => 'Deleted website',
            'source_text' => 'Deleted text snippet',
            'source_youtube_video' => 'Deleted YouTube video',
            'agent_website_url' => 'Deleted web page',
            default => 'Deleted source',
        };
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
     * The pivot timestamps come back from DB::table without a tz suffix.
     * Stamp them as UTC ISO 8601 so the browser's `new Date(...)` parses
     * them correctly instead of treating the string as local time.
     */
    private function toIso(?string $value): ?string
    {
        return $value === null ? null : Carbon::parse($value, 'UTC')->toIso8601ZuluString();
    }
}
