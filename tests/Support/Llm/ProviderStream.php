<?php

declare(strict_types=1);

namespace Tests\Support\Llm;

use Domain\Conversation\Services\ChatContext;
use Domain\Conversation\Services\Streaming\Sse\EventType;
use Domain\Conversation\Services\Streaming\Sse\StreamEvent;
use Domain\Conversation\Services\Streaming\Transformers\OpenAiCompatibleTransformer;
use GuzzleHttp\Psr7\FnStream;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Utils;
use Psr\Http\Message\StreamInterface;
use Tests\Support\Conversation\TestChatContext;

/**
 * A provider's own stream format (Anthropic Messages, OpenAI Responses), as
 * the provider re-writes it into a Chat Completions stream and the chat
 * engine's transformer reads it: SSE events written by hand, the stream read
 * through the transformer, and an upstream that hands over one line per read.
 */
final class ProviderStream
{
    /**
     * One server-sent event, named after its `type`.
     *
     * @param  array<string, mixed>  $event
     */
    public static function event(array $event): string
    {
        return 'event: '.$event['type']."\n".'data: '.json_encode($event)."\n\n";
    }

    /**
     * The re-written stream read through the chat engine's transformer, with a
     * context holding only what the transformer reads ({@see TestChatContext::bare()}).
     *
     * @param  list<string>  $offeredToolNames
     * @return array{0: list<StreamEvent>, 1: ChatContext}
     */
    public static function transform(StreamInterface $stream, bool $showsReasoning = true, array $offeredToolNames = []): array
    {
        $context = TestChatContext::bare(showsReasoning: $showsReasoning, offeredToolNames: $offeredToolNames);

        return [iterator_to_array(new OpenAiCompatibleTransformer()->transform(new Response(200, [], $stream), $context), false), $context];
    }

    /**
     * The text of every event of the given type.
     *
     * @param  list<StreamEvent>  $events
     * @return list<string>
     */
    public static function texts(array $events, EventType $type): array
    {
        return array_values(array_map(
            static fn (StreamEvent $event): string => $event->data['text'],
            array_filter($events, static fn (StreamEvent $event): bool => $event->type === $type),
        ));
    }

    /** An upstream that hands over one line of $body per read, as a slow network does. */
    public static function dripFed(string $body): StreamInterface
    {
        $lines = explode("\n", $body);

        return FnStream::decorate(Utils::streamFor(''), [
            'read' => function () use (&$lines): string {
                return array_shift($lines)."\n";
            },
            'eof' => function () use (&$lines): bool {
                return $lines === [];
            },
        ]);
    }
}
