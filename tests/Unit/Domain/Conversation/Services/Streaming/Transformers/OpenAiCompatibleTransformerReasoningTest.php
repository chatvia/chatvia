<?php

declare(strict_types=1);

use Domain\Conversation\Services\Streaming\Sse\EventType;
use Domain\Conversation\Services\Streaming\Sse\StreamEvent;
use Tests\Support\Conversation\OpenAiStream;

/**
 * The model's thinking in an OpenAI-compatible stream: from its own field
 * (`reasoning_content`, `reasoning`, OpenRouter's `reasoning_details`) or a
 * leading inline think block, it streams as `reasoning` events, never as part
 * of the answer, and only on a turn that shows it.
 */
it('sends the thinking in its own field as reasoning, never as the answer', function (string $field): void {
    [$events, $context] = OpenAiStream::transform([
        OpenAiStream::chunk(['choices' => [['delta' => [$field => 'Let me ']]]]),
        OpenAiStream::chunk(['choices' => [['delta' => [$field => 'think.']]]]),
        OpenAiStream::chunk(['choices' => [['delta' => ['content' => 'The answer.']]]]),
        "data: [DONE]\n",
    ], showsReasoning: true);

    expect(implode('', OpenAiStream::texts($events, EventType::Reasoning)))->toBe('Let me think.')
        ->and(OpenAiStream::texts($events))->toBe(['The answer.'])
        ->and($context->streamedMessage)->toBe('The answer.');
})->with(['reasoning_content', 'reasoning']);

it('reads OpenRouter reasoning_details when the delta has no reasoning text', function (): void {
    [$events] = OpenAiStream::transform([
        OpenAiStream::chunk(['choices' => [['delta' => ['reasoning_details' => [['type' => 'reasoning.summary', 'summary' => 'A summary.']]]]]]),
        "data: [DONE]\n",
    ], showsReasoning: true);

    expect(implode('', OpenAiStream::texts($events, EventType::Reasoning)))->toBe('A summary.');
});

it('takes a leading inline think block out of the answer, even split over chunks', function (): void {
    [$events, $context] = OpenAiStream::transform([
        OpenAiStream::chunk(['choices' => [['delta' => ['content' => "\n<thi"]]]]),
        OpenAiStream::chunk(['choices' => [['delta' => ['content' => 'nk>Weighing it']]]]),
        OpenAiStream::chunk(['choices' => [['delta' => ['content' => ' up.</th']]]]),
        OpenAiStream::chunk(['choices' => [['delta' => ['content' => "ink>\n\nIt is 42."]]]]),
        "data: [DONE]\n",
    ], showsReasoning: true);

    expect(implode('', OpenAiStream::texts($events, EventType::Reasoning)))->toBe('Weighing it up.')
        ->and(implode('', OpenAiStream::texts($events)))->toBe('It is 42.')
        ->and($context->streamedMessage)->toBe('It is 42.');
});

it('keeps an answer that only starts like a think tag', function (): void {
    [$events, $context] = OpenAiStream::transform([
        OpenAiStream::chunk(['choices' => [['delta' => ['content' => '<th']]]]),
        OpenAiStream::chunk(['choices' => [['delta' => ['content' => 'ree> is not a tag']]]]),
        "data: [DONE]\n",
    ], showsReasoning: true);

    expect(implode('', OpenAiStream::texts($events, EventType::Reasoning)))->toBe('')
        ->and($context->streamedMessage)->toBe('<three> is not a tag');
});

it('drops the thinking when the turn does not show it', function (): void {
    [$events, $context] = OpenAiStream::transform([
        OpenAiStream::chunk(['choices' => [['delta' => ['reasoning_content' => 'Hidden.']]]]),
        OpenAiStream::chunk(['choices' => [['delta' => ['content' => '<think>Also hidden.</think>Shown.']]]]),
        "data: [DONE]\n",
    ], showsReasoning: false);

    expect(array_filter($events, static fn (StreamEvent $event): bool => $event->type === EventType::Reasoning))->toBe([])
        ->and($context->streamedMessage)->toBe('Shown.');
});
