<?php

use App\Identity\Workspace\Enums\DataExportStatus;
use App\Identity\Workspace\Jobs\PrepareDataExportJob;
use App\Identity\Workspace\Models\DataExport;
use App\Identity\Workspace\Models\User;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    $this->user = User::factory()->create();
});

it('renders the data-export page for the authenticated user', function (): void {
    $this->actingAs($this->user)
        ->get(route('account.data-export.edit'))
        ->assertOk();
});

it('queues a new export when the user requests one', function (): void {
    Queue::fake();

    $this->actingAs($this->user)
        ->post(route('account.data-export.store'))
        ->assertRedirect();

    $row = DataExport::where('user_id', $this->user->id)->sole();
    expect($row->status)->toBe(DataExportStatus::Pending);

    Queue::assertPushed(PrepareDataExportJob::class);
});

it('refuses a second concurrent request', function (): void {
    Queue::fake();

    DataExport::create([
        'user_id' => $this->user->id,
        'status' => DataExportStatus::Processing,
        'download_token' => str_repeat('a', 64),
    ]);

    $this->actingAs($this->user)
        ->from(route('account.data-export.edit'))
        ->post(route('account.data-export.store'))
        ->assertRedirect(route('account.data-export.edit'))
        ->assertSessionHasErrors('data_export');
});

it('lets the owner download a ready export', function (): void {
    Storage::fake('local');
    Storage::disk('local')->put('data-exports/test.zip', 'pretend-zip-bytes');

    $export = DataExport::create([
        'user_id' => $this->user->id,
        'status' => DataExportStatus::Ready,
        'download_token' => str_repeat('a', 64),
        'file_path' => 'data-exports/test.zip',
        'file_size' => 17,
        'expires_at' => now()->addHours(12),
    ]);

    $this->actingAs($this->user)
        ->get(route('account.data-export.download', $export))
        ->assertOk();
});

it('forbids another user from downloading an export', function (): void {
    Storage::fake('local');
    Storage::disk('local')->put('data-exports/other.zip', 'pretend-zip-bytes');

    $other = User::factory()->create();
    $export = DataExport::create([
        'user_id' => $other->id,
        'status' => DataExportStatus::Ready,
        'download_token' => str_repeat('b', 64),
        'file_path' => 'data-exports/other.zip',
        'file_size' => 17,
        'expires_at' => now()->addHours(12),
    ]);

    $this->actingAs($this->user)
        ->get(route('account.data-export.download', $export))
        ->assertForbidden();
});

it('refuses to download an expired export', function (): void {
    Storage::fake('local');
    Storage::disk('local')->put('data-exports/expired.zip', 'pretend-zip-bytes');

    $export = DataExport::create([
        'user_id' => $this->user->id,
        'status' => DataExportStatus::Ready,
        'download_token' => str_repeat('c', 64),
        'file_path' => 'data-exports/expired.zip',
        'file_size' => 17,
        'expires_at' => now()->subHour(),
    ]);

    $this->actingAs($this->user)
        ->get(route('account.data-export.download', $export))
        ->assertForbidden();
});

it('refuses to download an export that is not ready yet', function (): void {
    $export = DataExport::create([
        'user_id' => $this->user->id,
        'status' => DataExportStatus::Processing,
        'download_token' => str_repeat('d', 64),
    ]);

    $this->actingAs($this->user)
        ->get(route('account.data-export.download', $export))
        ->assertForbidden();
});
