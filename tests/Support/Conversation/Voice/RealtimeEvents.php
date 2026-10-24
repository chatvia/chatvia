<?php

declare(strict_types=1);

namespace Tests\Support\Conversation\Voice;

use Domain\Conversation\Services\Voice\VoiceCallSession;

/**
 * The OpenAI realtime events a voice worker receives during a call, built by
 * hand so a test can feed {@see VoiceCallSession} one turn at a time.
 */
final class RealtimeEvents
{
    /** The visitor stopped talking. */
    public static function committed(string $itemId): array
    {
        return ['type' => 'input_audio_buffer.committed', 'item_id' => $itemId];
    }

    /** What the visitor said, written down. */
    public static function transcribed(string $itemId, string $words): array
    {
        return ['type' => 'conversation.item.input_audio_transcription.completed', 'item_id' => $itemId, 'transcript' => $words];
    }

    /** A reply started for the visitor's turn. */
    public static function created(string $responseId, string $turn = 'item_1'): array
    {
        return ['type' => 'response.created', 'response' => ['id' => $responseId, 'metadata' => ['turn' => $turn]]];
    }

    /** "Let me check" started. */
    public static function fillerCreated(): array
    {
        return ['type' => 'response.created', 'response' => ['id' => 'resp_filler', 'metadata' => ['notice' => '1', 'filler' => '1']]];
    }

    /**
     * A finished spoken reply.
     *
     * @param  array<string, string>  $metadata
     * @param  array<string, int>  $usage
     */
    public static function spoken(string $responseId, string $text, array $metadata = [], array $usage = ['input_tokens' => 120, 'output_tokens' => 30]): array
    {
        return [
            'type' => 'response.done',
            'response' => [
                'id' => $responseId,
                'status' => 'completed',
                'metadata' => $metadata,
                'usage' => $usage,
                'output' => [['type' => 'message', 'role' => 'assistant', 'content' => [['type' => 'output_audio', 'transcript' => $text]]]],
            ],
        ];
    }

    /** A finished reply that only called a tool. */
    public static function toolCall(string $responseId, string $name, string $arguments, string $callId = 'fc_1', string $turn = 'item_1'): array
    {
        return ['type' => 'response.done', 'response' => [
            'id' => $responseId,
            'status' => 'completed',
            'metadata' => ['turn' => $turn],
            'output' => [['type' => 'function_call', 'call_id' => $callId, 'name' => $name, 'arguments' => $arguments]],
        ]];
    }

    /** The finished "let me check" (cancelled when the visitor talked over it). */
    public static function fillerDone(string $status = 'completed'): array
    {
        return ['type' => 'response.done', 'response' => ['id' => 'resp_filler', 'status' => $status, 'metadata' => ['notice' => '1', 'filler' => '1'], 'output' => []]];
    }

    /** The visitor stops talking, and what they said is written down. */
    public static function visitorSays(VoiceCallSession $session, string $itemId, string $words): void
    {
        $session->received(self::committed($itemId));
        $session->received(self::transcribed($itemId, $words));
    }
}
