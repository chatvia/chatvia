<?php

use App\Identity\Workspace\Actions\DataExport\BuildDataExportArchiveAction;
use App\Identity\Workspace\Enums\DataExportStatus;
use App\Identity\Workspace\Events\DataExportReady;
use App\Identity\Workspace\Models\DataExport;
use App\Identity\Workspace\Models\User;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;

it('packages user data into a zip and marks the export ready', function (): void {
    Event::fake([DataExportReady::class]);

    $user = User::factory()->create();

    $export = DataExport::create([
        'user_id' => $user->id,
        'status' => DataExportStatus::Pending,
        'download_token' => str_repeat('z', 64),
    ]);

    app(BuildDataExportArchiveAction::class)->execute($export);

    $export->refresh();

    expect($export->status)->toBe(DataExportStatus::Ready)
        ->and($export->file_path)->toStartWith('data-exports/'.$user->id.'/')
        ->and($export->file_path)->toEndWith('.zip')
        ->and($export->file_size)->toBeGreaterThan(0)
        ->and($export->expires_at)->not->toBeNull();

    expect(Storage::disk('local')->exists($export->file_path))->toBeTrue();

    $absolute = Storage::disk('local')->path($export->file_path);
    $zip = new ZipArchive;
    expect($zip->open($absolute))->toBeTrue();

    $entries = [];
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $entries[] = $zip->getNameIndex($i);
    }
    $zip->close();

    expect($entries)->toContain('README.txt')
        ->toContain('profile.json')
        ->toContain('agents.json')
        ->toContain('conversations.json')
        ->toContain('messages.json')
        ->toContain('billing.json');

    Event::assertDispatched(DataExportReady::class);
});
