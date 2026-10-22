<?php

namespace Domain\Llm\Data\Chat;

use Spatie\LaravelData\Data;

/**
 * The model's reply to one non-streaming chat completion: its text, any tool
 * calls, and the token usage the provider reported. Built once from the
 * provider's JSON so no caller has to dig through `choices.0.message`.
 */
final class ChatResponseData extends Data
{
    /**
     * @param  list<ChatToolCallData>  $toolCalls
     */
    public function __construct(
        public string $content,
        public array $toolCalls = [],
        public ?ChatUsageData $usage = null,
        public ?string $finishReason = null,
    ) {}

    /**
     * @param  array<string, mixed>  $body  the decoded `/chat/completions` response
     */
    public static function fromResponse(array $body): self
    {
        $choice = $body['choices'][0] ?? [];
        $message = is_array($choice['message'] ?? null) ? $choice['message'] : [];

        return new self(
            content: self::text($message['content'] ?? null),
            toolCalls: self::toolCalls($message['tool_calls'] ?? null),
            usage: ChatUsageData::fromUsage(is_array($body['usage'] ?? null) ? $body['usage'] : null),
            finishReason: filled($choice['finish_reason'] ?? null) ? $choice['finish_reason'] : null,
        );
    }

    /**
     * Message content is usually a string; some providers send a list of
     * content parts instead, whose text parts are joined.
     */
    private static function text(mixed $content): string
    {
        if (! is_array($content)) {
            return is_scalar($content) ? (string) $content : '';
        }

        $texts = array_map(
            static fn (mixed $part): string => is_array($part) && filled($part['text'] ?? null) ? $part['text'] : '',
            $content,
        );

        return implode('', $texts);
    }

    /**
     * @return list<ChatToolCallData>
     */
    private static function toolCalls(mixed $toolCalls): array
    {
        if (! is_array($toolCalls)) {
            return [];
        }

        $calls = [];

        foreach ($toolCalls as $call) {
            $name = $call['function']['name'] ?? null;

            if (blank($name)) {
                continue;
            }

            $arguments = $call['function']['arguments'] ?? '';

            $calls[] = new ChatToolCallData(
                id: (string) ($call['id'] ?? ''),
                name: $name,
                arguments: is_scalar($arguments) ? (string) $arguments : (string) json_encode($arguments),
            );
        }

        return $calls;
    }
}
