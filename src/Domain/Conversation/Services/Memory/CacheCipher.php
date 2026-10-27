<?php

namespace Domain\Conversation\Services\Memory;

use Domain\Conversation\Models\Message;
use Domain\Knowledge\Ingestion\Models\Chunk;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;

/**
 * Encrypts chat-memory values at rest in the `chat` Redis store.
 *
 * Conversation transcripts, message content, titles, and retrieved sources live
 * in Redis while a conversation is in use. Without this they sit there as
 * plaintext (serialized) — so this mirrors the `encrypted` casts already on the
 * {@see Message} and
 * {@see Chunk} models (same APP_KEY, same AES).
 *
 * Reads never fail on a bad entry: a value that is not an encrypted string
 * (one written to the store without this class) comes back as it is, and one
 * the encryptor can't read gives the default. The chat goes on, and the entry
 * is replaced when it is rewritten or expires.
 */
final class CacheCipher
{
    /** Serialize + encrypt a value for storage. */
    public static function encrypt(mixed $value): string
    {
        return Crypt::encrypt($value);
    }

    /** Decrypt a stored value, tolerating unencrypted and unreadable payloads. */
    public static function decrypt(mixed $stored, mixed $default = null): mixed
    {
        if ($stored === null) {
            return $default;
        }

        // Not an encrypted string: a value written without this class.
        if (! is_scalar($stored)) {
            return $stored;
        }

        try {
            return Crypt::decrypt((string) $stored);
        } catch (DecryptException) {
            return $default;
        }
    }
}
