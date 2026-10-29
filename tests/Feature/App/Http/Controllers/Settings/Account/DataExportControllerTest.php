<?php

use App\Identity\Workspace\Enums\DataExportStatus;
use App\Identity\Workspace\Jobs\PrepareDataExportJob;
use App\Identity\Workspace\Models\DataExport;
use App\Identity\Workspace\Models\User;
use Illuminate\Support\Facades\Queue;

/**
 * Asking for a data export (DataExportController) queues a new one unless one
 * is already active.
 */
beforeEach(function (): void {
    $this->user = User::factory()->create();
});

it('queues a new export when the person asks for one', function (): void {
    Queue::fake();

    $this->actingAs($this->user)
        ->post(route('account.data-export.store'))
        ->assertRedirect()
        ->assertSessionHas('status', 'data-export-requested');

    expect(DataExport::query()->where('user_id', $this->user->id)->sole()->status)->toBe(DataExportStatus::Pending);
    Queue::assertPushed(PrepareDataExportJob::class);
});

it('refuses a second request while an export is being prepared', function (): void {
    Queue::fake();
    DataExport::factory()->processing()->create(['user_id' => $this->user->id]);

    $this->actingAs($this->user)
        ->from(route('account.data-export.edit'))
        ->post(route('account.data-export.store'))
        ->assertRedirect(route('account.data-export.edit'))
        ->assertSessionHasErrors('data_export');

    Queue::assertNothingPushed();
});
