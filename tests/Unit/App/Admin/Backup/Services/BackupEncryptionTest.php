<?php

use App\Admin\Backup\Exceptions\InvalidPublicKeyException;
use App\Admin\Backup\Services\BackupEncryption;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;

/**
 * The backup's public key, checked and used with the real gpg: one usable
 * public key is read and encryption goes to its encryption subkey; private
 * keys, other text, several keys, sign-only and expired keys are refused.
 * The fixtures are throwaway test keys (tests/Fixtures/backup).
 */
beforeEach(function (): void {
    if (! programInstalled('gpg')) {
        $this->markTestSkipped('gpg is not installed.');
    }
});

function beKey(string $name): string
{
    return File::get(base_path("tests/Fixtures/backup/{$name}.asc"));
}

it('refuses a key it cannot encrypt a backup with', function (string $text, string $message): void {
    expect(fn () => app(BackupEncryption::class)->inspect($text))
        ->toThrow(InvalidPublicKeyException::class, $message);
})->with([
    'a private key' => ["-----BEGIN PGP PRIVATE KEY BLOCK-----\n\nxx\n-----END PGP PRIVATE KEY BLOCK-----\n", 'This is a private key.'],
    'other text' => ['ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAA me@example.com', 'This is not an OpenPGP public key.'],
    'a broken key' => ["-----BEGIN PGP PUBLIC KEY BLOCK-----\n\nbroken\n-----END PGP PUBLIC KEY BLOCK-----\n", 'This public key cannot be read.'],
    'two keys' => [fn (): string => beKey('public').beKey('signonly'), 'Paste exactly one public key.'],
    'a key for signing only' => [fn (): string => beKey('signonly'), 'This key can only sign.'],
    'an expired key' => [fn (): string => beKey('expired'), 'This key is expired.'],
]);

it('reads a public key and encrypts a file to its encryption subkey, leaving no keyring behind', function (): void {
    $dir = deleteAfterTest(create_temp_dir('/be-'.Str::random(8)));
    File::put($dir.'/backup.tar', 'the archive');
    $homes = File::glob(sys_get_temp_dir().'/chatvia-gpg-*');

    $key = app(BackupEncryption::class)->inspect(beKey('public'));
    $fingerprint = app(BackupEncryption::class)->encrypt(beKey('public'), $dir.'/backup.tar', $dir.'/backup.tar.gpg');

    expect($key->fingerprint)->toBe('B267A209E6AEC7A0993FA6038E89D0F7F34C5DE7')
        ->and($key->userId)->toBe('ChatVia Backup Test <backup-test@example.com>')
        ->and($key->expiresAt)->toBeNull()
        ->and($fingerprint)->toBe('B267A209E6AEC7A0993FA6038E89D0F7F34C5DE7')
        ->and(File::get($dir.'/backup.tar.gpg'))->not->toContain('the archive')
        ->and(File::glob(sys_get_temp_dir().'/chatvia-gpg-*'))->toBe($homes);

    $home = create_temp_dir('/be-home-'.Str::random(8));
    File::chmod($home, 0700);
    deleteAfterTest($home);
    // Listing the packets needs no private key: the first one names the
    // subkey the file is encrypted to.
    $packets = Process::run(['gpg', '--homedir', $home, '--batch', '--no-autostart', '--list-packets', $dir.'/backup.tar.gpg']);

    expect($packets->output())->toContain('keyid 4A14452F0E6C301D');
});
