<?php

use App\Identity\Workspace\Models\User;
use Domain\Channel\Messenger\Models\AgentMessengerPage;
use Domain\Channel\Messenger\Services\MessengerClient;
use Domain\Channel\Services\MetaOAuthClient;
use Domain\Membership\Team\Services\TeamService;
use Tests\Support\Channel\Messenger\FakeMessengerClient;
use Tests\Support\Channel\Messenger\FakeMessengerOAuthClient;

/**
 * The Messenger connect flow is OAuth (Facebook Login for Business): redirect →
 * callback → pick a Page. The pending state + the granted Pages live in the
 * SESSION so they survive the cross-site round-trip to Facebook independent of
 * the cache store. These tests pin that stash, the CSRF state check, and the
 * final connect.
 */
beforeEach(function (): void {
    $this->owner = User::factory()->create();
    app(TeamService::class)->createDefaultTeam($this->owner);
    $this->owner->refresh();

    $this->agent = createAgent($this->owner);
    $this->agent->update(['is_public' => true]);

    $this->oauth = new FakeMessengerOAuthClient;
    $this->app->instance(MetaOAuthClient::class, $this->oauth);
});

it('redirect stashes the pending connection in the session and bounces to Facebook', function (): void {
    $res = $this->actingAs($this->owner)->get(route('agents.channels.messenger.redirect', $this->agent));

    // The state sent to Facebook is the one stashed for the callback to check.
    $state = session('messenger_oauth.state');
    expect($state)->toBeString()->toHaveLength(48)
        ->and(session('messenger_oauth.agent_id'))->toBe($this->agent->id);
    $res->assertRedirect('https://www.facebook.com/dialog/oauth?state='.$state);
});

it('callback with a matching state holds the granted Pages for the picker', function (): void {
    $res = $this->actingAs($this->owner)
        ->withSession(['messenger_oauth' => ['agent_id' => $this->agent->id, 'state' => 'good-state']])
        ->get(route('channels.messenger.callback', ['code' => 'CODE', 'state' => 'good-state']));

    // `open=messenger` re-opens the slide-over so the Page picker is visible
    // instead of behind a closed sheet after the full-page OAuth redirect.
    $res->assertRedirect(route('agents.channels', ['agent' => $this->agent, 'open' => 'messenger']));
    expect(session('messenger_oauth_pages.agent_id'))->toBe($this->agent->id)
        ->and(session('messenger_oauth_pages.pages'))->toHaveCount(1);
    // Not connected until the workspace actually picks a Page.
    expect(AgentMessengerPage::query()->count())->toBe(0);
});

it('callback whose code exchange fails stores no Pages and says why', function (): void {
    $this->oauth->userToken = null;

    $res = $this->actingAs($this->owner)
        ->withSession(['messenger_oauth' => ['agent_id' => $this->agent->id, 'state' => 'good-state']])
        ->get(route('channels.messenger.callback', ['code' => 'CODE', 'state' => 'good-state']));

    $res->assertRedirect(route('agents.channels', ['agent' => $this->agent, 'open' => 'messenger']))
        ->assertSessionHasErrors(['messenger' => 'No Facebook Pages came back. Re-run the connection and grant access to a Page with every permission checked.']);
    expect(session('messenger_oauth_pages'))->toBeNull();
});

it('callback with a mismatched state bails without storing Pages', function (): void {
    $res = $this->actingAs($this->owner)
        ->withSession(['messenger_oauth' => ['agent_id' => $this->agent->id, 'state' => 'right']])
        ->get(route('channels.messenger.callback', ['code' => 'CODE', 'state' => 'wrong']));

    $res->assertRedirect(route('agents.index'));
    expect(session('messenger_oauth_pages'))->toBeNull();
});

it('select connects the chosen Page', function (): void {
    $this->app->instance(MessengerClient::class, new FakeMessengerClient);

    $res = $this->actingAs($this->owner)
        ->withSession(['messenger_oauth_pages' => [
            'agent_id' => $this->agent->id,
            'pages' => [['id' => 'PAGE-1', 'name' => 'Acme Support', 'access_token' => 'EAAG-page-token']],
        ]])
        ->post(route('agents.channels.messenger.select', $this->agent), ['page_id' => 'PAGE-1']);

    $res->assertRedirect();

    $page = AgentMessengerPage::query()->where('agent_id', $this->agent->id)->first();
    expect($page)->not->toBeNull()
        ->and($page->page_id)->toBe('PAGE-1')
        ->and($page->page_name)->toBe('Acme Support')
        ->and($page->page_access_token)->toBe('EAAG-page-token');
});
