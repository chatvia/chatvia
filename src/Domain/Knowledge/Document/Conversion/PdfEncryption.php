<?php

declare(strict_types=1);

namespace Domain\Knowledge\Document\Conversion;

use Illuminate\Support\Facades\File;
use RuntimeException;

/**
 * Tells whether a PDF needs a password to open, the way poppler's pdfinfo
 * does: an encrypted PDF whose user password is empty opens without one (it
 * only limits printing or copying), so it is not locked. Used when pdfinfo
 * is not installed ({@see BuiltinPdfInspector}).
 *
 * It reads the encryption dictionary straight from the file's bytes. The PDF
 * spec never lets that dictionary sit in an object stream, so it is readable
 * even when the rest of the file is compressed or encrypted. The empty
 * password is checked with the spec's algorithms for the Standard security
 * handler: revisions 2–4 (MD5 and RC4, ISO 32000-1 algorithms 2, 4 and 5),
 * 5 (SHA-256) and 6 (ISO 32000-2 algorithm 2.B). RC4 is done in PHP because
 * OpenSSL 3 leaves it out by default. Any other handler needs a certificate,
 * so the file counts as locked.
 *
 * When the empty password opens the file, its key also opens the object
 * streams ({@see dictionaries()}), where modern PDFs keep their page tree.
 */
final class PdfEncryption
{
    /** The 32 bytes a password is padded with (ISO 32000-1, 7.6.3.3). */
    private const string PADDING = "\x28\xBF\x4E\x5E\x4E\x75\x8A\x41\x64\x00\x4E\x56\xFF\xFA\x01\x08"
        ."\x2E\x2E\x00\xB6\xD0\x68\x3E\x80\x2F\x0C\xA9\xFE\x64\x53\x69\x7A";

    /** Largest object stream inflated, a guard against zip bombs. */
    private const int MAX_STREAM_BYTES = 64 * 1024 * 1024;

    /** Whether the PDF has an encryption dictionary at all. */
    public function isEncrypted(string $path): bool
    {
        return $this->encryptionDictionary($this->bytes($path)) !== null;
    }

    /** Whether the PDF cannot be opened without a password. */
    public function needsPassword(string $path): bool
    {
        $bytes = $this->bytes($path);
        $encrypt = $this->encryptionDictionary($bytes);

        return $encrypt !== null && $this->fileKey($encrypt, $bytes) === null;
    }

    /**
     * Every dictionary object in the file, from every revision: those written
     * out in the file, and those inside object streams, which are decrypted
     * when the file opens without a password (otherwise they are left out, as
     * are object streams in other filters than Flate).
     *
     * @return list<array<string, mixed>>
     */
    public function dictionaries(string $path): array
    {
        $bytes = $this->bytes($path);

        if (preg_match_all('#(?<![0-9])(\d+)\s+(\d+)\s+obj\s*(?=<<)#', $bytes, $objects, PREG_SET_ORDER | PREG_OFFSET_CAPTURE) < 1) {
            return [];
        }

        $encrypt = $this->encryptionDictionary($bytes);
        $key = $encrypt === null ? '' : $this->fileKey($encrypt, $bytes);
        $cipher = $encrypt === null ? 'none' : $this->streamCipher($encrypt);
        $dictionaries = [];

        foreach ($objects as [$header, $number, $generation]) {
            $syntax = new PdfSyntax($bytes);
            $dictionary = $syntax->valueAt($header[1] + strlen($header[0]));

            if (! is_array($dictionary) || array_is_list($dictionary)) {
                continue;
            }

            $dictionaries[] = $dictionary;

            if (($dictionary['Type'] ?? null) !== '/ObjStm' || $key === null) {
                continue;
            }

            $data = $this->streamData($bytes, $syntax->position(), $dictionary['Length'] ?? null);
            $content = $data === null ? null : $this->inflate(
                $this->decrypt($data, $cipher, $key, (int) $number[0], (int) $generation[0]),
                $dictionary['Filter'] ?? null,
            );

            if ($content !== null) {
                array_push($dictionaries, ...$this->objectStreamDictionaries($content, (int) ($dictionary['First'] ?? 0)));
            }
        }

        return $dictionaries;
    }

    /**
     * The dictionaries in an object stream's content: a header of object
     * number and offset pairs, then the objects from byte `First` on.
     *
     * @return list<array<string, mixed>>
     */
    private function objectStreamDictionaries(string $content, int $first): array
    {
        preg_match_all('/(\d+)\s+(\d+)/', substr($content, 0, $first), $pairs, PREG_SET_ORDER);

        $syntax = new PdfSyntax($content);
        $dictionaries = [];

        foreach ($pairs as [, , $offset]) {
            $value = $syntax->valueAt($first + (int) $offset);

            if (is_array($value) && ! array_is_list($value)) {
                $dictionaries[] = $value;
            }
        }

        return $dictionaries;
    }

    private function bytes(string $path): string
    {
        if (! File::isReadable($path)) {
            throw new RuntimeException("PDF file is not readable: {$path}");
        }

        return (string) file_get_contents($path);
    }

    /**
     * The file's key when the empty user password opens it, else null.
     *
     * @param  array<string, mixed>  $encrypt
     */
    private function fileKey(array $encrypt, string $bytes): ?string
    {
        if (($encrypt['Filter'] ?? null) !== '/Standard') {
            return null;
        }

        $revision = (int) ($encrypt['R'] ?? 0);
        $user = is_string($encrypt['U'] ?? null) ? $encrypt['U'] : '';

        if ($revision >= 2 && $revision <= 4) {
            return $this->rc4FileKey($encrypt, $revision, $user, $this->firstId($bytes));
        }

        if (($revision !== 5 && $revision !== 6) || strlen($user) < 48) {
            return null;
        }

        $hash = fn (string $salt): string => $revision === 5 ? hash('sha256', $salt, true) : $this->hash2B($salt);

        if (! hash_equals(substr($user, 0, 32), $hash(substr($user, 32, 8)))) {
            return null;
        }

        // The file key is wrapped in /UE with a key made from the key salt.
        $wrapped = is_string($encrypt['UE'] ?? null) ? $encrypt['UE'] : '';
        $key = openssl_decrypt($wrapped, 'aes-256-cbc', $hash(substr($user, 40, 8)), OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING, str_repeat("\0", 16));

        return is_string($key) && strlen($key) === 32 ? $key : null;
    }

    /**
     * Revisions 2–4: the file key from the empty password (algorithm 2),
     * kept when the user entry it gives matches (algorithm 4 for revision 2,
     * 5 for 3 and 4).
     *
     * @param  array<string, mixed>  $encrypt
     */
    private function rc4FileKey(array $encrypt, int $revision, string $user, string $id): ?string
    {
        $keyLength = match (true) {
            $revision === 2 => 5,
            (int) ($encrypt['V'] ?? 0) >= 4 => 16,
            default => max(5, min(16, intdiv((int) ($encrypt['Length'] ?? 40), 8))),
        };

        $owner = is_string($encrypt['O'] ?? null) ? $encrypt['O'] : '';
        $input = self::PADDING.substr($owner, 0, 32).pack('V', (int) ($encrypt['P'] ?? 0) & 0xFFFFFFFF).$id;

        if ($revision >= 4 && ($encrypt['EncryptMetadata'] ?? true) === false) {
            $input .= "\xFF\xFF\xFF\xFF";
        }

        $hash = md5($input, true);

        if ($revision >= 3) {
            for ($round = 0; $round < 50; $round++) {
                $hash = md5(substr($hash, 0, $keyLength), true);
            }
        }

        $key = substr($hash, 0, $keyLength);

        if ($revision === 2) {
            return hash_equals(substr($user, 0, 32), $this->rc4($key, self::PADDING)) ? $key : null;
        }

        $value = $this->rc4($key, md5(self::PADDING.$id, true));

        for ($round = 1; $round <= 19; $round++) {
            $value = $this->rc4($key ^ str_repeat(chr($round), $keyLength), $value);
        }

        return strlen($user) >= 16 && hash_equals(substr($user, 0, 16), $value) ? $key : null;
    }

    /** ISO 32000-2 algorithm 2.B for the empty password and no user key data. */
    private function hash2B(string $salt): string
    {
        $key = hash('sha256', $salt, true);
        $last = 0;

        for ($round = 0; $round < 64 || $last > $round - 32; $round++) {
            $encrypted = (string) openssl_encrypt(
                str_repeat($key, 64),
                'aes-128-cbc',
                substr($key, 0, 16),
                OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING,
                substr($key, 16, 16),
            );

            // The first 16 bytes as one big number, mod 3: 256 is 1 mod 3,
            // so the sum of the bytes gives the same remainder.
            $remainder = array_sum(array_map(ord(...), str_split(substr($encrypted, 0, 16)))) % 3;
            $key = hash(['sha256', 'sha384', 'sha512'][$remainder], $encrypted, true);
            $last = ord($encrypted[strlen($encrypted) - 1]);
        }

        return substr($key, 0, 32);
    }

    /**
     * How streams are encrypted: 'rc4', 'aes128', 'aes256', or 'none' for
     * the Identity crypt filter.
     *
     * @param  array<string, mixed>  $encrypt
     */
    private function streamCipher(array $encrypt): string
    {
        $version = (int) ($encrypt['V'] ?? 0);

        if ($version < 4) {
            return 'rc4';
        }

        $filter = ltrim(is_string($encrypt['StmF'] ?? null) ? $encrypt['StmF'] : '/Identity', '/');
        $filters = is_array($encrypt['CF'] ?? null) ? $encrypt['CF'] : [];
        $method = is_array($filters[$filter] ?? null) ? ($filters[$filter]['CFM'] ?? null) : null;

        return match (true) {
            $filter === 'Identity', $method === '/None' => 'none',
            $method === '/V2' => 'rc4',
            $method === '/AESV2' => 'aes128',
            default => 'aes256',
        };
    }

    /** The raw bytes of the stream whose dictionary ends at the offset, or null. */
    private function streamData(string $bytes, int $offset, mixed $length): ?string
    {
        if (preg_match('#\G\s*stream(?:\r\n|\n|\r)#', $bytes, $keyword, 0, $offset) !== 1) {
            return null;
        }

        // The length may be an indirect object, written after the stream.
        if (is_string($length) && preg_match('#^(\d+) (\d+) R$#', $length, $reference) === 1) {
            $length = preg_match_all("#(?<![0-9]){$reference[1]}\s+{$reference[2]}\s+obj\s*(\d+)#", $bytes, $values) > 0 ? (int) end($values[1]) : null;
        }

        return is_int($length) && $length > 0 ? substr($bytes, $offset + strlen($keyword[0]), $length) : null;
    }

    private function decrypt(string $data, string $cipher, string $key, int $number, int $generation): string
    {
        if ($cipher === 'none' || $key === '') {
            return $data;
        }

        if ($cipher === 'aes256') {
            return (string) openssl_decrypt(substr($data, 16), 'aes-256-cbc', $key, OPENSSL_RAW_DATA, substr($data, 0, 16));
        }

        // Revisions 2–4 give each object its own key (algorithm 1).
        $objectKey = substr(md5(
            $key.substr(pack('V', $number), 0, 3).substr(pack('V', $generation), 0, 2).($cipher === 'aes128' ? 'sAlT' : ''),
            true,
        ), 0, min(strlen($key) + 5, 16));

        return $cipher === 'aes128'
            ? (string) openssl_decrypt(substr($data, 16), 'aes-128-cbc', $objectKey, OPENSSL_RAW_DATA, substr($data, 0, 16))
            : $this->rc4($objectKey, $data);
    }

    private function inflate(string $data, mixed $filter): ?string
    {
        $filters = is_array($filter) ? $filter : [$filter];

        if ($filters === [null] || $filters === []) {
            return $data;
        }

        if ($filters !== ['/FlateDecode']) {
            return null;
        }

        $inflated = @gzuncompress($data, self::MAX_STREAM_BYTES);

        return is_string($inflated) ? $inflated : null;
    }

    private function rc4(string $key, string $data): string
    {
        $state = range(0, 255);
        $length = strlen($key);

        for ($i = 0, $j = 0; $i < 256; $i++) {
            $j = ($j + $state[$i] + ord($key[$i % $length])) % 256;
            [$state[$i], $state[$j]] = [$state[$j], $state[$i]];
        }

        $output = '';

        for ($n = 0, $i = 0, $j = 0, $size = strlen($data); $n < $size; $n++) {
            $i = ($i + 1) % 256;
            $j = ($j + $state[$i]) % 256;
            [$state[$i], $state[$j]] = [$state[$j], $state[$i]];
            $output .= chr(ord($data[$n]) ^ $state[($state[$i] + $state[$j]) % 256]);
        }

        return $output;
    }

    /**
     * The encryption dictionary of the newest revision of the file, or null
     * when it is not encrypted. The trailer (or cross-reference stream) of
     * the last update names it, as `/Encrypt 12 0 R` or inline.
     *
     * @return array<string, mixed>|null
     */
    private function encryptionDictionary(string $bytes): ?array
    {
        if (preg_match_all('#/Encrypt\s*(\d+\s+\d+\s+R|<<)#', $bytes, $matches, PREG_OFFSET_CAPTURE) < 1) {
            return null;
        }

        [$value, $offset] = end($matches[1]);

        if ($value === '<<') {
            $position = $offset;
        } else {
            [$number, $generation] = array_map(intval(...), preg_split('/\s+/', trim($value)) ?: []);

            if (preg_match_all("#(?<![0-9]){$number}\s+{$generation}\s+obj\b#", $bytes, $objects, PREG_OFFSET_CAPTURE) < 1) {
                return null;
            }

            [$header, $start] = end($objects[0]);
            $position = $start + strlen($header);
        }

        $dictionary = new PdfSyntax($bytes)->valueAt($position);

        return is_array($dictionary) && ! array_is_list($dictionary) ? $dictionary : null;
    }

    /** The first part of the file's `/ID`, which the key of revisions 2–4 mixes in. */
    private function firstId(string $bytes): string
    {
        if (preg_match_all('#/ID\s*\[#', $bytes, $matches, PREG_OFFSET_CAPTURE) < 1) {
            return '';
        }

        [$match, $offset] = end($matches[0]);
        $ids = new PdfSyntax($bytes)->valueAt($offset + strlen($match) - 1);

        return is_array($ids) && isset($ids[0]) && is_string($ids[0]) ? $ids[0] : '';
    }
}
