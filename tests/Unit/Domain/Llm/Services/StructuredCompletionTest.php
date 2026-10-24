<?php

declare(strict_types=1);

use Domain\Llm\Data\Chat\ChatMessageData;
use Domain\Llm\Data\Chat\ChatRequestData;
use Domain\Llm\Enums\AiCallTag;
use Domain\Llm\Exceptions\InvalidStructuredReplyException;
use Domain\Llm\Services\StructuredCompletion;
use Illuminate\Support\Facades\Http;

/**
 * A structured reply is read leniently (a fenced JSON block counts); a reply
 * with no JSON comes back without data, and fails only when the caller says
 * it needs the data.
 */
it('decodes a structured reply and fails loudly only when asked to', function (): void {
    Http::fake(['*' => Http::sequence()
        ->push(['choices' => [['message' => ['content' => "```json\n{\"ok\":true}\n```"]]]])
        ->push(['choices' => [['message' => ['content' => 'no json here']]]])]);

    $completion = app(StructuredCompletion::class);
    $request = new ChatRequestData(testLlm(), [ChatMessageData::user('Hi')], AiCallTag::ConversationTitle);

    expect($completion->send($request)->data)->toBe(['ok' => true]);

    $reply = $completion->send($request);

    expect($reply->data)->toBeNull()
        ->and(fn (): array => $reply->requireData())->toThrow(InvalidStructuredReplyException::class);
});
