<?php

declare(strict_types=1);

namespace App\Admin\Backup\Services;

use App\Admin\Backup\Data\PublicKeyData;
use App\Admin\Backup\Exceptions\BackupException;
use App\Admin\Backup\Exceptions\InvalidPublicKeyException;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Contracts\Process\ProcessResult;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;

/**
 * Encrypts a backup to the admin's OpenPGP public key with `gpg`. The server
 * only ever holds the public key, so it can encrypt but never decrypt: a
 * stolen server or bucket cannot read the backups.
 *
 * Every call uses a keyring of its own in a temp folder, deleted afterwards,
 * so nothing of the key stays in gpg's files and the trust settings of the
 * server's own keyring play no part. `--no-autostart` keeps gpg from starting
 * an agent: a public key needs none.
 */
class BackupEncryption
{
    /** gpg's encryption of a large archive can take long. */
    private const int TIMEOUT_SECONDS = 3600;

    /**
     * What the key says about itself; throws when it cannot encrypt a backup.
     *
     * @throws InvalidPublicKeyException
     */
    public function inspect(string $publicKey): PublicKeyData
    {
        if (Str::contains($publicKey, 'PRIVATE KEY BLOCK')) {
            throw new InvalidPublicKeyException(__('This is a private key. Paste the public key: the server must never hold the key that opens the backups.'));
        }

        if (! Str::contains($publicKey, '-----BEGIN PGP PUBLIC KEY BLOCK-----')) {
            throw new InvalidPublicKeyException(__('This is not an OpenPGP public key. Export it with: gpg --armor --export you@example.com'));
        }

        return $this->withHome(function (string $home) use ($publicKey): PublicKeyData {
            File::put("{$home}/key.asc", $publicKey);

            // show-only lists the key without adding it to the keyring.
            $result = $this->gpg($home, ['--with-colons', '--import-options', 'show-only', '--import', "{$home}/key.asc"]);

            if (! $result->successful()) {
                throw new InvalidPublicKeyException(__('This public key cannot be read. Export it again with: gpg --armor --export you@example.com'));
            }

            return $this->keyFrom($result->output());
        });
    }

    /**
     * Encrypts $source to the key as $target; returns the key's fingerprint.
     *
     * @throws InvalidPublicKeyException|BackupException
     */
    public function encrypt(string $publicKey, string $source, string $target): string
    {
        $fingerprint = $this->inspect($publicKey)->fingerprint;

        $this->withHome(function (string $home) use ($publicKey, $fingerprint, $source, $target): void {
            File::put("{$home}/key.asc", $publicKey);

            $import = $this->gpg($home, ['--import', "{$home}/key.asc"]);

            if (! $import->successful()) {
                throw new BackupException('gpg could not import the public key: '.Str::trim($import->errorOutput()));
            }

            // The keyring holds only this key, which the admin chose, so it
            // is trusted as it is.
            $encrypt = $this->gpg($home, ['--trust-model', 'always', '--yes', '--recipient', $fingerprint, '--output', $target, '--encrypt', $source]);

            if (! $encrypt->successful()) {
                throw new BackupException('gpg could not encrypt the backup: '.Str::trim($encrypt->errorOutput()));
            }
        });

        return $fingerprint;
    }

    /**
     * The key in gpg's colon listing (one `pub` line, then its `fpr` and
     * `uid` lines). Its capital E says the key as a whole can encrypt now:
     * gpg leaves it out when the key, or its last encryption subkey, is
     * expired or revoked.
     */
    private function keyFrom(string $listing): PublicKeyData
    {
        $records = Str::of($listing)->explode("\n")
            ->map(fn (string $line): array => Str::of($line)->explode(':')->all())
            ->filter(fn (array $fields): bool => count($fields) > 9)
            ->values();

        if ($records->contains(fn (array $fields): bool => collect(['sec', 'ssb'])->containsStrict($fields[0]))) {
            throw new InvalidPublicKeyException(__('This is a private key. Paste the public key: the server must never hold the key that opens the backups.'));
        }

        $keys = $records->filter(fn (array $fields): bool => $fields[0] === 'pub')->values();

        if ($keys->count() !== 1) {
            throw new InvalidPublicKeyException(__('Paste exactly one public key.'));
        }

        $pub = $keys->first();

        if ($pub[1] === 'r') {
            throw new InvalidPublicKeyException(__('This key is revoked. Make a new key and paste its public key.'));
        }

        if ($pub[1] === 'e') {
            throw new InvalidPublicKeyException(__('This key is expired. Extend it (gpg --quick-set-expire) and paste the public key again.'));
        }

        if (! Str::contains($pub[11], 'E')) {
            throw new InvalidPublicKeyException(__('This key can only sign. Add an encryption subkey to it, or paste another key.'));
        }

        $fingerprint = $this->field($records->first(fn (array $fields): bool => $fields[0] === 'fpr'), 9);
        $userId = $this->field($records->first(fn (array $fields): bool => $fields[0] === 'uid'), 9);

        if (blank($fingerprint)) {
            throw new InvalidPublicKeyException(__('This public key cannot be read. Export it again with: gpg --armor --export you@example.com'));
        }

        return new PublicKeyData(
            fingerprint: $fingerprint,
            // gpg escapes a colon in a name as \x3a.
            userId: filled($userId) ? Str::replace('\x3a', ':', $userId) : null,
            expiresAt: filled($pub[6]) ? CarbonImmutable::createFromTimestampUTC((int) $pub[6]) : null,
        );
    }

    /**
     * @param  list<string>|null  $record
     */
    private function field(?array $record, int $index): string
    {
        return $record[$index] ?? '';
    }

    /**
     * @template T
     *
     * @param  Closure(string): T  $use
     * @return T
     */
    private function withHome(Closure $use): mixed
    {
        $home = create_temp_dir('/chatvia-gpg-'.Str::lower(Str::random(12)));
        // gpg refuses a home other people can read.
        File::chmod($home, 0700);

        try {
            return $use($home);
        } finally {
            File::deleteDirectory($home);
        }
    }

    /**
     * @param  list<string>  $arguments
     */
    private function gpg(string $home, array $arguments): ProcessResult
    {
        return Process::timeout(self::TIMEOUT_SECONDS)->run([
            (string) config('services.bin.gpg'),
            '--homedir', $home,
            '--batch',
            '--no-tty',
            '--no-autostart',
            '--quiet',
            ...$arguments,
        ]);
    }
}
