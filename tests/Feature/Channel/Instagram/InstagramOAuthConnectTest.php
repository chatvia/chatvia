<?php

use App\Identity\Workspace\Models\User;
use Domain\Channel\Instagram\Models\AgentInstagramAccount;
use Domain\Channel\Instagram\Services\InstagramClient;
use Domain\Channel\Services\MetaOAuthClient;
use Domain\Membership\Team\Services\TeamService;
use Tests\Support\Instagram\FakeInstagramClient;
use Tests\Support\Instagram\FakeInstagramOAuthClient;

/**
 * The Instagram connect flow is OAuth (Facebook Login for Business, Page-linked):
 * redirect → callback → pick an IG account. The pending state + the granted
 * accounts live in the SESSION (so they survive the cross-site round-trip
 * independent of the cache store). These tests pin that stash, the CSRF state
 * check, and the final connect.
 */
beforeEach(function (): void {
    $this->owner = User::factory()->create();
    app(TeamService::class)->createDefaultTeam($this->owner);
    $this->owner->refresh();

    $this->agent = createAgent($this->owner);
    $this->agent->update(['is_public' => true]);

    $this->oauth = new FakeInstagramOAuthClient;
    $this->app->instance(MetaOAuthClient::class, $this->oauth);
});

it('redirect stashes the pending connection in the session and bounces to Facebook', function (): void {
    $res = $this->actingAs($this->owner)->get(route('agents.channels.instagram.redirect', $this->agent));

    // The state sent to Facebook is the one stashed for the callback to check.
    $state = session('instagram_oauth.state');
    expect($state)->toBeString()->toHaveLength(48)
        ->and(session('instagram_oauth.agent_id'))->toBe($this->agent->id);
    $res->assertRedirect('https://www.facebook.com/dialog/oauth?state='.$state);
});

it('callback with a matching state holds the granted accounts for the picker', function (): void {
    $res = $this->actingAs($this->owner)
        ->withSession(['instagram_oauth' => ['agent_id' => $this->agent->id, 'state' => 'good-state']])
        ->get(route('channels.instagram.callback', ['code' => 'CODE', 'state' => 'good-state']));

    // `open=instagram` re-opens the slide-over so the account picker is visible
    // instead of behind a closed sheet after the full-page OAuth redirect.
    $res->assertRedirect(route('agents.channels', ['agent' => $this->agent, 'open' => 'instagram']));
    expect(session('instagram_oauth_pages.agent_id'))->toBe($this->agent->id)
        ->and(session('instagram_oauth_pages.pages'))->toHaveCount(1);
    // Not connected until the workspace actually picks an account.
    expect(AgentInstagramAccount::query()->count())->toBe(0);
});

it('callback whose code exchange fails stores no accounts and says why', function (): void {
    $this->oauth->userToken = null;

    $res = $this->actingAs($this->owner)
        ->withSession(['instagram_oauth' => ['agent_id' => $this->agent->id, 'state' => 'good-state']])
        ->get(route('channels.instagram.callback', ['code' => 'CODE', 'state' => 'good-state']));

    $res->assertRedirect(route('agents.channels', ['agent' => $this->agent, 'open' => 'instagram']))
        ->assertSessionHasErrors(['instagram' => 'No Instagram professional accounts came back. Make sure your Instagram account is Professional and linked to a Facebook Page you manage, then reconnect.']);
    expect(session('instagram_oauth_pages'))->toBeNull();
});

it('callback with a mismatched state bails without storing accounts', function (): void {
    $res = $this->actingAs($this->owner)
        ->withSession(['instagram_oauth' => ['agent_id' => $this->agent->id, 'state' => 'right']])
        ->get(route('channels.instagram.callback', ['code' => 'CODE', 'state' => 'wrong']));

    $res->assertRedirect(route('agents.index'));
    expect(session('instagram_oauth_pages'))->toBeNull();
});

it('select connects the chosen Instagram account', function (): void {
    $this->app->instance(InstagramClient::class, new FakeInstagramClient);

    $res = $this->actingAs($this->owner)
        ->withSession(['instagram_oauth_pages' => [
            'agent_id' => $this->agent->id,
            'pages' => [[
                'page_id' => '998877',
                'page_name' => 'Acme Support',
                'page_access_token' => 'EAAG-page-token',
                'ig_id' => '17840012345',
                'ig_username' => 'acme.support',
            ]],
        ]])
        ->post(route('agents.channels.instagram.select', $this->agent), ['ig_id' => '17840012345']);

    $res->assertRedirect();

    $account = AgentInstagramAccount::query()->where('agent_id', $this->agent->id)->first();
    expect($account)->not->toBeNull()
        ->and($account->ig_id)->toBe('17840012345')
        ->and($account->username)->toBe('acme.support')
        ->and($account->page_id)->toBe('998877')
        ->and($account->page_access_token)->toBe('EAAG-page-token');
});
