<?php

use App\Identity\Workspace\Models\User;
use Domain\Channel\Enums\MetaOAuthChannel;
use Domain\Channel\Instagram\Models\AgentInstagramAccount;
use Domain\Channel\Instagram\Services\InstagramClient;
use Domain\Channel\Messenger\Models\AgentMessengerPage;
use Domain\Channel\Messenger\Services\MessengerClient;
use Domain\Channel\Services\MetaOAuthClient;
use Tests\Support\Channel\FakeMetaOAuthClient;
use Tests\Support\Channel\Instagram\FakeInstagramClient;
use Tests\Support\Channel\Messenger\FakeMessengerClient;

/**
 * Messenger and Instagram connect through Facebook Login for Business
 * (InteractsWithMetaOAuth): redirect → callback → pick an account. The pending
 * state and the granted accounts live in the SESSION, so they survive the
 * cross-site round-trip independent of the cache store. These tests pin that
 * stash, the CSRF state check, and the final connect, for both channels.
 */
beforeEach(function (): void {
    $this->owner = User::factory()->create();
    $this->agent = createAgent($this->owner, ['is_public' => true]);

    $this->oauth = new FakeMetaOAuthClient;
    $this->app->instance(MetaOAuthClient::class, $this->oauth);
    $this->app->instance(MessengerClient::class, new FakeMessengerClient);
    $this->app->instance(InstagramClient::class, new FakeInstagramClient);
});

dataset('mocChannels', [
    'Messenger' => [
        MetaOAuthChannel::Messenger,
        'No Facebook Pages came back. Re-run the connection and grant access to a Page with every permission checked.',
        ['page_id' => 'PAGE-1'],
        AgentMessengerPage::class,
        ['page_id' => 'PAGE-1', 'page_name' => 'Acme Support', 'page_access_token' => 'EAAG-page-token'],
    ],
    'Instagram' => [
        MetaOAuthChannel::Instagram,
        'No Instagram professional accounts came back. Make sure your Instagram account is Professional and linked to a Facebook Page you manage, then reconnect.',
        ['ig_id' => '17840012345'],
        AgentInstagramAccount::class,
        ['ig_id' => '17840012345', 'username' => 'acme.support', 'page_id' => '998877', 'page_access_token' => 'EAAG-page-token'],
    ],
]);

it('stashes the pending connection in the session and sends the user to Facebook', function (MetaOAuthChannel $channel): void {
    $response = $this->actingAs($this->owner)->get(route("agents.channels.{$channel->value}.redirect", $this->agent));

    // The state sent to Facebook is the one stashed for the callback to check.
    $state = session("{$channel->value}_oauth.state");
    expect($state)->toBeString()->toHaveLength(48)
        ->and(session("{$channel->value}_oauth.agent_id"))->toBe($this->agent->id);
    $response->assertRedirect('https://www.facebook.com/dialog/oauth?state='.$state);
})->with('mocChannels');

it('holds the granted accounts for the picker when the callback state matches', function (MetaOAuthChannel $channel, string $nothingGranted, array $selection, string $connection): void {
    $response = $this->actingAs($this->owner)
        ->withSession(["{$channel->value}_oauth" => ['agent_id' => $this->agent->id, 'state' => 'good-state']])
        ->get(route("channels.{$channel->value}.callback", ['code' => 'CODE', 'state' => 'good-state']));

    // `open=` re-opens the slide-over so the picker is visible after the full-page OAuth redirect.
    $response->assertRedirect(route('agents.channels', ['agent' => $this->agent, 'open' => $channel->value]));
    expect(session("{$channel->value}_oauth_pages.agent_id"))->toBe($this->agent->id)
        ->and(session("{$channel->value}_oauth_pages.pages"))->toHaveCount(1)
        ->and($connection::query()->count())->toBe(0);
})->with('mocChannels');

it('holds nothing and says why when the code exchange fails', function (MetaOAuthChannel $channel, string $nothingGranted): void {
    $this->oauth->userToken = null;

    $this->actingAs($this->owner)
        ->withSession(["{$channel->value}_oauth" => ['agent_id' => $this->agent->id, 'state' => 'good-state']])
        ->get(route("channels.{$channel->value}.callback", ['code' => 'CODE', 'state' => 'good-state']))
        ->assertRedirect(route('agents.channels', ['agent' => $this->agent, 'open' => $channel->value]))
        ->assertSessionHasErrors([$channel->value => $nothingGranted]);

    expect(session("{$channel->value}_oauth_pages"))->toBeNull();
})->with('mocChannels');

it('holds nothing when the callback state does not match', function (MetaOAuthChannel $channel): void {
    $this->actingAs($this->owner)
        ->withSession(["{$channel->value}_oauth" => ['agent_id' => $this->agent->id, 'state' => 'right']])
        ->get(route("channels.{$channel->value}.callback", ['code' => 'CODE', 'state' => 'wrong']))
        ->assertRedirect(route('agents.index'));

    expect(session("{$channel->value}_oauth_pages"))->toBeNull();
})->with('mocChannels');

it('connects the account picked from what the callback granted', function (MetaOAuthChannel $channel, string $nothingGranted, array $selection, string $connection, array $stored): void {
    $this->actingAs($this->owner)
        ->withSession(["{$channel->value}_oauth" => ['agent_id' => $this->agent->id, 'state' => 'good-state']])
        ->get(route("channels.{$channel->value}.callback", ['code' => 'CODE', 'state' => 'good-state']));

    $this->post(route("agents.channels.{$channel->value}.select", $this->agent), $selection)
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($connection::query()->where('agent_id', $this->agent->id)->sole()->only(array_keys($stored)))->toBe($stored)
        ->and(session("{$channel->value}_oauth_pages"))->toBeNull();
})->with('mocChannels');
