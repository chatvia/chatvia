<?php

use App\Admin\Resources\Workspaces\Pages\ViewWorkspace;
use App\Identity\Workspace\Models\MessageLimit;
use App\Identity\Workspace\Models\User;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

/**
 * A platform admin sets or removes a workspace's monthly message limit from
 * the workspace page of the admin panel.
 */
beforeEach(function (): void {
    $this->admin = adminUser();
    $this->workspace = User::factory()->create()->workspace();
});

function mlaPage(): Testable
{
    return Livewire::actingAs(test()->admin, 'admin')->test(ViewWorkspace::class, ['record' => test()->workspace->getRouteKey()]);
}

it('sets a monthly message limit', function (): void {
    mlaPage()
        ->callAction('messageLimit', data: ['monthly_limit' => 500])
        ->assertHasNoActionErrors();

    expect(MessageLimit::query()->where('workspace_id', $this->workspace->id)->value('monthly_limit'))->toBe(500);
});

it('removes the limit when the field is left empty', function (): void {
    MessageLimit::factory()->forWorkspace($this->workspace)->create();

    mlaPage()
        ->callAction('messageLimit', data: ['monthly_limit' => null])
        ->assertHasNoActionErrors();

    expect(MessageLimit::query()->where('workspace_id', $this->workspace->id)->value('monthly_limit'))->toBeNull();
});
