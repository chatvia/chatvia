<?php

declare(strict_types=1);

use Domain\Integration\Function\Models\AgentFunction;
use Domain\Integration\Tool\Actions\Embedding\EmbedAgentFunctionAction;
use Domain\Knowledge\Embedding\Contracts\EmbeddingsProvider;
use Illuminate\Support\Facades\Log;
use Tests\Support\Integration\Tool\ToolVectors;

/**
 * Embedding one webhook function for tool routing: both its vectors are
 * written, and a failing embedder leaves the row unembedded with a warning
 * instead of failing the save.
 */
function eafFunction(): AgentFunction
{
    $function = AgentFunction::factory()->create([
        'internal_name' => 'lookup_order',
        'display_name' => 'Order lookup',
        'description' => 'Look up an order by id',
    ]);

    // Creating it runs the observer's embed job on the sync queue; clear its
    // output so each test starts from an unembedded row.
    AgentFunction::query()->whereKey($function->id)->update(['embedding' => null, 'search_text' => null]);

    return $function;
}

it('writes the dense and sparse vectors for a function', function (): void {
    $function = eafFunction();

    app(EmbedAgentFunctionAction::class)->execute($function->id);

    expect(ToolVectors::of($function))->toBe(['dense' => true, 'keyword' => true]);
});

it('leaves the row unembedded and logs when the embedder fails', function (): void {
    $function = eafFunction();
    $failing = Mockery::mock(EmbeddingsProvider::class);
    $failing->shouldReceive('embed')->andThrow(new RuntimeException('embedder down'));
    app()->instance(EmbeddingsProvider::class, $failing);
    Log::spy();

    app(EmbedAgentFunctionAction::class)->execute($function->id);

    expect(ToolVectors::of($function))->toBe(['dense' => false, 'keyword' => false]);
    Log::shouldHaveReceived('warning')->withArgs(fn (string $message): bool => $message === 'Function embedding failed')->once();
});
