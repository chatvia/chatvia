<?php

use App\Identity\Workspace\Models\DataExport;
use App\Identity\Workspace\Models\User;
use App\Identity\Workspace\Notifications\AccountDeletionConfirmationNotification;
use App\Identity\Workspace\Notifications\DataExportReadyNotification;
use Illuminate\Support\Facades\App;

/**
 * Transactional emails name ChatVia.ai through the `:app` placeholder, in
 * English and in the translated copies.
 */
it('names ChatVia.ai in the data export and account deletion emails', function (string $locale, string $subject, string $intro): void {
    App::setLocale($locale);
    $user = User::factory()->create();
    $export = DataExport::factory()->ready()->create(['user_id' => $user->id]);

    $export = new DataExportReadyNotification($export)->toMail($user);
    $deletion = new AccountDeletionConfirmationNotification('https://app.example/confirm', 'Sam')->toMail($user);

    expect($export->subject)->toBe($subject)
        ->and($deletion->introLines[0])->toBe($intro);
})->with([
    'English' => ['en', 'Your ChatVia.ai data export is ready', 'We received a request to permanently delete your ChatVia.ai account.'],
    'German' => ['de', 'Ihr ChatVia.ai-Datenexport ist bereit', 'Wir haben eine Anfrage zum dauerhaften Löschen Ihres ChatVia.ai-Kontos erhalten.'],
]);
