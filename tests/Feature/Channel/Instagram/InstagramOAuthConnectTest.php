<?php

use App\Identity\Workspace\Models\User;
use Domain\Channel\Instagram\Models\AgentInstagramAccount;
use Domain\Channel\Instagram\Services\InstagramClient;
use Domain\Channel\Services\MetaOAuthClient;
use Domain\Membership\Team\Services\TeamService;
use Tests\Support\Instagram\FakeInstagramClient;

/**
 * The Instagram connect flow is OAuth (Facebook Login for Business, Page-linked):
 * redirect → callback → pick an IG account. The pending state + the granted
 * accounts live in the SESSION (so they survive the cross-site round-trip
 * independent of the cache store). These tests pin that stash, the CSRF state
 * check, and the final connect.
 */
class FakeInstagramOAuthClient extends MetaOAuthClient
{
    /** @var list<array{page_id: string, page_name: string, page_access_token: string, ig_id: string, ig_username: string}> */
    public array $accountsList = [[
        'page_id' => '998877',
        'page_name' => 'Acme Support',
        'page_access_token' => 'EAAG-page-token',
        'ig_id' => '17840012345',
        'ig_username' => 'acme.support',
    ]];

    public ?string $userToken = 'USER-LONG-TOKEN';

    public function authorizeUrl(string $state, string $scopes, ?string $configId = null, ?string $redirectUri = null): string
    {
        return 'https://www.facebook.com/dialog/oauth?state='.$state;
    }

    public function exchangeCodeForUserToken(string $code, ?string $redirectUri = null): ?string
    {
        return $this->userToken;
    }

    public function pagesWithInstagram(string $userToken): array
    {
        return $this->accountsList;
    }
}

beforeEach(function (): void {
    $this->owner = User::factory()->create();
    app(TeamService::class)->createDefaultTeam($this->owner);
    $this->owner->billing()->firstOrCreate([]);
    $this->owner->billing->subscriptions()->create([
        'type' => 'default',
        'stripe_id' => 'sub_test_active',
        'stripe_status' => 'active',
        'stripe_price' => 'price_test',
        'quantity' => 1,
    ]);
    $this->owner->refresh();

    $this->agent = createAgent($this->owner);
    $this->agent->update(['is_public' => true]);

    $this->app->instance(MetaOAuthClient::class, new FakeInstagramOAuthClient);
});

it('redirect stashes the pending connection in the session and bounces to Facebook', function (): void {
    $res = $this->actingAs($this->owner)->get(route('agents.channels.instagram.redirect', $this->agent));

    $res->assertRedirectContains('facebook.com/dialog/oauth');
    expect(session('instagram_oauth.agent_id'))->toBe($this->agent->id)
        ->and(session('instagram_oauth.state'))->toBeString();
});

it('callback with a matching state holds the granted accounts for the picker', function (): void {
    $res = $this->actingAs($this->owner)
        ->withSession(['instagram_oauth' => ['agent_id' => $this->agent->id, 'state' => 'good-state']])
        ->get(route('channels.instagram.callback', ['code' => 'CODE', 'state' => 'good-state']));

    $res->assertRedirect(route('agents.channels', $this->agent));
    expect(session('instagram_oauth_pages.agent_id'))->toBe($this->agent->id)
        ->and(session('instagram_oauth_pages.pages'))->toHaveCount(1);
    // Not connected until the workspace actually picks an account.
    expect(AgentInstagramAccount::query()->count())->toBe(0);
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
