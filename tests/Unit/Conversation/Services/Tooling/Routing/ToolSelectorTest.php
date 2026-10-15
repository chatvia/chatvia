<?php

use App\Identity\Workspace\Models\User;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Models\Message;
use Domain\Conversation\Services\Tooling\Routing\ApprovalToolFilter;
use Domain\Conversation\Services\Tooling\Routing\StickyToolResolver;
use Domain\Conversation\Services\Tooling\Routing\SurfaceToolFilter;
use Domain\Conversation\Services\Tooling\Routing\ToolSelector;
use Domain\Integration\Mcp\Models\AgentMcpServer;
use Domain\Integration\Tool\Data\ToolEntry;
use Domain\Integration\Tool\Data\ToolName;
use Domain\Integration\Tool\Registry\ToolRegistry;
use Domain\Integration\Tool\Routing\AlwaysAvailableToolResolver;
use Domain\Integration\Tool\Routing\SemanticToolRouter;
use Illuminate\Support\Facades\DB;

function tsEntry(string $name): ToolEntry
{
    return new ToolEntry(kind: ToolName::parse($name)->kind, sourceId: 'x:'.$name, name: $name, description: '', parameters: []);
}

/** Attach a custom MCP server to the agent, optionally marked always-on. */
function tsAttach(Agent $agent, string $slug, bool $alwaysAvailable): AgentMcpServer
{
    $server = AgentMcpServer::create([
        'workspace_id' => $agent->workspace_id,
        'name' => ucfirst($slug),
        'slug' => $slug,
        'url' => "https://example.test/mcp/{$slug}",
        'transport' => 'streamable_http',
        'auth_type' => 'none',
        'is_enabled' => true,
    ]);

    DB::table('agent_mcp_server')->insert(['agent_id' => $agent->id, 'mcp_server_id' => $server->id, 'always_available' => $alwaysAvailable]);

    return $server;
}

/**
 * @param  array<int, ToolEntry>  $pool  the agent's full pool
 * @param  array<int, ToolEntry>  $routed  what the semantic router would return
 */
function tsSelector(array $pool, array $routed): ToolSelector
{
    $registry = new class($pool) extends ToolRegistry
    {
        /** @param array<int, ToolEntry> $pool */
        public function __construct(private readonly array $pool) {}

        public function for(Agent $agent): array
        {
            return $this->pool;
        }
    };

    $router = new class($routed) extends SemanticToolRouter
    {
        /** @param array<int, ToolEntry> $routed */
        public function __construct(private readonly array $routed) {}

        public function route(string $userMessage, Agent $agent): array
        {
            return $this->routed;
        }
    };

    return new ToolSelector($registry, $router, app(StickyToolResolver::class), app(SurfaceToolFilter::class), app(AlwaysAvailableToolResolver::class), app(ApprovalToolFilter::class));
}

beforeEach(function (): void {
    $this->agent = createAgent(User::factory()->create());
});

it('ships always-on connector tools even when routing returns nothing', function (): void {
    tsAttach($this->agent, 'tutor', alwaysAvailable: true);
    tsAttach($this->agent, 'crm', alwaysAvailable: false);
    $pool = [tsEntry('mcp__tutor__ask'), tsEntry('mcp__tutor__correct'), tsEntry('mcp__crm__search'), tsEntry('send_email')];

    $shipped = tsSelector($pool, [])->selectForTurn($this->agent, 'hi', null);

    expect(array_column(array_column($shipped, 'function'), 'name'))->toBe(['mcp__tutor__ask', 'mcp__tutor__correct']);
});

it('unions always-on tools with the routed set without duplicates', function (): void {
    tsAttach($this->agent, 'tutor', alwaysAvailable: true);
    $pool = [tsEntry('mcp__tutor__ask'), tsEntry('mcp__tutor__correct'), tsEntry('send_email')];

    $shipped = tsSelector($pool, [tsEntry('send_email'), tsEntry('mcp__tutor__ask')])->selectForTurn($this->agent, 'email the tutor', null);

    expect(array_column(array_column($shipped, 'function'), 'name'))->toBe(['send_email', 'mcp__tutor__ask', 'mcp__tutor__correct']);
});

it('ignores an always-on flag on a disabled connector and ships nothing for an unmatched turn', function (): void {
    $server = tsAttach($this->agent, 'tutor', alwaysAvailable: true);
    $server->update(['is_enabled' => false]);

    expect(tsSelector([tsEntry('mcp__tutor__ask')], [])->selectForTurn($this->agent, 'hi', null))->toBe([]);
});

it('caps always-on tools at ten even if a pinned catalog grew', function (): void {
    tsAttach($this->agent, 'big', alwaysAvailable: true);
    $pool = array_map(fn (int $i): ToolEntry => tsEntry("mcp__big__tool_{$i}"), range(1, 14));

    expect(tsSelector($pool, [])->selectForTurn($this->agent, 'hi', null))->toHaveCount(10);
});

it('skips the router entirely when every tool in the pool is always-on', function (): void {
    tsAttach($this->agent, 'tutor', alwaysAvailable: true);
    $pool = [tsEntry('mcp__tutor__ask'), tsEntry('mcp__tutor__correct')];

    $registry = new class($pool) extends ToolRegistry
    {
        /** @param array<int, ToolEntry> $pool */
        public function __construct(private readonly array $pool) {}

        public function for(Agent $agent): array
        {
            return $this->pool;
        }
    };
    $router = new class extends SemanticToolRouter
    {
        public function __construct() {}

        public function route(string $userMessage, Agent $agent): array
        {
            throw new RuntimeException('router must not run for a fully pinned pool');
        }
    };
    $selector = new ToolSelector($registry, $router, app(StickyToolResolver::class), app(SurfaceToolFilter::class), app(AlwaysAvailableToolResolver::class), app(ApprovalToolFilter::class));

    expect(array_column(array_column($selector->selectForTurn($this->agent, 'hi', null), 'function'), 'name'))->toBe(['mcp__tutor__ask', 'mcp__tutor__correct']);
});

it('pulls tools used in recent turns back in when routing skips them', function (): void {
    $conversation = Conversation::factory()->create();
    Message::factory()->create([
        'chat_conversation_id' => $conversation->id,
        'role' => 'assistant',
        'metadata' => ['tool_calls' => ['mcp__crm__get_issue', 'send_email']],
    ]);
    $pool = [tsEntry('send_email'), tsEntry('mcp__crm__search'), tsEntry('mcp__crm__get_issue')];

    $shipped = tsSelector($pool, [tsEntry('send_email')])->selectForTurn($this->agent, 'tell me more about #3', $conversation->external_id);

    expect(array_column(array_column($shipped, 'function'), 'name'))->toBe(['send_email', 'mcp__crm__get_issue']);
});

it('ships the full pool when routing fails', function (): void {
    $pool = [tsEntry('send_email'), tsEntry('mcp__crm__search')];

    $registry = new class($pool) extends ToolRegistry
    {
        /** @param array<int, ToolEntry> $pool */
        public function __construct(private readonly array $pool) {}

        public function for(Agent $agent): array
        {
            return $this->pool;
        }
    };
    $router = new class extends SemanticToolRouter
    {
        public function __construct() {}

        public function route(string $userMessage, Agent $agent): array
        {
            throw new RuntimeException('embedding API down');
        }
    };
    $selector = new ToolSelector($registry, $router, app(StickyToolResolver::class), app(SurfaceToolFilter::class), app(AlwaysAvailableToolResolver::class), app(ApprovalToolFilter::class));

    expect(array_column(array_column($selector->selectForTurn($this->agent, 'hi', null), 'function'), 'name'))->toBe(['send_email', 'mcp__crm__search']);
});
