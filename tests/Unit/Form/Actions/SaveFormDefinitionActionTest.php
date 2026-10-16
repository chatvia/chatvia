<?php

use App\Identity\Workspace\Models\User;
use Domain\Form\Actions\SaveFormDefinitionAction;
use Domain\Form\Data\FormDefinitionData;
use Domain\Form\Enums\FormType;
use Domain\Form\Models\FormDefinition;

beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->agent = makeTeamAgent($this->user, 'Bot');
});

function sfdLeadFormData(string $name = 'Get a quote'): FormDefinitionData
{
    return new FormDefinitionData(
        external_id: null,
        slug: null,
        name: $name,
        subtitle: null,
        form_type: FormType::Lead,
        schema: [
            ['key' => 'email', 'label' => 'Email', 'type' => 'email', 'required' => true],
        ],
        lead_field_map: ['email' => 'email'],
        success_message: 'Thanks!',
        agent_instructions: 'Show when the visitor wants a quote.',
        is_active: true,
    );
}

it('creates a form definition with a derived slug', function (): void {
    $form = app(SaveFormDefinitionAction::class)->execute($this->agent, sfdLeadFormData());

    expect($form->agent_id)->toBe($this->agent->id)
        ->and($form->slug)->toBe('get-a-quote')
        ->and($form->form_type->value)->toBe('lead')
        ->and($form->schema)->toHaveCount(1)
        ->and($form->agent_instructions)->toBe('Show when the visitor wants a quote.')
        ->and($form->lead_field_map)->toBe(['email' => 'email']);
});

it('disambiguates duplicate slugs within an agent', function (): void {
    app(SaveFormDefinitionAction::class)->execute($this->agent, sfdLeadFormData('Contact'));
    $second = app(SaveFormDefinitionAction::class)->execute($this->agent, sfdLeadFormData('Contact'));

    expect($second->slug)->toBe('contact-2');
});

it('updates an existing definition and keeps its slug', function (): void {
    $form = app(SaveFormDefinitionAction::class)->execute($this->agent, sfdLeadFormData('Contact'));

    $updated = app(SaveFormDefinitionAction::class)->execute(
        $this->agent,
        new FormDefinitionData(null, null, 'Contact renamed', null, FormType::Qualification, [
            ['key' => 'company', 'label' => 'Company', 'type' => 'text', 'required' => false],
        ], null, null, null, false),
        $form,
    );

    expect($updated->id)->toBe($form->id)
        ->and($updated->slug)->toBe('contact') // unchanged on update
        ->and($updated->name)->toBe('Contact renamed')
        ->and($updated->is_active)->toBeFalse()
        ->and(FormDefinition::query()->where('agent_id', $this->agent->id)->count())->toBe(1);
});
