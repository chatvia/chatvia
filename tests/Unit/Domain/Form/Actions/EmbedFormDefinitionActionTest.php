<?php

use Domain\Agent\Models\Agent;
use Domain\Form\Actions\EmbedFormDefinitionAction;
use Domain\Form\Models\FormDefinition;

/**
 * Embedding a form writes its dense vector and its sparse (full-text) column,
 * which the form search reads.
 */
function efdUnembeddedForm(): FormDefinition
{
    $form = FormDefinition::factory()->forAgent(Agent::factory()->create())->create([
        'name' => 'Refund request',
        'slug' => 'refund-request',
        'agent_instructions' => 'Show when the visitor wants their money back.',
    ]);

    $form->forceFill(['embedding' => null, 'search_text' => null])->saveQuietly();

    return $form;
}

/** @return array{has_vec: bool, has_bm25: bool} */
function efdEmbeddedColumns(FormDefinition $form): array
{
    $row = FormDefinition::query()
        ->whereKey($form->id)
        ->selectRaw('embedding IS NOT NULL AS has_vec, search_text IS NOT NULL AS has_bm25')
        ->first();

    return ['has_vec' => (bool) $row->has_vec, 'has_bm25' => (bool) $row->has_bm25];
}

it('writes the dense and sparse vectors for a form', function (): void {
    $form = efdUnembeddedForm();

    app(EmbedFormDefinitionAction::class)->execute($form->id);

    expect(efdEmbeddedColumns($form))->toBe(['has_vec' => true, 'has_bm25' => true]);
});
