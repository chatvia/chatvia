<?php

use App\Admin\Resources\Workspaces\Pages\ViewWorkspace;
use App\Admin\Resources\Workspaces\WorkspaceResource;
use App\Identity\Workspace\Models\MessageLimit;
use App\Identity\Workspace\Models\User;
use Livewire\Livewire;

/**
 * A platform admin sets or removes a workspace's monthly message limit from
 * the workspace page of the admin panel.
 */
beforeEach(function (): void {
    $this->admin = User::factory()->admin()->create()->fresh();
    $this->admin->saveAppAuthenticationSecret('JBSWY3DPEHPK3PXP');
    $this->workspace = User::factory()->workspace()->create();
});

it('shows the workspace page with its message limit', function (): void {
    MessageLimit::create([
        'user_id' => $this->workspace->id,
        'monthly_limit' => 750,
        'messages_used' => 40,
        'period_starts_at' => now()->startOfMonth(),
    ]);

    $this->actingAs($this->admin, 'admin')
        ->get(WorkspaceResource::getUrl('view', ['record' => $this->workspace]))
        ->assertOk()
        ->assertSee('Monthly limit on agent replies')
        ->assertSee('750');
});

it('sets a monthly message limit', function (): void {
    Livewire::actingAs($this->admin, 'admin')
        ->test(ViewWorkspace::class, ['record' => $this->workspace->getRouteKey()])
        ->callAction('messageLimit', data: ['monthly_limit' => 500])
        ->assertHasNoActionErrors();

    expect(MessageLimit::query()->where('user_id', $this->workspace->id)->value('monthly_limit'))->toBe(500);
});

it('removes the limit when the field is left empty', function (): void {
    MessageLimit::create([
        'user_id' => $this->workspace->id,
        'monthly_limit' => 100,
        'messages_used' => 0,
        'period_starts_at' => now()->startOfMonth(),
    ]);

    Livewire::actingAs($this->admin, 'admin')
        ->test(ViewWorkspace::class, ['record' => $this->workspace->getRouteKey()])
        ->callAction('messageLimit', data: ['monthly_limit' => null])
        ->assertHasNoActionErrors();

    expect(MessageLimit::query()->where('user_id', $this->workspace->id)->exists())->toBeFalse();
});

it('rejects a limit below one', function (): void {
    Livewire::actingAs($this->admin, 'admin')
        ->test(ViewWorkspace::class, ['record' => $this->workspace->getRouteKey()])
        ->callAction('messageLimit', data: ['monthly_limit' => 0])
        ->assertHasActionErrors(['monthly_limit']);

    expect(MessageLimit::query()->where('user_id', $this->workspace->id)->exists())->toBeFalse();
});
