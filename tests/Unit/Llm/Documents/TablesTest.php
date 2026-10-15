<?php

use Domain\Llm\Contracts\LlmProviderInterface;
use Domain\Llm\Operations\Documents\Tables;
use Domain\Llm\Services\LlmChat;
use Domain\Llm\Services\TaskModels;
use GuzzleHttp\Psr7\Response as Psr7Response;
use Illuminate\Http\Client\Response;

/**
 * Table descriptions: the call leaves a reasoning model room to think and
 * still write prose for every table on the page — with a tight token cap the
 * description is cut short or comes back empty.
 */
it('gives the model room to reason before it describes the tables', function (): void {
    testLlm();
    $sent = null;

    $provider = Mockery::mock(LlmProviderInterface::class);
    $provider->shouldReceive('completion')->once()->andReturnUsing(function (array $data) use (&$sent): Response {
        $sent = $data;

        return new Response(new Psr7Response(200, [], json_encode(['choices' => [['message' => ['content' => 'Table 1: The Basic plan costs 9 EUR per month.']]]])));
    });

    $description = new Tables(app(LlmChat::class)->using($provider), app(TaskModels::class))
        ->describe(["| Plan | Price |\n|---|---|\n| Basic | 9 EUR |"]);

    expect($description)->toBe('Table 1: The Basic plan costs 9 EUR per month.')
        ->and($sent['max_tokens'] ?? $sent['max_completion_tokens'] ?? 0)->toBeGreaterThanOrEqual(1500);
});
