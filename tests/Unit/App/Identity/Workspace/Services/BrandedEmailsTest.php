<?php

use App\Identity\Workspace\Enums\DataExportStatus;
use App\Identity\Workspace\Models\DataExport;
use App\Identity\Workspace\Models\User;
use App\Identity\Workspace\Notifications\AccountDeletionConfirmationNotification;
use App\Identity\Workspace\Notifications\DataExportReadyNotification;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Str;

/**
 * Transactional emails name ChatVia.ai through the `:app` placeholder, in
 * English and in the translated copies.
 */
it('names ChatVia.ai in the data export and account deletion emails', function (string $locale, string $subject, string $intro): void {
    App::setLocale($locale);
    $user = User::factory()->create();
    $export = DataExport::create([
        'user_id' => $user->id,
        'status' => DataExportStatus::Ready,
        'download_token' => Str::repeat('d', 64),
        'file_path' => 'data-exports/x.zip',
        'file_size' => 1,
        'expires_at' => now()->addDay(),
    ]);

    $export = new DataExportReadyNotification($export)->toMail($user);
    $deletion = new AccountDeletionConfirmationNotification('https://app.example/confirm', 'Sam')->toMail($user);

    expect($export->subject)->toBe($subject)
        ->and($deletion->introLines[0])->toBe($intro);
})->with([
    'English' => ['en', 'Your ChatVia.ai data export is ready', 'We received a request to permanently delete your ChatVia.ai account.'],
    'German' => ['de', 'Ihr ChatVia.ai-Datenexport ist bereit', 'Wir haben eine Anfrage zum dauerhaften Löschen Ihres ChatVia.ai-Kontos erhalten.'],
]);
