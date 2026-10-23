<?php

declare(strict_types=1);

namespace Domain\Llm\Providers\Messages;

use stdClass;

/**
 * Reads a whole Anthropic Messages reply as a Chat Completions reply, so the
 * app's single calls (guardrails, titles, image descriptions…) read it the
 * same way as every other provider's: the text as `choices.0.message.content`,
 * tool_use blocks as `tool_calls`, and the token counts as `usage`.
 */
final class MessagesReply
{
    /** Why the reply ended, under the Chat Completions names. */
    private const array FINISH_REASONS = [
        'end_turn' => 'stop',
        'stop_sequence' => 'stop',
        'max_tokens' => 'length',
        'tool_use' => 'tool_calls',
        'refusal' => 'content_filter',
    ];

    /**
     * @param  array<array-key, mixed>  $reply  a Messages API reply
     * @return array<string, mixed>
     */
    public static function toChatCompletion(array $reply): array
    {
        $text = '';
        $toolCalls = [];

        foreach (is_array($reply['content'] ?? null) ? $reply['content'] : [] as $block) {
            match ($block['type'] ?? null) {
                'text' => $text .= (string) ($block['text'] ?? ''),
                'tool_use' => $toolCalls[] = [
                    'id' => (string) ($block['id'] ?? ''),
                    'type' => 'function',
                    'function' => [
                        'name' => (string) ($block['name'] ?? ''),
                        'arguments' => (string) json_encode($block['input'] ?? new stdClass, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    ],
                ],
                default => null,
            };
        }

        $stopReason = (string) ($reply['stop_reason'] ?? '');

        return [
            'id' => (string) ($reply['id'] ?? ''),
            'object' => 'chat.completion',
            'model' => (string) ($reply['model'] ?? ''),
            'choices' => [[
                'index' => 0,
                'message' => array_filter([
                    'role' => 'assistant',
                    'content' => $text,
                    'tool_calls' => $toolCalls,
                ], static fn (mixed $value): bool => $value !== []),
                'finish_reason' => self::FINISH_REASONS[$stopReason] ?? ($stopReason !== '' ? $stopReason : null),
            ]],
            'usage' => self::usage(is_array($reply['usage'] ?? null) ? $reply['usage'] : []),
        ];
    }

    /**
     * Messages token counts under their Chat Completions names. The input
     * the cache served or stored counts toward the prompt, as it is billed.
     *
     * @param  array<array-key, mixed>  $usage
     * @return array<string, mixed>
     */
    public static function usage(array $usage): array
    {
        $cached = (int) ($usage['cache_read_input_tokens'] ?? 0);
        $prompt = (int) ($usage['input_tokens'] ?? 0) + $cached + (int) ($usage['cache_creation_input_tokens'] ?? 0);
        $completion = (int) ($usage['output_tokens'] ?? 0);

        return [
            'prompt_tokens' => $prompt,
            'completion_tokens' => $completion,
            'total_tokens' => $prompt + $completion,
            'prompt_tokens_details' => ['cached_tokens' => $cached],
            'completion_tokens_details' => ['reasoning_tokens' => (int) ($usage['output_tokens_details']['thinking_tokens'] ?? 0)],
        ];
    }
}
