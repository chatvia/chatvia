<?php

use App\Identity\Workspace\Actions\DataExport\RequestDataExportAction;
use App\Identity\Workspace\Data\DataExport\RequestDataExportData;
use App\Identity\Workspace\Enums\DataExportStatus;
use App\Identity\Workspace\Exceptions\ActiveDataExportExistsException;
use App\Identity\Workspace\Jobs\PrepareDataExportJob;
use App\Identity\Workspace\Models\DataExport;
use App\Identity\Workspace\Models\User;
use Illuminate\Support\Facades\Queue;

it('creates a pending export and dispatches the build job', function (): void {
    Queue::fake();

    $user = User::factory()->create();

    $export = app(RequestDataExportAction::class)
        ->execute(new RequestDataExportData(user: $user));

    expect($export)->toBeInstanceOf(DataExport::class)
        ->and($export->user_id)->toBe($user->id)
        ->and($export->status)->toBe(DataExportStatus::Pending)
        ->and($export->download_token)->toHaveLength(64);

    Queue::assertPushed(PrepareDataExportJob::class, fn (PrepareDataExportJob $job): bool => $job->dataExportId === $export->id);
});

it('refuses to start a second export while one is pending', function (): void {
    Queue::fake();

    $user = User::factory()->create();

    DataExport::create([
        'user_id' => $user->id,
        'status' => DataExportStatus::Pending,
        'download_token' => str_repeat('a', 64),
    ]);

    app(RequestDataExportAction::class)
        ->execute(new RequestDataExportData(user: $user));
})->throws(ActiveDataExportExistsException::class);

it('refuses to start a second export while a ready export has not expired', function (): void {
    Queue::fake();

    $user = User::factory()->create();

    DataExport::create([
        'user_id' => $user->id,
        'status' => DataExportStatus::Ready,
        'download_token' => str_repeat('b', 64),
        'file_path' => 'data-exports/1/abc.zip',
        'file_size' => 1024,
        'expires_at' => now()->addHours(12),
    ]);

    app(RequestDataExportAction::class)
        ->execute(new RequestDataExportData(user: $user));
})->throws(ActiveDataExportExistsException::class);

it('allows a new request once the previous export has expired', function (): void {
    Queue::fake();

    $user = User::factory()->create();

    DataExport::create([
        'user_id' => $user->id,
        'status' => DataExportStatus::Ready,
        'download_token' => str_repeat('c', 64),
        'file_path' => 'data-exports/1/old.zip',
        'file_size' => 1024,
        'expires_at' => now()->subHour(),
    ]);

    $export = app(RequestDataExportAction::class)
        ->execute(new RequestDataExportData(user: $user));

    expect($export->status)->toBe(DataExportStatus::Pending);
    Queue::assertPushed(PrepareDataExportJob::class);
});

it('allows a new request after the previous one failed', function (): void {
    Queue::fake();

    $user = User::factory()->create();

    DataExport::create([
        'user_id' => $user->id,
        'status' => DataExportStatus::Failed,
        'download_token' => str_repeat('d', 64),
        'error_message' => 'something broke',
    ]);

    $export = app(RequestDataExportAction::class)
        ->execute(new RequestDataExportData(user: $user));

    expect($export->status)->toBe(DataExportStatus::Pending);
});
