<?php

declare(strict_types=1);

namespace Tests\Support\Conversation\Tooling;

use Closure;
use Domain\Conversation\Contracts\StreamTransformer;
use Domain\Conversation\Services\ChatContext;
use Generator;
use Psr\Http\Message\ResponseInterface;

/**
 * Stands in for the transformer of a live model stream: every model round
 * runs the script, which may queue tool calls on the context and yield stream
 * events, as the OpenAI-compatible transformer does off the wire.
 */
final readonly class ScriptedStreamTransformer implements StreamTransformer
{
    /** @param  Closure(ChatContext): Generator  $script */
    public function __construct(private Closure $script) {}

    /** A model round that streams nothing and calls no tool. */
    public static function silent(): self
    {
        return new self(static function (): Generator {
            yield from [];
        });
    }

    public function transform(ResponseInterface $upstream, ChatContext $context): Generator
    {
        return yield from ($this->script)($context);
    }
}
