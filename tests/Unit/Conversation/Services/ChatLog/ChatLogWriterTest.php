<?php

use Domain\Agent\Models\Agent;
use Domain\Analytics\Events\MessageSent;
use Domain\Conversation\Actions\Activity\TouchConversationActivityAction;
use Domain\Conversation\Actions\Messages\StoreUserMessageAction;
use Domain\Conversation\Data\Messages\UserTurnData;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Models\Session;
use Domain\Conversation\Services\ChatLog\ChatLogWriter;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Support\Localization\Country\Models\Country;

/**
 * The conversation stamp a stored message leaves: it moves the conversation
 * in the chat log (updated_at) at most once a minute, even right after a
 * widget heartbeat refreshed last_activity_at, and finds the conversation
 * with one query. Analytics gets the session's country without a lookup per
 * message.
 */
function clwConversation(array $attributes = []): Conversation
{
    $agent = Agent::factory()->create();

    return Conversation::factory()->create([
        'chat_session_id' => Session::factory()->create(['agent_id' => $agent->id])->id,
        'is_visible' => true,
        ...$attributes,
    ]);
}

/** Puts the conversation's stamps at fixed times, without touching anything else. */
function clwStamp(Conversation $conversation, DateTimeInterface $lastActivityAt, DateTimeInterface $updatedAt): void
{
    DB::table('chat_conversations')->where('id', $conversation->id)
        ->update(['last_activity_at' => $lastActivityAt, 'updated_at' => $updatedAt]);
}

it('moves the conversation in the chat log even right after a heartbeat', function (): void {
    $conversation = clwConversation();
    clwStamp($conversation, now()->subHour(), now()->subHour());
    // The heartbeat refreshes last_activity_at only.
    app(TouchConversationActivityAction::class)->execute($conversation);

    app(ChatLogWriter::class)->conversation($conversation->session, $conversation->external_id, 'widget');

    expect($conversation->fresh()->updated_at->greaterThan(now()->subMinute()))->toBeTrue();
});

it('moves the activity time when only it is old', function (): void {
    $conversation = clwConversation();
    clwStamp($conversation, now()->subHour(), now()->subSeconds(5));

    app(ChatLogWriter::class)->conversation($conversation->session, $conversation->external_id, 'widget');

    expect($conversation->fresh()->last_activity_at->greaterThan(now()->subMinute()))->toBeTrue();
});

it('leaves both stamps alone within a minute and reads the conversation once', function (): void {
    $conversation = clwConversation();
    $recent = now()->subSeconds(10)->startOfSecond();
    clwStamp($conversation, $recent, $recent);
    $session = $conversation->session;
    $queries = [];
    DB::listen(function ($query) use (&$queries): void {
        $queries[] = $query->sql;
    });

    app(ChatLogWriter::class)->conversation($session, $conversation->external_id, 'widget');
    $sent = $queries;

    $fresh = $conversation->fresh();
    expect($sent)->toHaveCount(1)
        ->and($sent[0])->toStartWith('select')
        ->and($fresh->updated_at->equalTo($recent))->toBeTrue()
        ->and($fresh->last_activity_at->equalTo($recent))->toBeTrue();
});

it('creates the conversation when it does not exist yet', function (): void {
    $agent = Agent::factory()->create();
    $session = Session::factory()->create(['agent_id' => $agent->id]);
    $id = (string) Str::uuid();

    $conversation = app(ChatLogWriter::class)->conversation($session, $id, 'widget');

    expect($conversation->fresh())
        ->external_id->toBe($id)
        ->is_visible->toBeTrue()
        ->last_activity_at->not->toBeNull();
});

it('announces the session country to analytics', function (): void {
    Event::fake([MessageSent::class]);
    Bus::fake();
    $country = Country::query()->firstOrFail();
    $agent = Agent::factory()->create();
    $sessionId = (string) Str::uuid();
    Session::factory()->create(['agent_id' => $agent->id, 'external_id' => $sessionId, 'country_id' => $country->id]);

    app(StoreUserMessageAction::class)->execute($agent, UserTurnData::from([
        'external_session_id' => $sessionId,
        'external_conversation_id' => (string) Str::uuid(),
        'external_message_id' => (string) Str::uuid(),
        'llm_id' => null,
        'message' => 'Hello',
        'sent_at' => now(),
        'source' => 'widget',
    ]));

    Event::assertDispatched(MessageSent::class, fn (MessageSent $event): bool => $event->countryCode === $country->code);
});
