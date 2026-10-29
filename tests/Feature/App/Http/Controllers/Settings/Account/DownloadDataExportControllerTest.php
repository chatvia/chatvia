<?php

use App\Identity\Workspace\Models\DataExport;
use App\Identity\Workspace\Models\User;
use Illuminate\Support\Facades\Storage;

/**
 * Downloading a data export (DownloadDataExportController): only the person
 * who asked for it, only while it is ready and not expired. Someone else's
 * export, even a teammate's of the same workspace, is a 404 (DataExportPolicy;
 * the isolation sweep covers another workspace's).
 */
beforeEach(function (): void {
    Storage::fake('local');
    $this->user = User::factory()->create();
});

it('streams a ready export as a ChatVia.ai file', function (): void {
    Storage::disk('local')->put('data-exports/test.zip', 'pretend-zip-bytes');
    $export = DataExport::factory()->ready('data-exports/test.zip')->create(['user_id' => $this->user->id]);

    $response = $this->actingAs($this->user)
        ->get(route('account.data-export.download', $export))
        ->assertOk();

    expect($response->streamedContent())->toBe('pretend-zip-bytes')
        ->and((string) $response->headers->get('Content-Disposition'))->toContain('chatvia-export-'.$this->user->id.'-');
});

it('hides a teammate\'s export from a download', function (): void {
    Storage::disk('local')->put('data-exports/other.zip', 'pretend-zip-bytes');
    $teammate = User::factory()->employee($this->user)->create();
    $export = DataExport::factory()->ready('data-exports/other.zip')->create(['user_id' => $teammate->id]);

    $this->actingAs($this->user)
        ->get(route('account.data-export.download', $export))
        ->assertNotFound();
});

it('refuses to download an export that is not ready', function (string $state): void {
    $factory = DataExport::factory();
    $export = ($state === 'expired' ? $factory->ready()->state(['expires_at' => now()->subHour()]) : $factory->processing())
        ->create(['user_id' => $this->user->id]);

    $this->actingAs($this->user)
        ->get(route('account.data-export.download', $export))
        ->assertForbidden();
})->with([
    'past its download window' => 'expired',
    'still being prepared' => 'processing',
]);
