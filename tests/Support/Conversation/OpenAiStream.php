<?php

declare(strict_types=1);

namespace Tests\Support\Conversation;

use Domain\Conversation\Services\ChatContext;
use Domain\Conversation\Services\Streaming\Sse\EventType;
use Domain\Conversation\Services\Streaming\Sse\StreamEvent;
use Domain\Conversation\Services\Streaming\Transformers\OpenAiCompatibleTransformer;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Utils;

/**
 * An OpenAI-compatible SSE body, line by line, run through
 * {@see OpenAiCompatibleTransformer} on a turn of its own, and the texts of
 * the events it streamed.
 */
final class OpenAiStream
{
    /** @param  array<string, mixed>  $payload */
    public static function chunk(array $payload): string
    {
        return 'data: '.json_encode($payload)."\n";
    }

    /**
     * Run the transformer over the given SSE lines, on a turn that offered
     * the given tools (none by default) and shows the model's thinking or not.
     *
     * @param  list<string>  $lines
     * @param  list<string>  $offeredToolNames
     * @return array{0: list<StreamEvent>, 1: ChatContext}
     */
    public static function transform(array $lines, array $offeredToolNames = [], bool $showsReasoning = false): array
    {
        $context = TestChatContext::make();
        $context->offeredToolNames = $offeredToolNames;
        $context->showsReasoning = $showsReasoning;
        $response = new Response(200, [], Utils::streamFor(implode('', $lines)));

        return [iterator_to_array(new OpenAiCompatibleTransformer()->transform($response, $context), false), $context];
    }

    /**
     * The texts of the streamed events of one type, in order.
     *
     * @param  list<StreamEvent>  $events
     * @return list<string>
     */
    public static function texts(array $events, EventType $type = EventType::Delta): array
    {
        return array_values(array_map(
            static fn (StreamEvent $event): string => $event->data['text'],
            array_filter($events, static fn (StreamEvent $event): bool => $event->type === $type),
        ));
    }
}
