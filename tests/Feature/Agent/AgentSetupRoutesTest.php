<?php

use App\Identity\Chatter\Models\Chatter;
use App\Identity\Workspace\Models\User;
use App\Identity\Workspace\Services\AgentSignInTokenService;
use Domain\Agent\Jobs\PurgeAgentJob;
use Domain\Agent\Models\Agent;
use Illuminate\Support\Facades\Bus;

/**
 * The dashboard routes that change an agent itself: rename, update the
 * persona, set the custom domain, open it to the internet, renew its signing
 * secret, delete it. Each one is gated by its agent permission, and does
 * what it says.
 */
beforeEach(function (): void {
    Bus::fake([PurgeAgentJob::class]);

    $this->owner = User::factory()->create();
    $this->agent = createAgent($this->owner, ['name' => 'Support Bot', 'is_public' => false]);
});

/**
 * Every agent permission except the given one.
 *
 * @return list<string>
 */
function setupRoutesAllAgentPermissionsBut(string $permission): array
{
    return array_values(array_filter(
        array_keys(config('team-permissions.permissions')),
        fn (string $code): bool => str_starts_with($code, 'agent.') && $code !== $permission,
    ));
}

// [method, route name, permission, body, status once allowed, whether the change was made]
dataset('agent setup routes', [
    'rename' => ['PATCH', 'agents.rename', 'agent.setup', ['name' => 'Renamed Bot', 'slug' => 'renamed-bot'], 302,
        fn (Agent $agent): bool => $agent->name === 'Renamed Bot' && $agent->slug === 'renamed-bot'],
    'update the persona' => ['PATCH', 'agents.update', 'agent.setup', ['system_instructions' => 'Answer in one line.'], 200,
        fn (Agent $agent): bool => $agent->system_instructions === 'Answer in one line.'],
    'set the custom domain' => ['PATCH', 'agents.domain.update', 'agent.setup', ['domain' => 'chat.acme-bot.test'], 302,
        fn (Agent $agent): bool => $agent->domain === 'chat.acme-bot.test'],
    'open it to the internet' => ['PATCH', 'agents.security.update', 'agent.setup', ['is_public' => true], 302,
        fn (Agent $agent): bool => $agent->is_public === true],
    'renew the signing secret' => ['POST', 'agents.identity.regenerate', 'agent.identity.manage', [], 302,
        fn (Agent $agent): bool => $agent->auth_secret !== test()->secretBefore],
    'delete it' => ['DELETE', 'agents.delete', 'agent.delete', [], 302,
        fn (Agent $agent): bool => $agent->trashed()],
]);

it('refuses a teammate without the permission and changes nothing', function (string $method, string $route, string $permission, array $body, int $allowed, Closure $changed): void {
    $this->secretBefore = $this->agent->ensureAuthSecret();
    $teammate = teammateWithPermissions($this->owner, setupRoutesAllAgentPermissionsBut($permission), $this->agent->team);

    $this->actingAs($teammate)
        ->json($method, route($route, $this->agent), $body)
        ->assertForbidden();

    expect($changed(Agent::withTrashed()->findOrFail($this->agent->id)))->toBeFalse();
    Bus::assertNotDispatched(PurgeAgentJob::class);
})->with('agent setup routes');

it('lets a teammate with the permission make the change', function (string $method, string $route, string $permission, array $body, int $allowed, Closure $changed): void {
    $this->secretBefore = $this->agent->ensureAuthSecret();
    $teammate = teammateWithPermissions($this->owner, [$permission], $this->agent->team);

    $this->actingAs($teammate)
        ->from(route('agents.setup', $this->agent))
        ->json($method, route($route, $this->agent), $body)
        ->assertStatus($allowed);

    expect($changed(Agent::withTrashed()->findOrFail($this->agent->id)))->toBeTrue();
})->with('agent setup routes');

it('refuses a name or an address another agent of the workspace uses', function (): void {
    $other = createAgent($this->owner, ['name' => 'Sales Bot', 'slug' => 'sales-bot']);

    $this->actingAs($this->owner)
        ->patchJson(route('agents.rename', $this->agent), ['name' => 'Sales Bot', 'slug' => 'support-bot'])
        ->assertJsonValidationErrors(['name']);
    $this->actingAs($this->owner)
        ->patchJson(route('agents.rename', $this->agent), ['name' => 'Support Two', 'slug' => $other->slug])
        ->assertJsonValidationErrors(['slug']);

    expect($this->agent->fresh()->name)->toBe('Support Bot');
});

it('stores the custom domain in lower case, clears it when empty, and refuses the app\'s own host or another agent\'s domain', function (): void {
    config(['app.url' => 'https://app.chatvia.test']);
    createAgent($this->owner, ['domain' => 'taken.acme-bot.test']);

    $this->actingAs($this->owner)
        ->patchJson(route('agents.domain.update', $this->agent), ['domain' => ' Chat.Acme-Bot.TEST '])
        ->assertRedirect();
    expect($this->agent->fresh()->domain)->toBe('chat.acme-bot.test');

    foreach (['app.chatvia.test', 'taken.acme-bot.test', 'https://chat.acme-bot.test/path'] as $refused) {
        $this->actingAs($this->owner)
            ->patchJson(route('agents.domain.update', $this->agent), ['domain' => $refused])
            ->assertJsonValidationErrors(['domain']);
    }
    expect($this->agent->fresh()->domain)->toBe('chat.acme-bot.test');

    $this->actingAs($this->owner)
        ->patchJson(route('agents.domain.update', $this->agent), ['domain' => ''])
        ->assertRedirect();
    expect($this->agent->fresh()->domain)->toBeNull();
});

it('locks a public agent again, and needs an explicit choice', function (): void {
    $this->agent->update(['is_public' => true]);

    $this->actingAs($this->owner)
        ->patchJson(route('agents.security.update', $this->agent), [])
        ->assertJsonValidationErrors(['is_public']);
    expect($this->agent->fresh()->is_public)->toBeTrue();

    $this->actingAs($this->owner)
        ->patchJson(route('agents.security.update', $this->agent), ['is_public' => false])
        ->assertRedirect();
    expect($this->agent->fresh()->is_public)->toBeFalse();
});

it('stops every sign-in token signed with the old secret once the secret is renewed', function (): void {
    $chatter = Chatter::factory()->grantedTo($this->agent)->create();
    $tokens = app(AgentSignInTokenService::class);
    $token = $tokens->issue($chatter, $this->agent)['token'];

    expect($tokens->verify($token, $this->agent->fresh())?->is($chatter))->toBeTrue();

    $this->actingAs($this->owner)
        ->post(route('agents.identity.regenerate', $this->agent))
        ->assertRedirect();

    $agent = $this->agent->fresh();

    expect($tokens->verify($token, $agent))->toBeNull()
        ->and($tokens->verify($tokens->issue($chatter, $agent)['token'], $agent)?->is($chatter))->toBeTrue();
});

it('deletes the agent: frees its address, queues its purge and goes back to the list', function (): void {
    $this->agent->update(['domain' => 'chat.acme-bot.test']);

    $this->actingAs($this->owner)
        ->delete(route('agents.delete', $this->agent))
        ->assertRedirect(route('agents.index'));

    $deleted = Agent::withTrashed()->findOrFail($this->agent->id);

    expect($deleted->trashed())->toBeTrue()
        ->and($deleted->slug)->toBeNull()
        ->and($deleted->domain)->toBeNull();
    Bus::assertDispatched(PurgeAgentJob::class);
});
