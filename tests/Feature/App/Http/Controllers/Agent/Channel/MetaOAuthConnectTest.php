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
 * (InteractsWithMetaOAuth): redirect, callback, pick an account. The callback
 * checks the CSRF state stashed in the session, holds the granted accounts
 * there for the picker (nothing, with an error, when the code exchange
 * fails), and the pick connects the chosen account.
 */
beforeEach(function (): void {
    $this->owner = User::factory()->create();
    $this->agent = createAgent($this->owner, ['is_public' => true]);
    $this->app->instance(MetaOAuthClient::class, new FakeMetaOAuthClient);
    $this->app->instance(MessengerClient::class, new FakeMessengerClient);
    $this->app->instance(InstagramClient::class, new FakeInstagramClient);
});

dataset('mocChannels', [
    'Messenger' => [
        MetaOAuthChannel::Messenger,
        ['page_id' => 'PAGE-1'],
        AgentMessengerPage::class,
        ['page_id' => 'PAGE-1', 'page_name' => 'Acme Support', 'page_access_token' => 'EAAG-page-token'],
    ],
    'Instagram' => [
        MetaOAuthChannel::Instagram,
        ['ig_id' => '17840012345'],
        AgentInstagramAccount::class,
        ['ig_id' => '17840012345', 'username' => 'acme.support', 'page_id' => '998877', 'page_access_token' => 'EAAG-page-token'],
    ],
]);

it('holds nothing when the callback state does not match or the code exchange fails', function (MetaOAuthChannel $channel): void {
    $failedExchange = new FakeMetaOAuthClient;
    $failedExchange->userToken = null;

    $this->actingAs($this->owner)
        ->withSession(["{$channel->value}_oauth" => ['agent_id' => $this->agent->id, 'state' => 'right']])
        ->get(route("channels.{$channel->value}.callback", ['code' => 'CODE', 'state' => 'wrong']))
        ->assertRedirect(route('agents.index'));
    $mismatchHeld = session("{$channel->value}_oauth_pages");
    $this->app->instance(MetaOAuthClient::class, $failedExchange);
    $this->withSession(["{$channel->value}_oauth" => ['agent_id' => $this->agent->id, 'state' => 'good-state']])
        ->get(route("channels.{$channel->value}.callback", ['code' => 'CODE', 'state' => 'good-state']))
        ->assertRedirect(route('agents.channels', ['agent' => $this->agent, 'open' => $channel->value]))
        ->assertSessionHasErrors($channel->value);

    expect($mismatchHeld)->toBeNull()
        ->and(session("{$channel->value}_oauth_pages"))->toBeNull();
})->with('mocChannels');

it('holds the granted accounts on a matching state and connects the one picked', function (MetaOAuthChannel $channel, array $selection, string $connection, array $stored): void {
    $this->actingAs($this->owner)
        ->withSession(["{$channel->value}_oauth" => ['agent_id' => $this->agent->id, 'state' => 'good-state']])
        ->get(route("channels.{$channel->value}.callback", ['code' => 'CODE', 'state' => 'good-state']))
        ->assertRedirect(route('agents.channels', ['agent' => $this->agent, 'open' => $channel->value]));

    expect(session("{$channel->value}_oauth_pages.agent_id"))->toBe($this->agent->id)
        ->and(session("{$channel->value}_oauth_pages.pages"))->toHaveCount(1)
        ->and($connection::query()->count())->toBe(0);

    $this->post(route("agents.channels.{$channel->value}.select", $this->agent), $selection)
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($connection::query()->where('agent_id', $this->agent->id)->sole()->only(array_keys($stored)))->toBe($stored)
        ->and(session("{$channel->value}_oauth_pages"))->toBeNull();
})->with('mocChannels');
