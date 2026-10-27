<?php

declare(strict_types=1);

namespace Domain\Conversation\Contracts;

use Domain\Conversation\Services\ChatContext;
use Domain\Conversation\Services\Streaming\Transformers\OpenAiCompatibleTransformer;
use Generator;
use Psr\Http\Message\ResponseInterface;

/**
 * Parses an upstream OpenAI-compatible SSE byte stream into envelope events.
 *
 * Kept as an interface — not for multiple production implementations (there is
 * one, {@see OpenAiCompatibleTransformer}) but as the seam the agentic tool loop
 * streams through: ToolLoopRunnerTest injects fake transformers to drive canned
 * tool-call streams without a live upstream connection.
 */
interface StreamTransformer
{
    public function transform(ResponseInterface $upstream, ChatContext $context): Generator;
}
