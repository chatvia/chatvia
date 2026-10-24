<?php

use App\Admin\Resources\Workspaces\Pages\ViewWorkspace;
use App\Admin\Resources\Workspaces\WorkspaceResource;
use App\Identity\Chatter\Models\Chatter;
use App\Identity\Workspace\Actions\Account\DeleteAccountAction;
use App\Identity\Workspace\Jobs\DeleteWorkspaceJob;
use App\Identity\Workspace\Jobs\PrepareDataExportJob;
use App\Identity\Workspace\Models\DataExport;
use App\Identity\Workspace\Models\User;
use App\Identity\Workspace\Services\WorkspaceActivation;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

/**
 * What staff may do to a workspace from its page in the admin panel: edit
 * it and its owner, reset the owner's password, deactivate, reactivate,
 * delete, turn off two-step sign-in, and export its data. A workspace that
 * platform staff belong to can be neither switched off nor deleted.
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

it('deactivates the workspace with a reason and reactivates it', function (): void {
    $teammate = User::factory()->employee($this->owner)->create();
    $chatter = Chatter::factory()->ofWorkspace($this->owner)->create();

    vwaPage()
        ->assertActionHidden('reactivate')
        ->callAction('deactivate', data: ['reason' => 'Unpaid invoice'])
        ->assertHasNoActionErrors()
        ->assertActionHidden('deactivate')
        ->assertActionVisible('reactivate');

    $activation = app(WorkspaceActivation::class);
    $workspaceId = $this->owner->workspace()->id;

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

it('needs a reason to deactivate', function (): void {
    vwaPage()
        ->callAction('deactivate', data: ['reason' => ''])
        ->assertHasActionErrors(['reason' => 'required']);

    expect($this->owner->fresh()->isDeactivated())->toBeFalse();
});

it('disables deactivate and delete for a workspace with platform staff in it', function (): void {
    Livewire::actingAs($this->admin, 'admin')
        ->test(ViewWorkspace::class, ['record' => $this->admin->workspace()->getRouteKey()])
        ->assertActionVisible('deactivate')
        ->assertActionDisabled('deactivate')
        ->assertSee('Platform staff belong to this workspace. Switching it off would lock them out of this panel.')
        ->assertActionDisabled('delete')
        ->assertActionEnabled('messageLimit')
        ->assertActionEnabled('edit')
        ->callAction('deactivate', data: ['reason' => 'Testing']);

    expect($this->admin->fresh()->isDeactivated())->toBeFalse();
});

it('edits the workspace name and the owner\'s name and email', function (): void {
    vwaPage()
        ->assertActionVisible('edit')
        ->mountAction('edit')
        ->assertActionDataSet([
            'name' => 'Acme Corp',
            'owner_name' => 'Dana Owner',
            'owner_email' => 'dana@acme.test',
        ])
        ->fillForm(['name' => 'Acme GmbH', 'owner_name' => 'Dana Smith', 'owner_email' => 'dana@acme.example'])
        ->callMountedAction()
        ->assertHasNoActionErrors()
        ->assertNotified('Workspace saved');

    $owner = $this->owner->fresh();
    expect($this->workspace->fresh()->name)->toBe('Acme GmbH')
        ->and($owner->name)->toBe('Dana Smith')
        ->and($owner->email)->toBe('dana@acme.example')
        ->and($owner->email_verified_at)->toBeNull();
});

it('keeps the owner\'s email confirmed when it does not change', function (): void {
    vwaPage()
        ->callAction('edit', data: ['name' => 'Acme GmbH', 'owner_name' => 'Dana Owner', 'owner_email' => 'dana@acme.test'])
        ->assertHasNoActionErrors();

    expect($this->owner->fresh()->email_verified_at)->not->toBeNull();
});

it('refuses an edit that leaves the owner without a valid email or the workspace without a name', function (array $data, array $errors): void {
    User::factory()->create(['email' => 'taken@acme.test']);

    vwaPage()
        ->callAction('edit', data: [...['name' => 'Acme Corp', 'owner_name' => 'Dana Owner', 'owner_email' => 'dana@acme.test'], ...$data])
        ->assertHasActionErrors($errors);

    expect($this->owner->fresh()->email)->toBe('dana@acme.test');
})->with([
    'an email another account uses' => [['owner_email' => 'taken@acme.test'], ['owner_email' => 'unique']],
    'no name' => [['name' => ''], ['name' => 'required']],
    'capital letters in the email' => [['owner_email' => 'Dana@Acme.test'], ['owner_email' => 'lowercase']],
]);

it('emails the owner a password reset link', function (): void {
    Notification::fake();

    vwaPage()
        ->callAction('sendPasswordResetLink')
        ->assertHasNoActionErrors()
        ->assertNotified('Password reset link sent');

    Notification::assertSentTo($this->owner, ResetPassword::class);

    vwaPage()
        ->callAction('sendPasswordResetLink')
        ->assertNotified('No link sent');

    Notification::assertSentToTimes($this->owner, ResetPassword::class, 1);
});

it('sets a new password for the owner', function (): void {
    vwaPage()
        ->callAction('setPassword', data: ['password' => ''])
        ->assertHasActionErrors(['password' => 'required']);

    vwaPage()
        ->callAction('setPassword', data: ['password' => 'a-new-secret-1'])
        ->assertHasNoActionErrors()
        ->assertNotified('Password changed');

    expect(Hash::check('a-new-secret-1', $this->owner->fresh()->password))->toBeTrue();
});

it('deletes the workspace only after its name is typed', function (): void {
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
        ->assertSee('Being deleted')
        ->assertActionHidden('delete')
        ->assertActionHidden('reactivate')
        ->assertActionHidden('deactivate')
        ->assertActionHidden('edit')
        ->assertActionHidden('sendPasswordResetLink')
        ->assertActionHidden('setPassword');
});

it('turns off the owner\'s two-step sign-in', function (): void {
    vwaPage()->assertActionHidden('disableTwoFactor');

    $this->owner->forceFill([
        'two_factor_secret' => encrypt('secret'),
        'two_factor_confirmed_at' => now(),
        'app_authentication_secret' => 'app-secret',
    ])->save();

    vwaPage()
        ->callAction('disableTwoFactor')
        ->assertHasNoActionErrors();

    $owner = $this->owner->fresh();
    expect($owner->two_factor_secret)->toBeNull()
        ->and($owner->two_factor_confirmed_at)->toBeNull()
        ->and($owner->app_authentication_secret)->toBeNull();
});

it('starts a data export for the owner once at a time', function (): void {
    Queue::fake();

    vwaPage()
        ->callAction('exportData')
        ->assertHasNoActionErrors()
        ->assertNotified('Export started');

    expect(DataExport::query()->where('user_id', $this->owner->id)->count())->toBe(1);
    Queue::assertPushed(PrepareDataExportJob::class);

    vwaPage()
        ->callAction('exportData')
        ->assertNotified('An export is already being prepared or can be downloaded');

    expect(DataExport::query()->where('user_id', $this->owner->id)->count())->toBe(1);
});
