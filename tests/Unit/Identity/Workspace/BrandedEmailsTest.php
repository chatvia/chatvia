<?php

use App\Identity\Workspace\Enums\DataExportStatus;
use App\Identity\Workspace\Models\DataExport;
use App\Identity\Workspace\Models\User;
use App\Identity\Workspace\Notifications\AccountDeletionConfirmationNotification;
use App\Identity\Workspace\Notifications\DataExportReadyNotification;
use Illuminate\Support\Facades\App;

/**
 * Transactional emails name Chatvia through the `:app` placeholder, in
 * English and in the translated copies.
 */
it('names Chatvia in the data export and account deletion emails', function (string $locale, string $subject, string $intro): void {
    App::setLocale($locale);
    $user = User::factory()->create();
    $export = DataExport::create([
        'user_id' => $user->id,
        'status' => DataExportStatus::Ready,
        'download_token' => str_repeat('d', 64),
        'file_path' => 'data-exports/x.zip',
        'file_size' => 1,
        'expires_at' => now()->addDay(),
    ]);

    $export = new DataExportReadyNotification($export)->toMail($user);
    $deletion = new AccountDeletionConfirmationNotification('https://app.example/confirm', 'Sam')->toMail($user);

    expect($export->subject)->toBe($subject)
        ->and($deletion->introLines[0])->toBe($intro);
})->with([
    'English' => ['en', 'Your Chatvia data export is ready', 'We received a request to permanently delete your Chatvia account.'],
    'German' => ['de', 'Ihr Chatvia-Datenexport ist bereit', 'Wir haben eine Anfrage zum dauerhaften Löschen Ihres Chatvia-Kontos erhalten.'],
]);
