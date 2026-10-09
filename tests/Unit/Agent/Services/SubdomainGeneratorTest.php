<?php

use Domain\Agent\Models\Agent;
use Domain\Agent\Services\SubdomainGenerator;
use Domain\Llm\Contracts\LlmProviderInterface;
use Domain\Llm\Services\LlmChat;
use GuzzleHttp\Psr7\Response as Psr7Response;
use Illuminate\Http\Client\Response;

/**
 * The "generate a subdomain" button: the model's suggestion is cleaned to a
 * subdomain-safe label, a taken one is retried, and the button always gets a
 * free name — a random one when the model can't help.
 *
 * @param  list<string>|Throwable  $replies
 */
function subdomainGenerator(array|Throwable $replies): SubdomainGenerator
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

    return new SubdomainGenerator(new LlmChat($provider));
}

it('lowercases before stripping, so capital letters are kept', function (): void {
    expect(SubdomainGenerator::clean('"HelpDesk Hero"'))->toBe('helpdesk-hero')
        ->and(SubdomainGenerator::clean('SmartBot.chatvia.ai'))->toBe('smartbot')
        ->and(SubdomainGenerator::clean('--Ask_Me!--'))->toBe('askme')
        ->and(SubdomainGenerator::clean('ab'))->toBeNull()
        ->and(SubdomainGenerator::clean(null))->toBeNull();
});

it('returns the cleaned suggestion when it is free', function (): void {
    expect(subdomainGenerator(['QuickHelper'])->generate())->toBe('quickhelper');
});

it('retries a taken suggestion', function (): void {
    Agent::factory()->create(['sub_domain' => 'quickhelper']);

    expect(subdomainGenerator(['QuickHelper', 'Answer Owl'])->generate())->toBe('answer-owl');
});

it('falls back to a random free name after the retries run out', function (): void {
    Agent::factory()->create(['sub_domain' => 'taken']);

    $name = subdomainGenerator(['taken', 'taken', 'taken'])->generate();

    expect($name)->toStartWith('agent-')
        ->and($name)->toMatch('/^[a-z0-9-]+$/');
});

it('falls back to a random name when the model is unreachable', function (): void {
    expect(subdomainGenerator(new RuntimeException('down'))->generate())->toStartWith('agent-');
});
