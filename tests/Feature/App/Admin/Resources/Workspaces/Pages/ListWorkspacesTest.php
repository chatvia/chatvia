<?php

use App\Admin\Resources\Workspaces\Pages\ListWorkspaces;
use App\Admin\Resources\Workspaces\WorkspaceResource;
use App\Identity\Workspace\Actions\Account\DeactivateAccountAction;
use App\Identity\Workspace\Models\User;
use Filament\Facades\Filament;

/**
 * The admin panel's list of workspaces, with its status filter (every
 * workspace, active ones, deactivated ones). A workspace is deactivated
 * when its owner is.
 */
beforeEach(function (): void {
    Filament::setCurrentPanel('admin');
    $this->admin = adminUser();

    $this->active = User::factory()->create(['name' => 'Active Owner'])->workspace();
    $this->active->update(['name' => 'Acme Active']);

    $owner = User::factory()->create(['name' => 'Gone Owner']);
    $owner->workspace()->update(['name' => 'Gone Corp']);
    app(DeactivateAccountAction::class)->execute($owner, 'Unpaid invoices');
    $this->deactivated = $owner->workspace()->fresh();
});

it('lists the workspaces with their owner', function (): void {
    $this->actingAs($this->admin, 'admin')
        ->get(WorkspaceResource::getUrl('index'))
        ->assertOk()
        ->assertSee(['Acme Active', 'Gone Corp']);
});

it('filters the list by status', function (): void {
    $page = adminPage(ListWorkspaces::class, $this->admin);

    $page->assertCanSeeTableRecords([$this->active, $this->deactivated]);

    $page->filterTable('status', true)
        ->assertCanSeeTableRecords([$this->deactivated])
        ->assertCanNotSeeTableRecords([$this->active]);

    $page->filterTable('status', false)
        ->assertCanSeeTableRecords([$this->active])
        ->assertCanNotSeeTableRecords([$this->deactivated]);
});
