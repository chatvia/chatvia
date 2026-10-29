<?php

use App\Identity\Workspace\Models\User;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Models\Message;
use Domain\Conversation\Services\Tooling\Routing\ApprovalToolFilter;
use Domain\Conversation\Services\Tooling\Routing\StickyToolResolver;
use Domain\Conversation\Services\Tooling\Routing\SurfaceToolFilter;
use Domain\Conversation\Services\Tooling\Routing\ToolSelector;
use Domain\Conversation\Services\Tooling\Routing\UnavailableToolFilter;
use Domain\Integration\Mcp\Models\AgentMcpServer;
use Domain\Integration\Tool\Data\ToolEntryData;
use Domain\Integration\Tool\Registry\ToolRegistry;
use Domain\Integration\Tool\Routing\AlwaysAvailableToolResolver;
use Domain\Integration\Tool\Routing\SemanticToolRouter;
use Domain\Integration\Tool\ValueObjects\ToolName;

/**
 * The tools offered on a turn: the semantic router's pick, joined by the
 * agent's always-on connector tools and the tools of recent turns, without
 * duplicates. A router failure offers the whole pool.
 */
function tsEntry(string $name): ToolEntryData
{
    return new ToolEntryData(kind: ToolName::parse($name)->kind, sourceId: 'x:'.$name, name: $name, description: '', parameters: []);
}

/** Attach a custom MCP server to the agent, optionally marked always-on. */
function tsAttach(Agent $agent, string $slug, bool $alwaysAvailable): AgentMcpServer
{
    return AgentMcpServer::factory()->attachedTo($agent, $alwaysAvailable)->create(['slug' => $slug]);
}

/**
 * @param  array<int, ToolEntryData>  $pool  the agent's full pool
 * @param  array<int, ToolEntryData>|null  $routed  what the semantic router would return; null when it fails
 */
function tsSelector(array $pool, ?array $routed): ToolSelector
{
    $registry = new class($pool) extends ToolRegistry
    {
        /** @param array<int, ToolEntryData> $pool */
        public function __construct(private readonly array $pool) {}

        public function for(Agent $agent): array
        {
            return $this->pool;
        }
    };

    $router = new class($routed) extends SemanticToolRouter
    {
        /** @param array<int, ToolEntryData>|null $routed */
        public function __construct(private readonly ?array $routed) {}

        public function route(string $userMessage, Agent $agent): array
        {
            return $this->routed ?? throw new RuntimeException('embedding API down');
        }
    };

    return new ToolSelector($registry, $router, app(StickyToolResolver::class), app(SurfaceToolFilter::class), app(AlwaysAvailableToolResolver::class), app(ApprovalToolFilter::class), app(UnavailableToolFilter::class));
}

/** @return list<string> */
function tsNames(array $shipped): array
{
    return array_column(array_column($shipped, 'function'), 'name');
}

beforeEach(function (): void {
    $this->agent = createAgent(User::factory()->create());
});

it('joins the routed tools with the always-on and recently used ones, without duplicates', function (): void {
    tsAttach($this->agent, 'tutor', alwaysAvailable: true);
    $conversation = Conversation::factory()->forAgent($this->agent)->hostedChat()->create();
    Message::factory()->fromAssistant()->withMetadata(['tool_calls' => ['mcp__crm__get_issue', 'send_email']])->create([
        'chat_conversation_id' => $conversation->id,
    ]);
    $pool = [tsEntry('mcp__tutor__ask'), tsEntry('mcp__tutor__correct'), tsEntry('send_email'), tsEntry('mcp__crm__search'), tsEntry('mcp__crm__get_issue')];

    $shipped = tsSelector($pool, [tsEntry('send_email'), tsEntry('mcp__tutor__ask')])->selectForTurn($this->agent, 'tell me more about #3', $conversation->external_id);

    expect(tsNames($shipped))->toEqualCanonicalizing(['send_email', 'mcp__tutor__ask', 'mcp__tutor__correct', 'mcp__crm__get_issue'])
        ->and(tsNames($shipped))->toHaveCount(4);
});

it('ships the full pool when routing fails', function (): void {
    $pool = [tsEntry('send_email'), tsEntry('mcp__crm__search')];

    expect(tsNames(tsSelector($pool, null)->selectForTurn($this->agent, 'hi', null)))->toBe(['send_email', 'mcp__crm__search']);
});
