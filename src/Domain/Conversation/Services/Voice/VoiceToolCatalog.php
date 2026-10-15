<?php

declare(strict_types=1);

namespace Domain\Conversation\Services\Voice;

use Domain\Agent\Models\Agent;
use Domain\Conversation\Services\Tooling\Routing\SurfaceToolFilter;
use Domain\Integration\Connector\Data\Catalog\ConnectorData;
use Domain\Integration\Connector\Services\ConnectorCatalog;
use Domain\Integration\Mcp\Models\AgentMcpServer;
use Domain\Integration\Tool\Data\ToolEntry;
use Domain\Integration\Tool\Data\ToolName;
use Domain\Integration\Tool\Registry\ToolRegistry;
use Illuminate\Support\Str;

/**
 * The tools a voice call offers: knowledge search first (a call has no
 * reference material pasted into the question, so the model looks things up
 * itself), then the agent's own tools. A tool that needs a person's approval
 * is offered too: there is nothing to click in a call, so the visitor says
 * yes out loud ({@see VoiceToolApprovals}). Tools whose result has to be
 * seen (charts, forms: connectors marked `voice: false`) and tools the
 * surface does not allow are left out.
 *
 * A call's tools are fixed when it starts (there is no per-message routing),
 * so the list is capped at `chatvia.voice.max_tools`.
 */
final readonly class VoiceToolCatalog
{
    /** The knowledge search tool, answered by the call's worker itself. */
    public const string KNOWLEDGE_TOOL = 'search_knowledge';

    public function __construct(
        private ToolRegistry $registry,
        private SurfaceToolFilter $surfaceFilter,
        private ConnectorCatalog $catalog,
    ) {}

    /**
     * The agent's tools the call offers, by name.
     *
     * @return array<string, ToolEntry>
     */
    public function for(Agent $agent, string $conversationId): array
    {
        $screenOnly = $this->catalog->all()
            ->reject(static fn (ConnectorData $connector): bool => $connector->voice)
            ->map(static fn (ConnectorData $connector): string => ToolName::mcpPrefix(AgentMcpServer::makeSlug($connector->slug)))
            ->values()
            ->all();

        $limit = max(0, (int) config('chatvia.voice.max_tools', 20));
        $tools = [];

        foreach ($this->surfaceFilter->apply($this->registry->for($agent), $conversationId) as $entry) {
            if (count($tools) >= $limit) {
                break;
            }

            if ($entry->name === self::KNOWLEDGE_TOOL
                || isset($tools[$entry->name])
                || ! $entry->toolName()->isValidForLlm()
                || ($screenOnly !== [] && Str::startsWith($entry->name, $screenOnly))) {
                continue;
            }

            $tools[$entry->name] = $entry;
        }

        return $tools;
    }

    /**
     * The session's `tools` list: knowledge search, then each tool in the
     * Realtime shape.
     *
     * @param  array<string, ToolEntry>  $tools
     * @return list<array<string, mixed>>
     */
    public function definitions(array $tools): array
    {
        return [
            [
                'type' => 'function',
                'name' => self::KNOWLEDGE_TOOL,
                'description' => 'Search what this business knows: its documents, website pages and prepared answers. Called before every answer. For small talk (a greeting, thanks), call it with an empty query.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'query' => [
                            'type' => 'string',
                            'description' => 'The full question to look up, written out on its own (for example "What are the opening hours on Sunday?", not "and on Sunday?"). Empty for small talk.',
                        ],
                    ],
                    'required' => ['query'],
                ],
            ],
            ...array_map(static fn (ToolEntry $entry): array => $entry->toRealtimeFormat(), array_values($tools)),
        ];
    }
}
