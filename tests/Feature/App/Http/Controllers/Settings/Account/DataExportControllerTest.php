<?php

use App\Identity\Workspace\Enums\DataExportStatus;
use App\Identity\Workspace\Jobs\PrepareDataExportJob;
use App\Identity\Workspace\Models\DataExport;
use App\Identity\Workspace\Models\User;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia;

/**
 * The Data export page (DataExportController): it shows the person's export in
 * progress or ready, and queues a new one unless one is already active.
 */
beforeEach(function (): void {
    $this->user = User::factory()->create();
});

it('shows the person\'s active export on the page', function (): void {
    $export = DataExport::factory()->processing()->create(['user_id' => $this->user->id]);

    $this->actingAs($this->user)
        ->get(route('account.data-export.edit'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('dashboard/account/pages/DataExport')
            ->where('activeExport.id', $export->id)
            ->where('activeExport.status', 'processing'));
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

it('hides the page and the request while owners may not download their data', function (): void {
    Queue::fake();
    config(['chatvia.account.data_export' => false]);

    $this->actingAs($this->user)->get(route('account.data-export.edit'))->assertNotFound();
    $this->actingAs($this->user)->post(route('account.data-export.store'))->assertNotFound();

    expect(DataExport::query()->where('user_id', $this->user->id)->exists())->toBeFalse();
    Queue::assertNothingPushed();
});
