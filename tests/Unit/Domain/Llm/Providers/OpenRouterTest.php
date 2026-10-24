<?php

declare(strict_types=1);

use Domain\Llm\Models\LlmProvider;
use Domain\Llm\Providers\OpenRouter;
use GuzzleHttp\Psr7\Response;
use Tests\Support\Llm\GuzzleRecorder;

/**
 * OpenRouter streams the model's thinking back only for a turn that shows it;
 * otherwise it asks the model to leave the thinking out of the reply.
 */
it('lets the thinking through only when the turn shows it', function (bool $show, array $reasoning): void {
    $recorder = GuzzleRecorder::answering(new Response(200, [], "data: [DONE]\n\n"));

    new OpenRouter(LlmProvider::factory()->make(), 'https://llm.test/v1')
        ->stream('deepseek/deepseek-r1', [['role' => 'user', 'content' => 'hi']], ['reasoning_effort' => 'high'] + ($show ? ['show_reasoning' => true] : []));

    expect($recorder->body()['reasoning'])->toBe($reasoning)
        ->and($recorder->body())->not->toHaveKey('show_reasoning');
})->with([
    'shown' => [true, ['effort' => 'high']],
    'not shown' => [false, ['effort' => 'high', 'exclude' => true]],
]);
