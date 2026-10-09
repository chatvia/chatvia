<?php

use App\Identity\Workspace\Enums\DataExportStatus;
use App\Identity\Workspace\Models\DataExport;
use App\Identity\Workspace\Models\User;
use App\Identity\Workspace\Notifications\AccountDeletionConfirmationNotification;
use App\Identity\Workspace\Notifications\DataExportReadyNotification;
use Domain\Billing\Notifications\TrialWelcomeNotification;
use Illuminate\Support\Facades\App;

/**
 * Transactional emails name the configured brand (`chatvia.brand.name`), in
 * English and in the translated copies, never a hard-coded product name.
 */
beforeEach(function (): void {
    config(['chatvia.brand.name' => 'Acme Chat']);
});

it('names the brand in the trial welcome email', function (): void {
    $mail = new TrialWelcomeNotification('starter')->toMail(User::factory()->make());

    expect($mail->subject)->toBe('Welcome to Acme Chat - Your Account is Ready!')
        ->and($mail->greeting)->toBe('Welcome to Acme Chat!')
        ->and(end($mail->outroLines))->toBe('Thank you for choosing Acme Chat!');
});

it('names the brand in the translated trial welcome email', function (): void {
    App::setLocale('de');

    $mail = new TrialWelcomeNotification('starter')->toMail(User::factory()->make());

    expect($mail->greeting)->toBe('Willkommen bei Acme Chat!');
});

it('names the brand in the data export and account deletion emails', function (): void {
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

    expect($export->subject)->toBe('Your Acme Chat data export is ready')
        ->and($deletion->introLines[0])->toBe('We received a request to permanently delete your Acme Chat account.');
});
