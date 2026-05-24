<?php

namespace App\Security;

use RuntimeException;

final class HMAC
{
    private const string CIPHER = 'aes-256-gcm';

    private const int TAG_LENGTH = 16;

    private string $key;

    public function encrypt(string|array $value): string
    {
        $ivLength = openssl_cipher_iv_length(self::CIPHER);
        $iv = random_bytes($ivLength);

        if (is_array($value)) {
            $value = json_encode($value);
        }

        $ciphertext = openssl_encrypt(
            $value,
            self::CIPHER,
            $this->key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            '',
            self::TAG_LENGTH
        );

        if ($ciphertext === false) {
            throw new RuntimeException('Encryption failed.');
        }

        // iv | tag | ciphertext
        return base64_encode($iv.$tag.$ciphertext);
    }

    /**
     * Decrypt and verify integrity
     */
    public function decrypt(string $payload): string
    {
        //        $data = base64_decode($payload, true);

        //        if ($data === false) {
        //            throw new RuntimeException('Invalid payload encoding.');
        //        }

        $data = $payload;

        $ivLength = openssl_cipher_iv_length(self::CIPHER);

        $iv = substr($data, 0, $ivLength);
        $tag = substr($data, $ivLength, self::TAG_LENGTH);
        $ciphertext = substr($data, $ivLength + self::TAG_LENGTH);

        $plaintext = openssl_decrypt(
            $ciphertext,
            self::CIPHER,
            $this->key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );

        if ($plaintext === false) {
            throw new RuntimeException('Decryption failed or data was tampered with.');
        }

        return $plaintext;
    }

    public function setKey(string $key): static
    {
        $this->key = $key;

        return $this;
    }
}
