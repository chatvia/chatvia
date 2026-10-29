<?php

declare(strict_types=1);

use Domain\Conversation\Services\Streaming\Sse\EventType;
use Tests\Support\Conversation\OpenAiStream;

/**
 * The model's thinking in an OpenAI-compatible stream: from its own field
 * (`reasoning_content`, `reasoning`, OpenRouter's `reasoning_details`) or a
 * leading inline think block, it streams as `reasoning` events, never as part
 * of the answer.
 */
it('sends the thinking in its own field as reasoning, never as the answer', function (array $delta, string $thinking): void {
    [$events, $context] = OpenAiStream::transform([
        OpenAiStream::chunk(['choices' => [['delta' => $delta]]]),
        OpenAiStream::chunk(['choices' => [['delta' => ['content' => 'The answer.']]]]),
        "data: [DONE]\n",
    ], showsReasoning: true);

    expect(implode('', OpenAiStream::texts($events, EventType::Reasoning)))->toBe($thinking)
        ->and(OpenAiStream::texts($events))->toBe(['The answer.'])
        ->and($context->streamedMessage)->toBe('The answer.');
})->with([
    'reasoning_content' => [['reasoning_content' => 'Let me think.'], 'Let me think.'],
    'reasoning' => [['reasoning' => 'Let me think.'], 'Let me think.'],
    'OpenRouter reasoning_details' => [['reasoning_details' => [['type' => 'reasoning.summary', 'summary' => 'A summary.']]], 'A summary.'],
]);

it('takes a leading inline think block out of the answer, even split over chunks, but keeps one that only looks like a tag', function (): void {
    [$events, $context] = OpenAiStream::transform([
        OpenAiStream::chunk(['choices' => [['delta' => ['content' => "\n<thi"]]]]),
        OpenAiStream::chunk(['choices' => [['delta' => ['content' => 'nk>Weighing it']]]]),
        OpenAiStream::chunk(['choices' => [['delta' => ['content' => ' up.</th']]]]),
        OpenAiStream::chunk(['choices' => [['delta' => ['content' => "ink>\n\nIt is 42."]]]]),
        "data: [DONE]\n",
    ], showsReasoning: true);
    [$lookalike, $lookalikeContext] = OpenAiStream::transform([
        OpenAiStream::chunk(['choices' => [['delta' => ['content' => '<th']]]]),
        OpenAiStream::chunk(['choices' => [['delta' => ['content' => 'ree> is not a tag']]]]),
        "data: [DONE]\n",
    ], showsReasoning: true);

    expect(implode('', OpenAiStream::texts($events, EventType::Reasoning)))->toBe('Weighing it up.')
        ->and(implode('', OpenAiStream::texts($events)))->toBe('It is 42.')
        ->and($context->streamedMessage)->toBe('It is 42.')
        ->and(implode('', OpenAiStream::texts($lookalike, EventType::Reasoning)))->toBe('')
        ->and($lookalikeContext->streamedMessage)->toBe('<three> is not a tag');
});
