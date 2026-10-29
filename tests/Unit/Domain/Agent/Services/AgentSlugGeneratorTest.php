<?php

use Domain\Agent\Models\Agent;
use Domain\Agent\Services\AgentSlugGenerator;
use Domain\Llm\Contracts\ChatCompletionProvider;
use Domain\Llm\Services\LlmChat;
use Domain\Llm\Services\TaskModels;
use GuzzleHttp\Psr7\Response as Psr7Response;
use Illuminate\Http\Client\Response;

/**
 * The "generate" button for an agent's chat address: the model's suggestion is
 * cleaned to a URL-safe slug, and one taken as another agent's slug or
 * external id is retried.
 */
it('retries a suggestion taken as a slug or an external id and returns the cleaned free one', function (): void {
    Agent::factory()->create(['slug' => 'quickhelper']);
    Agent::factory()->create(['external_id' => 'answer-owl']);
    $provider = Mockery::mock(ChatCompletionProvider::class);
    $provider->shouldReceive('completion')->andReturnValues(collect(['QuickHelper', 'Answer Owl', '"HelpDesk Hero"'])
        ->map(fn (string $reply): Response => new Response(new Psr7Response(200, [], json_encode(['choices' => [['message' => ['content' => $reply]]]], JSON_THROW_ON_ERROR))))
        ->all());

    $slug = new AgentSlugGenerator(app(LlmChat::class)->using($provider), app(TaskModels::class))->generate();

    expect($slug)->toBe('helpdesk-hero');
});
