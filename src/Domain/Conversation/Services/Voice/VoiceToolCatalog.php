<?php

declare(strict_types=1);

namespace Domain\Conversation\Services\Voice;

use Domain\Agent\Models\Agent;
use Domain\Conversation\Data\Memory\ChatViewerData;
use Domain\Conversation\Data\Voice\VoiceToolLabelData;
use Domain\Conversation\Services\Tooling\Routing\SurfaceToolFilter;
use Domain\Conversation\Services\Tooling\Routing\UnavailableToolFilter;
use Domain\Integration\Connector\Data\Catalog\ConnectorData;
use Domain\Integration\Connector\Services\ConnectorCatalog;
use Domain\Integration\Mcp\Models\AgentMcpServer;
use Domain\Integration\Tool\Access\ToolApprovalPolicy;
use Domain\Integration\Tool\Data\ToolEntryData;
use Domain\Integration\Tool\Registry\ToolDisplayResolver;
use Domain\Integration\Tool\Registry\ToolRegistry;
use Domain\Integration\Tool\ValueObjects\ToolName;
use Domain\Knowledge\Retrieval\Services\KnowledgeSearchSwitch;
use Illuminate\Support\Str;

/**
 * The tools a voice call offers: knowledge search first (a call has no
 * reference material pasted into the question, so the model looks things up
 * itself; left out for an agent that does not search its knowledge), then
 * the agent's own tools. A tool that needs a person's approval
 * is offered to a signed-in chatter or staff account: there is nothing to
 * click in a call, so they say yes out loud ({@see VoiceToolApprovals}). An
 * anonymous visitor never gets one, since the call would run with the
 * workspace's own connector credentials. Tools whose result has to be
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
        private ToolDisplayResolver $display,
        private ToolApprovalPolicy $approvalPolicy,
        private KnowledgeSearchSwitch $knowledgeSearch,
        private UnavailableToolFilter $unavailableFilter,
    ) {}

    /**
     * The agent's tools the call offers, by name. `$viewer` is the signed-in
     * caller; null for an anonymous visitor, who gets no tool that needs
     * approval.
     *
     * @return array<string, ToolEntryData>
     */
    public function for(Agent $agent, string $conversationId, ?ChatViewerData $viewer = null): array
    {
        $screenOnly = $this->catalog->all()
            ->reject(static fn (ConnectorData $connector): bool => $connector->voice)
            ->map(static fn (ConnectorData $connector): string => ToolName::mcpPrefix(AgentMcpServer::makeSlug($connector->slug)))
            ->values()
            ->all();

        $limit = max(0, (int) config('chatvia.voice.max_tools', 20));
        $tools = [];

        // Withheld like in the typed chat: a capability without a license key.
        $entries = $this->unavailableFilter->apply($this->surfaceFilter->apply($this->registry->for($agent), $conversationId));

        foreach ($entries as $entry) {
            if (count($tools) >= $limit) {
                break;
            }

            if ($entry->name === self::KNOWLEDGE_TOOL
                || isset($tools[$entry->name])
                || ! $entry->toolName()->isValidForLlm()
                || ($screenOnly !== [] && Str::startsWith($entry->name, $screenOnly))
                || (! $viewer instanceof ChatViewerData && $this->approvalPolicy->requiresApproval($agent, $entry->toolName()))) {
                continue;
            }

            $tools[$entry->name] = $entry;
        }

        return $tools;
    }

    /**
     * How the call view names each tool, as the typed chat does. First-party
     * plumbing (forms, leads) is left out: the typed chat hides it too.
     *
     * @param  array<string, ToolEntryData>  $tools
     * @return array<string, VoiceToolLabelData>
     */
    public function labels(Agent $agent, array $tools): array
    {
        $labels = [];

        foreach ($tools as $name => $entry) {
            $tool = $entry->toolName();
            $display = $this->display->resolve($agent, $tool);

            if (! $display->isBuiltin) {
                $labels[$name] = new VoiceToolLabelData($display->kind, $display->label, $display->iconUrl, $tool->isMcp() ? $tool->tool : null);
            }
        }

        return $labels;
    }

    /**
     * The session's `tools` list: knowledge search (unless the agent does not
     * search its knowledge), then each tool in the Realtime shape.
     *
     * @param  array<string, ToolEntryData>  $tools
     * @return list<array<string, mixed>>
     */
    public function definitions(Agent $agent, array $tools): array
    {
        $realtime = array_map(static fn (ToolEntryData $entry): array => $entry->toRealtimeFormat(), array_values($tools));

        if (! $this->knowledgeSearch->for($agent)) {
            return $realtime;
        }

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
            ...$realtime,
        ];
    }
}
