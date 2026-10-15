<?php

use Domain\Agent\Models\Agent;
use Domain\Form\Actions\EmbedFormDefinitionAction;
use Domain\Form\Models\FormDefinition;

it('writes the dense and sparse vectors for a form', function (): void {
    $agent = Agent::factory()->create();
    $form = FormDefinition::factory()->forAgent($agent)->create([
        'name' => 'Refund request',
        'slug' => 'refund-request',
        'agent_instructions' => 'Show when the visitor wants their money back.',
    ]);

    // Clear what the create-observer populated, then re-embed explicitly.
    $form->forceFill(['embedding' => null, 'embedding_bm25' => null])->saveQuietly();

    app(EmbedFormDefinitionAction::class)->execute($form->id);

    $row = FormDefinition::query()
        ->whereKey($form->id)
        ->selectRaw('embedding IS NOT NULL AS has_vec, embedding_bm25 IS NOT NULL AS has_bm25')
        ->first();

    expect((bool) $row->has_vec)->toBeTrue()
        ->and((bool) $row->has_bm25)->toBeTrue();
});

it('is a no-op for a missing form id', function (): void {
    app(EmbedFormDefinitionAction::class)->execute(999_999);
})->throwsNoExceptions();
