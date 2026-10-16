<?php

use App\Identity\Workspace\Models\User;
use Domain\Form\Actions\DeleteFormDefinitionAction;
use Domain\Form\Actions\EnableFormsCapabilityAction;
use Domain\Form\Models\FormDefinition;

/**
 * The Forms capability follows the agent's forms: adding one turns it on,
 * removing the last one turns it off.
 */
beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->agent = makeTeamAgent($this->user, 'Support Bot');

    // The workspace-level forms server already exists, so enabling is a pure attach.
    $this->user->workspace()->mcpServers()->create([
        'connector_slug' => FormDefinition::CAPABILITY_SLUG,
        'category' => 'builtin',
        'name' => 'Forms',
        'slug' => 'forms',
        'url' => config('app.url').'/mcp/forms',
        'auth_type' => 'none',
        'is_enabled' => true,
    ]);
});

it('turns Forms on for the agent, once', function (): void {
    app(EnableFormsCapabilityAction::class)->execute($this->agent);
    app(EnableFormsCapabilityAction::class)->execute($this->agent);

    expect($this->agent->isFormsEnabled())->toBeTrue()
        ->and($this->agent->mcpServers()->count())->toBe(1);
});

it('turns Forms off only when the last form is deleted', function (): void {
    [$first, $second] = FormDefinition::factory()->forAgent($this->agent)->count(2)->create();
    app(EnableFormsCapabilityAction::class)->execute($this->agent);

    app(DeleteFormDefinitionAction::class)->execute($this->agent, $first);
    expect(FormDefinition::query()->whereKey($first->id)->exists())->toBeFalse()
        ->and($this->agent->isFormsEnabled())->toBeTrue();

    app(DeleteFormDefinitionAction::class)->execute($this->agent, $second);
    expect($this->agent->isFormsEnabled())->toBeFalse();
});
