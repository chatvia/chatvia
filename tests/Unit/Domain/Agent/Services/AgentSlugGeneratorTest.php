<?php

use Domain\Agent\Models\Agent;
use Domain\Agent\Services\AgentSlugGenerator;
use Domain\Llm\Contracts\LlmProviderInterface;
use Domain\Llm\Services\LlmChat;
use Domain\Llm\Services\TaskModels;
use GuzzleHttp\Psr7\Response as Psr7Response;
use Illuminate\Http\Client\Response;

/**
 * The "generate" button for an agent's chat address: the model's suggestion is
 * cleaned to a URL-safe slug, a taken one is retried, and the button always
 * gets a free name — a random one when the model can't help.
 *
 * @param  list<string>|Throwable  $replies
 */
function agentSlugGenerator(array|Throwable $replies): AgentSlugGenerator
{
    $provider = Mockery::mock(LlmProviderInterface::class);

    if ($replies instanceof Throwable) {
        $provider->shouldReceive('completion')->andThrow($replies);
    } else {
        $provider->shouldReceive('completion')->andReturnValues(array_map(
            static fn (string $reply): Response => new Response(new Psr7Response(200, [], json_encode(['choices' => [['message' => ['content' => $reply]]]]))),
            $replies,
        ));
    }

    return new AgentSlugGenerator(app(LlmChat::class)->using($provider), app(TaskModels::class));
}

it('lowercases before stripping, so capital letters are kept', function (): void {
    expect(AgentSlugGenerator::clean('"HelpDesk Hero"'))->toBe('helpdesk-hero')
        ->and(AgentSlugGenerator::clean('SmartBot.chatvia.ai'))->toBe('smartbot')
        ->and(AgentSlugGenerator::clean('--Ask_Me!--'))->toBe('askme')
        ->and(AgentSlugGenerator::clean('ab'))->toBeNull()
        ->and(AgentSlugGenerator::clean(null))->toBeNull();
});

it('returns the cleaned suggestion when it is free', function (): void {
    expect(agentSlugGenerator(['QuickHelper'])->generate())->toBe('quickhelper');
});

it('retries a taken suggestion', function (): void {
    Agent::factory()->create(['slug' => 'quickhelper']);

    expect(agentSlugGenerator(['QuickHelper', 'Answer Owl'])->generate())->toBe('answer-owl');
});

it('treats another agent\'s external id as taken', function (): void {
    Agent::factory()->create(['external_id' => 'quickhelper']);

    expect(agentSlugGenerator(['QuickHelper', 'Answer Owl'])->generate())->toBe('answer-owl');
});

it('falls back to a random free name after the retries run out', function (): void {
    Agent::factory()->create(['slug' => 'taken']);

    $name = agentSlugGenerator(['taken', 'taken', 'taken'])->generate();

    expect($name)->toStartWith('agent-')
        ->and($name)->toMatch('/^[a-z0-9-]+$/');
});

it('falls back to a random name when the model is unreachable', function (): void {
    expect(agentSlugGenerator(new RuntimeException('down'))->generate())->toStartWith('agent-');
});

it('falls back to a random name when every reply is empty', function (): void {
    expect(agentSlugGenerator(['', '', ''])->generate())->toStartWith('agent-');
});

it('gives the model room to reason before it names the agent', function (): void {
    testLlm();
    $sent = null;

    $provider = Mockery::mock(LlmProviderInterface::class);
    $provider->shouldReceive('completion')->once()->andReturnUsing(function (array $data) use (&$sent): Response {
        $sent = $data;

        return new Response(new Psr7Response(200, [], json_encode(['choices' => [['message' => ['content' => 'answer-owl']]]])));
    });

    $name = new AgentSlugGenerator(app(LlmChat::class)->using($provider), app(TaskModels::class))->generate();

    expect($name)->toBe('answer-owl')
        ->and($sent['max_tokens'] ?? $sent['max_completion_tokens'] ?? 0)->toBeGreaterThanOrEqual(400);
});
