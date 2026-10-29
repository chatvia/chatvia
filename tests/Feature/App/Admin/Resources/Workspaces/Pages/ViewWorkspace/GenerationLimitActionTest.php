<?php

use App\Admin\Resources\Workspaces\Pages\ViewWorkspace;
use App\Identity\Workspace\Models\GenerationLimit;
use App\Identity\Workspace\Models\User;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

/**
 * A platform admin sets or removes a workspace's monthly cap on the pictures
 * and audio files its agents make, from the workspace page of the admin
 * panel.
 */
beforeEach(function (): void {
    $this->admin = adminUser();
    $this->workspace = User::factory()->create()->workspace();
});

function glaPage(): Testable
{
    return Livewire::actingAs(test()->admin, 'admin')->test(ViewWorkspace::class, ['record' => test()->workspace->getRouteKey()]);
}

it('sets a monthly generation limit', function (): void {
    glaPage()
        ->callAction('generationLimit', data: ['monthly_limit' => 80])
        ->assertHasNoActionErrors();

    expect(GenerationLimit::query()->where('workspace_id', $this->workspace->id)->value('monthly_limit'))->toBe(80);
});

it('removes the limit when the field is left empty', function (): void {
    GenerationLimit::factory()->forWorkspace($this->workspace)->create();

    glaPage()
        ->callAction('generationLimit', data: ['monthly_limit' => null])
        ->assertHasNoActionErrors();

    expect(GenerationLimit::query()->where('workspace_id', $this->workspace->id)->exists())->toBeFalse();
});
