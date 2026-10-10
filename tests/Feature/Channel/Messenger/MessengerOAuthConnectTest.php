<?php

use App\Identity\Workspace\Models\User;
use Domain\Channel\Data\MetaPageData;
use Domain\Channel\Enums\MetaApp;
use Domain\Channel\Messenger\Models\AgentMessengerPage;
use Domain\Channel\Messenger\Services\MessengerClient;
use Domain\Channel\Services\MetaOAuthClient;
use Domain\Membership\Team\Services\TeamService;
use Tests\Support\Messenger\FakeMessengerClient;

/**
 * The Messenger connect flow is OAuth (Facebook Login for Business): redirect →
 * callback → pick a Page. The pending state + the granted Pages live in the
 * SESSION so they survive the cross-site round-trip to Facebook independent of
 * the cache store (a cache-store mismatch across php-fpm workers was the
 * original "redirected home, nothing connected" bug). These tests pin that
 * stash, the CSRF state check, and the final connect.
 */
class FakeMetaOAuthClient extends MetaOAuthClient
{
    /** @var list<array{id: string, name: string, access_token: string}> */
    public array $pagesList = [['id' => 'PAGE-1', 'name' => 'Acme Support', 'access_token' => 'EAAG-page-token']];

    public ?string $userToken = 'USER-LONG-TOKEN';

    public function authorizeUrl(MetaApp $app, string $state, string $scopes): string
    {
        return 'https://www.facebook.com/dialog/oauth?state='.$state;
    }

    public function exchangeCodeForUserToken(MetaApp $app, string $code): ?string
    {
        return $this->userToken;
    }

    public function pages(string $userToken): array
    {
        return array_map(MetaPageData::from(...), $this->pagesList);
    }
}

beforeEach(function (): void {
    $this->owner = User::factory()->create();
    app(TeamService::class)->createDefaultTeam($this->owner);
    $this->owner->refresh();

    $this->agent = createAgent($this->owner);
    $this->agent->update(['is_public' => true]);

    $this->app->instance(MetaOAuthClient::class, new FakeMetaOAuthClient);
});

it('redirect stashes the pending connection in the session and bounces to Facebook', function (): void {
    $res = $this->actingAs($this->owner)->get(route('agents.channels.messenger.redirect', $this->agent));

    $res->assertRedirectContains('facebook.com/dialog/oauth');
    expect(session('messenger_oauth.agent_id'))->toBe($this->agent->id)
        ->and(session('messenger_oauth.state'))->toBeString();
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
