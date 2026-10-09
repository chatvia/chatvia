<?php

use App\Identity\Workspace\Models\User;
use Domain\Integration\Function\Models\AgentFunction;
use Domain\Integration\Tool\Actions\Embedding\EmbedAgentFunctionAction;
use Domain\Knowledge\Embedding\Contracts\EmbeddingsProviderInterface;
use Illuminate\Support\Facades\Log;

function embedTestFunction(): AgentFunction
{
    $function = new AgentFunction;
    $function->user_id = User::factory()->create()->id;
    $function->internal_name = 'lookup_order';
    $function->display_name = 'Order lookup';
    $function->description = 'Look up an order by id';
    $function->parameters_schema = ['type' => 'object', 'properties' => []];
    $function->webhook_url = 'https://hooks.example.test/fn';
    $function->webhook_method = 'GET';
    $function->auth_type = 'none';
    $function->is_enabled = true;
    $function->save();

    // Saving runs the observer's embed job on the sync queue; clear its output
    // so each test starts from an unembedded row.
    AgentFunction::query()->whereKey($function->id)->update(['embedding' => null, 'embedding_bm25' => null]);

    return $function;
}

/** @return array{has_vec: bool, has_bm25: bool} */
function embeddedFunctionColumns(int $functionId): array
{
    $row = AgentFunction::query()
        ->whereKey($functionId)
        ->selectRaw('embedding IS NOT NULL AS has_vec, embedding_bm25 IS NOT NULL AS has_bm25')
        ->first();

    return ['has_vec' => (bool) $row->has_vec, 'has_bm25' => (bool) $row->has_bm25];
}

it('writes the dense and sparse vectors for a function', function (): void {
    $function = embedTestFunction();

    app(EmbedAgentFunctionAction::class)->execute($function->id);

    expect(embeddedFunctionColumns($function->id))->toBe(['has_vec' => true, 'has_bm25' => true]);
});

it('is a no-op for a missing function id', function (): void {
    app(EmbedAgentFunctionAction::class)->execute(999_999);
})->throwsNoExceptions();

it('leaves the row unembedded and logs when the embedder fails', function (): void {
    $function = embedTestFunction();
    $failing = Mockery::mock(EmbeddingsProviderInterface::class);
    $failing->shouldReceive('embed')->andThrow(new RuntimeException('embedder down'));
    app()->instance(EmbeddingsProviderInterface::class, $failing);
    Log::spy();

    app(EmbedAgentFunctionAction::class)->execute($function->id);

    expect(embeddedFunctionColumns($function->id))->toBe(['has_vec' => false, 'has_bm25' => false]);
    Log::shouldHaveReceived('warning')->withArgs(fn (string $message): bool => $message === 'Function embedding failed')->once();
});
