<?php

use Domain\Llm\Contracts\LlmProviderInterface;
use Domain\Llm\Operations\Query\TitleGenerator;
use Domain\Llm\Services\LlmChat;
use Domain\Llm\Services\TaskModels;
use GuzzleHttp\Psr7\Response as Psr7Response;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Str;

/**
 * Conversation titles: the model's reply is cleaned to one short line, and the
 * call leaves a reasoning model room to think — with a tight token cap it
 * stops before writing anything and the title is empty.
 */
it('cleans the reply to a short title', function (): void {
    expect(TitleGenerator::clean("**Title:** \"Sunday Opening Hours.\"\n\nThe visitor asked when…"))->toBe('Sunday Opening Hours')
        ->and(TitleGenerator::clean("\n\n# Polite Decline\n"))->toBe('Polite Decline')
        ->and(TitleGenerator::clean('Title: Order tracking'))->toBe('Order tracking')
        ->and(Str::length(TitleGenerator::clean(Str::repeat('word ', 40))))->toBeLessThanOrEqual(80)
        ->and(TitleGenerator::clean(''))->toBe('')
        ->and(TitleGenerator::clean("  \n "))->toBe('');
});

it('gives the model room to reason before it writes the title', function (): void {
    testLlm();
    $sent = null;

    $provider = Mockery::mock(LlmProviderInterface::class);
    $provider->shouldReceive('completion')->once()->andReturnUsing(function (array $data) use (&$sent): Response {
        $sent = $data;

        return new Response(new Psr7Response(200, [], json_encode(['choices' => [['message' => ['content' => "Sunday Opening Hours\n\n"]]]])));
    });

    $title = new TitleGenerator(app(LlmChat::class)->using($provider), app(TaskModels::class))
        ->generate("When are you open on Sunday?\nFrom nine to five.");

    expect($title)->toBe('Sunday Opening Hours')
        ->and($sent['max_tokens'] ?? $sent['max_completion_tokens'] ?? 0)->toBeGreaterThanOrEqual(300);
});
