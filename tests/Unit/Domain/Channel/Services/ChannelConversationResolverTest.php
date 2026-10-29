<?php

use Domain\Channel\Instagram\Models\AgentInstagramAccount;
use Domain\Channel\Instagram\Services\InstagramThreadResolver;
use Domain\Channel\Messenger\Models\AgentMessengerPage;
use Domain\Channel\Messenger\Services\MessengerThreadResolver;
use Domain\Channel\Slack\Models\AgentSlackApp;
use Domain\Channel\Slack\Services\SlackChannelResolver;
use Domain\Channel\Teams\Models\AgentTeamsBot;
use Domain\Channel\Teams\Services\TeamsChatResolver;
use Domain\Channel\Telegram\Models\AgentTelegramBot;
use Domain\Channel\Telegram\Services\TelegramChatResolver;
use Domain\Channel\Whatsapp\Models\AgentWhatsappNumber;
use Domain\Channel\Whatsapp\Services\WhatsappChatResolver;
use Domain\Conversation\Actions\Conversations\StartConversationAction;
use Domain\Conversation\Enums\ConversationSource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Tests\Support\Channel\Instagram\FakeInstagramClient;
use Tests\Support\Channel\Messenger\FakeMessengerClient;
use Tests\Support\Channel\Slack\FakeSlackClient;

/**
 * The find-or-create every channel resolver shares: a sender's first message
 * starts a Session of the agent (no chatter) and a Conversation of the
 * channel's source, mapped to the sender's channel id; their later messages
 * reuse it; and a mapping whose conversation was pruned starts over. Each
 * dataset entry makes a connection and returns a call that resolves a
 * sender's channel id on it.
 */
dataset('ccrResolvers', [
    'Telegram' => [ConversationSource::Telegram, function (): Closure {
        $bot = AgentTelegramBot::factory()->create();

        return fn (string $id): Model => app(TelegramChatResolver::class)->resolve($bot, $id);
    }],
    'WhatsApp' => [ConversationSource::Whatsapp, function (): Closure {
        $number = AgentWhatsappNumber::factory()->create();

        return fn (string $id): Model => app(WhatsappChatResolver::class)->resolve($number, $id);
    }],
    'Messenger' => [ConversationSource::Messenger, function (): Closure {
        $page = AgentMessengerPage::factory()->create();

        return fn (string $id): Model => new MessengerThreadResolver(app(StartConversationAction::class), new FakeMessengerClient)->resolve($page, $id);
    }],
    'Instagram' => [ConversationSource::Instagram, function (): Closure {
        $account = AgentInstagramAccount::factory()->create();

        return fn (string $id): Model => new InstagramThreadResolver(app(StartConversationAction::class), new FakeInstagramClient)->resolve($account, $id);
    }],
    'Slack' => [ConversationSource::Slack, function (): Closure {
        $app = AgentSlackApp::factory()->create();

        return fn (string $id): Model => new SlackChannelResolver(app(StartConversationAction::class), new FakeSlackClient)->resolve($app, $id);
    }],
    'Microsoft Teams' => [ConversationSource::MsTeams, function (): Closure {
        $bot = AgentTeamsBot::factory()->create();

        return fn (string $id): Model => new TeamsChatResolver(app(StartConversationAction::class))->resolve($bot, $id, 'https://smba.trafficmanager.net/emea/', 'personal', 'Lina');
    }],
]);

it('starts a session of the agent and a conversation of the channel on first contact, and reuses it when the sender returns', function (ConversationSource $source, Closure $resolver): void {
    $this->travelTo(Carbon::parse('2026-10-01 10:00:00'));
    $resolve = $resolver();

    $mapping = $resolve('100');
    $again = $resolve('100');

    $agent = $mapping->session->agent;
    expect($mapping->workspace_id)->toBe($agent->workspace_id)
        ->and($mapping->session->user_id)->toBeNull()
        ->and($mapping->conversation->session->is($mapping->session))->toBeTrue()
        ->and($mapping->conversation->source)->toBe($source)
        ->and($mapping->fresh()->last_message_at->toDateTimeString())->toBe('2026-10-01 10:00:00')
        ->and($again->id)->toBe($mapping->id)
        ->and($mapping::query()->count())->toBe(1);
})->with('ccrResolvers');

it('starts over when the sender’s conversation was pruned', function (ConversationSource $source, Closure $resolver): void {
    $resolve = $resolver();
    $pruned = $resolve('100');
    $pruned->conversation->delete();

    $fresh = $resolve('100');

    expect($fresh->id)->not->toBe($pruned->id)
        ->and($fresh->chat_conversation_id)->not->toBe($pruned->chat_conversation_id)
        ->and($fresh::query()->count())->toBe(1);
})->with('ccrResolvers');
