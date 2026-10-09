<?php

use App\Identity\Workspace\Models\User;
use Domain\Agent\Models\Agent;
use Domain\Integration\Mcp\Actions\SetConnectorAlwaysAvailableAction;
use Domain\Integration\Mcp\Data\Agent\SetConnectorAlwaysAvailableData;
use Domain\Integration\Mcp\Exceptions\AlwaysAvailableToolLimitException;
use Domain\Integration\Mcp\Exceptions\ConnectorNotAttachedException;
use Domain\Integration\Mcp\Models\AgentMcpServer;
use Domain\Integration\Tool\Data\ToolEntry;
use Domain\Integration\Tool\Enums\ToolKind;
use Domain\Integration\Tool\Registry\ToolRegistry;
use Illuminate\Support\Facades\DB;

function saaServer(Agent $agent, string $slug, bool $attach = true, bool $alwaysAvailable = false): AgentMcpServer
{
    $server = AgentMcpServer::create([
        'user_id' => $agent->user_id,
        'name' => ucfirst($slug),
        'slug' => $slug,
        'url' => "https://example.test/mcp/{$slug}",
        'transport' => 'streamable_http',
        'auth_type' => 'none',
        'is_enabled' => true,
    ]);

    if ($attach) {
        DB::table('agent_mcp_server')->insert(['agent_id' => $agent->id, 'mcp_server_id' => $server->id, 'always_available' => $alwaysAvailable]);
    }

    return $server;
}

/** An action whose tool pool has `$count` tools per listed slug. */
function saaAction(array $toolsPerSlug): SetConnectorAlwaysAvailableAction
{
    $pool = [];
    foreach ($toolsPerSlug as $slug => $count) {
        foreach (range(1, $count) as $i) {
            $pool[] = new ToolEntry(kind: ToolKind::Mcp, sourceId: "mcp_tool:{$slug}{$i}", name: "mcp__{$slug}__tool_{$i}", description: '', parameters: []);
        }
    }

    $registry = new class($pool) extends ToolRegistry
    {
        /** @param array<int, ToolEntry> $pool */
        public function __construct(private readonly array $pool) {}

        public function for(Agent $agent): array
        {
            return $this->pool;
        }
    };

    return new SetConnectorAlwaysAvailableAction($registry);
}

function alwaysAvailableOf(Agent $agent, AgentMcpServer $server): bool
{
    return (bool) DB::table('agent_mcp_server')->where('agent_id', $agent->id)->where('mcp_server_id', $server->id)->value('always_available');
}

beforeEach(function (): void {
    $this->agent = createAgent(User::factory()->create());
});

it('marks an attached connector always-on and back off', function (): void {
    $server = saaServer($this->agent, 'tutor');
    $action = saaAction(['tutor' => 3]);

    $action->execute($this->agent, $server, new SetConnectorAlwaysAvailableData(always_available: true));
    expect(alwaysAvailableOf($this->agent, $server))->toBeTrue();

    $action->execute($this->agent, $server, new SetConnectorAlwaysAvailableData(always_available: false));
    expect(alwaysAvailableOf($this->agent, $server))->toBeFalse();
});

it('refuses when the always-on connectors would exceed ten tools together', function (): void {
    saaServer($this->agent, 'crm', alwaysAvailable: true);
    $server = saaServer($this->agent, 'tutor');

    expect(fn () => saaAction(['crm' => 8, 'tutor' => 3])->execute($this->agent, $server, new SetConnectorAlwaysAvailableData(always_available: true)))
        ->toThrow(AlwaysAvailableToolLimitException::class, 'at most 10 tools');

    expect(alwaysAvailableOf($this->agent, $server))->toBeFalse();
});

it('allows exactly ten always-on tools and lets a pinned connector be turned off regardless', function (): void {
    $crm = saaServer($this->agent, 'crm', alwaysAvailable: true);
    $server = saaServer($this->agent, 'tutor');
    $action = saaAction(['crm' => 7, 'tutor' => 3]);

    $action->execute($this->agent, $server, new SetConnectorAlwaysAvailableData(always_available: true));
    expect(alwaysAvailableOf($this->agent, $server))->toBeTrue();

    // Turning off never checks the cap, even when the pool has grown past it.
    saaAction(['crm' => 20, 'tutor' => 20])->execute($this->agent, $crm, new SetConnectorAlwaysAvailableData(always_available: false));
    expect(alwaysAvailableOf($this->agent, $crm))->toBeFalse();
});

it('refuses a connector that is not attached to the agent', function (): void {
    $server = saaServer($this->agent, 'tutor', attach: false);

    expect(fn () => saaAction(['tutor' => 1])->execute($this->agent, $server, new SetConnectorAlwaysAvailableData(always_available: true)))
        ->toThrow(ConnectorNotAttachedException::class, 'not attached');
});
