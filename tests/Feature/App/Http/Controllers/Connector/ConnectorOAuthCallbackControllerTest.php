<?php

declare(strict_types=1);

use App\Identity\Workspace\Models\User;
use Domain\Integration\Mcp\Actions\Catalog\SyncMcpServerCatalogAction;
use Domain\Integration\Mcp\Models\AgentMcpServer;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Http;
use Mockery\MockInterface;
use Tests\Support\Integration\Connector\PendingConnectorOAuth;

/**
 * The connector OAuth callback over HTTP: a teammate's finished flow saves
 * the connector and its token under the workspace, where the whole team sees
 * it. The state checks of CompleteConnectorOAuthAction are unit-tested.
 */
it('saves a connector a teammate connects under the workspace, visible to the whole team', function (): void {
    $owner = User::factory()->create();
    $teammate = teammateWithPermissions($owner, ['account.connectors']);
    Http::fake([
        'auth.example.test/token' => Http::response([
            'access_token' => 'fresh-access',
            'refresh_token' => 'fresh-refresh',
            'expires_in' => 3600,
        ]),
    ]);
    $this->mock(SyncMcpServerCatalogAction::class, function (MockInterface $mock): void {
        $mock->shouldReceive('execute')->andReturn(new Collection);
    });
    PendingConnectorOAuth::stash('team-state', $teammate, 'team-binding');

    $this->actingAs($teammate)
        ->withSession(['connectors.oauth.session_binding' => 'team-binding'])
        ->get(route('connectors.oauth.callback', ['state' => 'team-state', 'code' => 'auth-code']))
        ->assertRedirect(route('integrations.connectors.edit'))
        ->assertSessionHas('success');

    $server = AgentMcpServer::query()->where('connector_slug', 'notion')->sole();
    expect($server->workspace_id)->toBe($owner->workspace()->id)
        ->and($server->auth_value)->toBe('fresh-access');

    $this->actingAs($teammate)
        ->getJson(route('integrations.connectors.data'))
        ->assertOk()
        ->assertJsonFragment(['connector_slug' => 'notion']);
});
