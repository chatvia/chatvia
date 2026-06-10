<?php

namespace Domain\Conversation\Services\Memory;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;

/**
 * Encrypts chat-memory values at rest in the `chat` Redis store.
 *
 * Conversation transcripts, message content, titles, and retrieved sources live
 * in Redis for the plan's retention window. Without this they sit there as
 * plaintext (serialized) — so this mirrors the `encrypted` casts already on the
 * {@see \Domain\Conversation\Models\Message} and
 * {@see \Domain\Knowledge\Ingestion\Models\Chunk} models (same APP_KEY, same AES).
 *
 * Reads tolerate legacy plaintext entries written before encryption was enabled
 * (a non-string value) and anything the encryptor can't read (returns the
 * default). That makes turning this on a safe rolling change: pre-existing
 * entries keep working and are replaced by encrypted ones as they're rewritten
 * or expire.
 */
final class CacheCipher
{
    /** Serialize + encrypt a value for storage. */
    public static function encrypt(mixed $value): string
    {
        return Crypt::encrypt($value);
    }

    /** Decrypt a stored value, tolerating legacy plaintext and unreadable payloads. */
    public static function decrypt(mixed $stored, mixed $default = null): mixed
    {
        if ($stored === null) {
            return $default;
        }

        // Legacy plaintext entry written before encryption was enabled.
        if (! is_string($stored)) {
            return $stored;
        }

        try {
            return Crypt::decrypt($stored);
        } catch (DecryptException) {
            return $default;
        }
    }
}
