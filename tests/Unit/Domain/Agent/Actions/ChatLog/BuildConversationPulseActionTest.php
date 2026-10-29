<?php

use Domain\Agent\Actions\ChatLog\BuildConversationPulseAction;
use Domain\Agent\Data\Resources\ChatLog\Pulse\ChatLogPulseMessageData;
use Domain\Analytics\Enums\Sentiment;
use Domain\Analytics\Enums\Tone;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Models\Message;
use Domain\Conversation\Models\Session;
use Illuminate\Support\Str;

/**
 * The pulse charts the visitor's mood across a conversation: one point per
 * visitor message (tone wins over sentiment when both are tagged), plus a
 * summary of where the mood started, ended, peaked and which way it moved.
 * Assistant replies are counted but never charted.
 */
function bcpConversation(): Conversation
{
    $session = Session::factory()->create();

    return Conversation::factory()->create([
        'chat_session_id' => $session->id,
        'sentiment' => 'positive',
        'tone' => 'satisfied',
    ]);
}

/**
 * @param  list<array{0: string, 1: string|null, 2: string|null}>  $turns  role, tone, sentiment
 */
function bcpTurns(Conversation $conversation, array $turns): void
{
    foreach ($turns as $minute => [$role, $tone, $sentiment]) {
        Message::factory()->create([
            'chat_conversation_id' => $conversation->id,
            'role' => $role,
            'content' => Str::repeat('word ', 20),
            'tone' => $tone,
            'sentiment' => $sentiment,
            'created_at' => now()->subHour()->addMinutes($minute),
        ]);
    }
}

it('charts only visitor messages and summarises an improving, resolved conversation', function (): void {
    $conversation = bcpConversation();
    bcpTurns($conversation, [
        ['user', 'frustrated', 'negative'],
        ['assistant', null, null],
        ['user', null, 'positive'],
        ['assistant', null, null],
        ['user', null, 'neutral'],
    ]);

    $pulse = app(BuildConversationPulseAction::class)->execute($conversation->session->agent, $conversation);

    $points = $pulse->messages->toCollection();
    expect($points)->toHaveCount(3)
        ->and($points->map(fn (ChatLogPulseMessageData $p): array => [$p->index, $p->sentiment_score])->all())
        ->toBe([[1, -1.0], [2, 1.0], [3, 0.0]])
        ->and(Str::length($points->first()->content_preview))->toBeLessThanOrEqual(53);

    $summary = $pulse->summary;
    expect($summary->trend)->toBe('improving')
        ->and($summary->resolution)->toBeTrue()
        ->and($summary->avg_sentiment)->toBe(0.0)
        ->and($summary->started_as?->tone)->toBe(Tone::Frustrated)
        ->and($summary->ended_as?->sentiment)->toBe(Sentiment::Neutral)
        ->and($summary->peak_negative?->index)->toBe(1)
        ->and($summary->peak_positive?->index)->toBe(2);

    expect($pulse->conversation->id)->toBe($conversation->external_id)
        ->and($pulse->conversation->total_messages)->toBe(5)
        ->and($pulse->conversation->user_messages)->toBe(3)
        ->and($pulse->conversation->tone)->toBe(Tone::Satisfied);
});

it('reports a declining conversation that never recovered', function (): void {
    $conversation = bcpConversation();
    bcpTurns($conversation, [
        ['user', 'satisfied', 'positive'],
        ['user', 'urgent', 'negative'],
    ]);

    $summary = app(BuildConversationPulseAction::class)->execute($conversation->session->agent, $conversation)->summary;

    expect($summary->trend)->toBe('declining')
        ->and($summary->resolution)->toBeFalse()
        ->and($summary->avg_sentiment)->toBe(0.25)
        ->and($summary->peak_negative?->tone)->toBe(Tone::Urgent)
        ->and($summary->peak_positive?->tone)->toBe(Tone::Satisfied);
});
