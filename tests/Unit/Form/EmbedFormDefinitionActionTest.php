<?php

use Domain\Agent\Models\Agent;
use Domain\Form\Actions\EmbedFormDefinitionAction;
use Domain\Form\Models\FormDefinition;
use Domain\Knowledge\Embedding\Contracts\EmbeddingsProviderInterface;
use Illuminate\Support\Facades\Log;

/**
 * A form to embed, with the vectors the create-observer wrote cleared so each
 * test starts from an unembedded row.
 */
function efdUnembeddedForm(): FormDefinition
{
    $form = FormDefinition::factory()->forAgent(Agent::factory()->create())->create([
        'name' => 'Refund request',
        'slug' => 'refund-request',
        'agent_instructions' => 'Show when the visitor wants their money back.',
    ]);

    $form->forceFill(['embedding' => null, 'embedding_bm25' => null])->saveQuietly();

    return $form;
}

/** @return array{has_vec: bool, has_bm25: bool} */
function efdEmbeddedColumns(FormDefinition $form): array
{
    $row = FormDefinition::query()
        ->whereKey($form->id)
        ->selectRaw('embedding IS NOT NULL AS has_vec, embedding_bm25 IS NOT NULL AS has_bm25')
        ->first();

    return ['has_vec' => (bool) $row->has_vec, 'has_bm25' => (bool) $row->has_bm25];
}

it('writes the dense and sparse vectors for a form', function (): void {
    $form = efdUnembeddedForm();

    app(EmbedFormDefinitionAction::class)->execute($form->id);

    expect(efdEmbeddedColumns($form))->toBe(['has_vec' => true, 'has_bm25' => true]);
});

it('leaves the form unembedded and logs when the embedder fails', function (): void {
    $form = efdUnembeddedForm();
    $failing = Mockery::mock(EmbeddingsProviderInterface::class);
    $failing->shouldReceive('embed')->andThrow(new RuntimeException('embedder down'));
    app()->instance(EmbeddingsProviderInterface::class, $failing);
    Log::spy();

    app(EmbedFormDefinitionAction::class)->execute($form->id);

    expect(efdEmbeddedColumns($form))->toBe(['has_vec' => false, 'has_bm25' => false]);
    Log::shouldHaveReceived('warning')
        ->withArgs(fn (string $message, array $context): bool => $message === 'Form embedding failed'
            && $context === ['form_definition_id' => $form->id, 'error' => 'embedder down'])
        ->once();
});
