<?php

use App\Admin\Resources\Workspaces\Pages\ViewWorkspace;
use App\Admin\Resources\Workspaces\WorkspaceResource;
use App\Identity\Chatter\Models\Chatter;
use App\Identity\Workspace\Actions\Account\DeleteAccountAction;
use App\Identity\Workspace\Jobs\DeleteWorkspaceJob;
use App\Identity\Workspace\Models\User;
use App\Identity\Workspace\Services\WorkspaceActivation;
use Illuminate\Support\Facades\Queue;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

/**
 * What staff may do to a workspace from its page in the admin panel:
 * deactivate it with a reason and reactivate it, and delete it once its
 * name is typed. A workspace that platform staff belong to can be neither
 * switched off nor deleted.
 */
beforeEach(function (): void {
    $this->admin = adminUser();
    $this->owner = User::factory()->create([
        'name' => 'Dana Owner',
        'email' => 'dana@acme.test',
        'locale' => 'de',
        'registered_country_code' => 'de',
        'registered_ip' => '203.0.113.9',
    ]);
    $this->workspace = $this->owner->workspace();
    $this->workspace->update(['name' => 'Acme Corp']);
});

function vwaPage(): Testable
{
    return Livewire::actingAs(test()->admin, 'admin')
        ->test(ViewWorkspace::class, ['record' => test()->workspace->getRouteKey()]);
}

it('deactivates the workspace only with a reason, and reactivates it', function (): void {
    $teammate = User::factory()->employee($this->owner)->create();
    $chatter = Chatter::factory()->ofWorkspace($this->owner)->create();
    $activation = app(WorkspaceActivation::class);
    $workspaceId = $this->owner->workspace()->id;

    vwaPage()
        ->callAction('deactivate', data: ['reason' => ''])
        ->assertHasActionErrors(['reason' => 'required']);

    expect($this->owner->fresh()->isDeactivated())->toBeFalse();

    vwaPage()
        ->callAction('deactivate', data: ['reason' => 'Unpaid invoice'])
        ->assertHasNoActionErrors()
        ->assertActionHidden('deactivate')
        ->assertActionVisible('reactivate');

    expect($this->owner->fresh()->deactivation_reason)->toBe('Unpaid invoice')
        ->and($teammate->fresh()->isDeactivated())->toBeTrue()
        ->and($chatter->fresh()->deactivated_at)->not->toBeNull()
        ->and($activation->isActive($workspaceId))->toBeFalse();

    vwaPage()
        ->callAction('reactivate')
        ->assertHasNoActionErrors();

    expect($this->owner->fresh()->isDeactivated())->toBeFalse()
        ->and($teammate->fresh()->isDeactivated())->toBeFalse()
        ->and($chatter->fresh()->deactivated_at)->toBeNull()
        ->and($activation->isActive($workspaceId))->toBeTrue();
});

it('disables deactivate and delete for a workspace with platform staff in it', function (): void {
    Livewire::actingAs($this->admin, 'admin')
        ->test(ViewWorkspace::class, ['record' => $this->admin->workspace()->getRouteKey()])
        ->assertActionDisabled('deactivate')
        ->assertActionDisabled('delete')
        ->callAction('deactivate', data: ['reason' => 'Testing']);

    expect($this->admin->fresh()->isDeactivated())->toBeFalse();
});

it('deletes the workspace only after its name is typed, and locks its actions', function (): void {
    Queue::fake();

    vwaPage()
        ->callAction('delete', data: ['confirm_name' => 'Acme'])
        ->assertHasActionErrors(['confirm_name']);

    Queue::assertNothingPushed();

    vwaPage()
        ->callAction('delete', data: ['confirm_name' => 'Acme Corp'])
        ->assertHasNoActionErrors()
        ->assertRedirect(WorkspaceResource::getUrl('index'));

    Queue::assertPushed(DeleteWorkspaceJob::class, fn (DeleteWorkspaceJob $job): bool => $job->userId === $this->owner->id);
    expect($this->owner->fresh()->deactivation_reason)->toBe(DeleteAccountAction::DELETION_REASON);

    vwaPage()
        ->assertActionHidden('delete')
        ->assertActionHidden('reactivate')
        ->assertActionHidden('deactivate')
        ->assertActionHidden('edit')
        ->assertActionHidden('sendPasswordResetLink')
        ->assertActionHidden('setPassword');
});
