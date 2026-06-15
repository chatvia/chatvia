<?php

namespace Support\Casts;

use Domain\Conversation\Services\Memory\CacheCipher;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

/**
 * Like Laravel's built-in `encrypted` cast, but tolerant of legacy PLAINTEXT on
 * read.
 *
 * When encryption is added to a column that already has data, the pre-existing
 * rows are still plaintext. The stock `encrypted` cast calls `Crypt::decrypt`,
 * which throws `DecryptException` ("payload is invalid") and 500s the request.
 * This cast catches that and returns the raw value instead, so pre-encryption
 * rows keep reading until they're next written (which encrypts them). New writes
 * are always encrypted, so the data converges to ciphertext over time without a
 * backfill migration. Mirrors the same tolerance in
 * {@see CacheCipher} for the Redis layer.
 *
 * Uses encryptString/decryptString (no serialization) — for string columns,
 * format-compatible with the stock `encrypted` cast.
 *
 * @implements CastsAttributes<string|null, string|null>
 */
class EncryptedTolerant implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        return self::decrypt($value);
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        return $value === null ? null : Crypt::encryptString((string) $value);
    }

    /**
     * Decrypt a raw column value the same way the cast does on read, tolerant of
     * legacy plaintext. Use this when a value bypasses Eloquent hydration — e.g.
     * a raw query-builder join that selects an encrypted column directly, where
     * the cast never runs and the attribute would otherwise stay ciphertext.
     */
    public static function decrypt(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        try {
            return Crypt::decryptString((string) $value);
        } catch (DecryptException) {
            // Legacy plaintext written before this column was encrypted.
            return is_string($value) ? $value : null;
        }
    }
}
