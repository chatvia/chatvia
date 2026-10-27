<?php

declare(strict_types=1);

namespace Domain\Knowledge\Document\Conversion;

/**
 * Reads one PDF object (ISO 32000-1, 7.3) out of a file's bytes: the small
 * part of the syntax {@see PdfEncryption} needs to read the encryption
 * dictionary, the file's ID and the dictionaries of object streams, without
 * a full PDF parser.
 *
 * Values come back as PHP values: a dictionary as an array keyed by the
 * names without their slash, an array as a list, a name as a string with its
 * slash, a string as its raw bytes, a number as int or float, true / false /
 * null, and a reference ("12 0 R") as that text.
 */
final class PdfSyntax
{
    /** Deeper nesting than this is no real encryption dictionary. */
    private const int MAX_DEPTH = 16;

    private const string WHITESPACE = "\x00\t\n\x0C\r ";

    private const string DELIMITERS = '()<>[]{}/%';

    private int $position = 0;

    public function __construct(
        private readonly string $bytes,
    ) {}

    /** The value that starts at (or after whitespace at) the offset. */
    public function valueAt(int $offset): mixed
    {
        $this->position = $offset;

        return $this->value(0);
    }

    /** The offset just after the value {@see valueAt()} read last. */
    public function position(): int
    {
        return $this->position;
    }

    private function value(int $depth): mixed
    {
        $this->skipWhitespace();

        if ($depth > self::MAX_DEPTH || $this->position >= strlen($this->bytes)) {
            return null;
        }

        $char = $this->bytes[$this->position];

        return match (true) {
            str_starts_with(substr($this->bytes, $this->position, 2), '<<') => $this->dictionary($depth),
            $char === '<' => $this->hexString(),
            $char === '(' => $this->literalString(),
            $char === '[' => $this->array($depth),
            $char === '/' => "/{$this->name()}",
            default => $this->keywordOrNumber(),
        };
    }

    /** @return array<string, mixed> */
    private function dictionary(int $depth): array
    {
        $this->position += 2;
        $entries = [];

        while (true) {
            $this->skipWhitespace();

            if ($this->position >= strlen($this->bytes) || str_starts_with(substr($this->bytes, $this->position, 2), '>>')) {
                $this->position += 2;

                return $entries;
            }

            if ($this->bytes[$this->position] !== '/') {
                // Not a key: give up on the rest rather than misread it.
                return $entries;
            }

            $key = $this->name();
            $entries[$key] = $this->value($depth + 1);
        }
    }

    /** @return list<mixed> */
    private function array(int $depth): array
    {
        $this->position++;
        $items = [];

        while (true) {
            $this->skipWhitespace();

            if ($this->position >= strlen($this->bytes) || $this->bytes[$this->position] === ']') {
                $this->position++;

                return $items;
            }

            $before = $this->position;
            $items[] = $this->value($depth + 1);

            if ($this->position === $before) {
                $this->position++;
            }
        }
    }

    private function name(): string
    {
        $this->position++;
        $name = '';

        while ($this->position < strlen($this->bytes) && ! $this->isDelimiterOrWhitespace($this->bytes[$this->position])) {
            $name .= $this->bytes[$this->position++];
        }

        return (string) preg_replace_callback('/#([0-9A-Fa-f]{2})/', fn (array $hex): string => chr((int) hexdec($hex[1])), $name);
    }

    private function hexString(): string
    {
        $end = strpos($this->bytes, '>', $this->position);
        $end = $end === false ? strlen($this->bytes) : $end;
        $digits = (string) preg_replace('/[^0-9A-Fa-f]/', '', substr($this->bytes, $this->position + 1, $end - $this->position - 1));
        $this->position = $end + 1;

        return (string) hex2bin(strlen($digits) % 2 === 1 ? "{$digits}0" : $digits);
    }

    private function literalString(): string
    {
        $this->position++;
        $string = '';
        $open = 1;
        $length = strlen($this->bytes);

        while ($this->position < $length) {
            $char = $this->bytes[$this->position++];

            if ($char === '\\') {
                $string .= $this->escape();

                continue;
            }

            if ($char === '(') {
                $open++;
            } elseif ($char === ')' && --$open === 0) {
                return $string;
            }

            // An end of line inside a string is one line feed, whatever it was.
            if ($char === "\r") {
                if (($this->bytes[$this->position] ?? '') === "\n") {
                    $this->position++;
                }

                $char = "\n";
            }

            $string .= $char;
        }

        return $string;
    }

    /** The bytes after a backslash in a literal string. */
    private function escape(): string
    {
        $char = $this->bytes[$this->position++] ?? '';

        if ($char >= '0' && $char <= '7') {
            $octal = $char;

            while (strlen($octal) < 3 && ($next = $this->bytes[$this->position] ?? '') >= '0' && $next <= '7') {
                $octal .= $next;
                $this->position++;
            }

            return chr((int) octdec($octal) & 0xFF);
        }

        if ($char === "\r" || $char === "\n") {
            // A backslash at the end of a line joins the lines.
            if ($char === "\r" && ($this->bytes[$this->position] ?? '') === "\n") {
                $this->position++;
            }

            return '';
        }

        return match ($char) {
            'n' => "\n",
            'r' => "\r",
            't' => "\t",
            'b' => "\x08",
            'f' => "\x0C",
            default => $char,
        };
    }

    private function keywordOrNumber(): mixed
    {
        $start = $this->position;

        while ($this->position < strlen($this->bytes) && ! $this->isDelimiterOrWhitespace($this->bytes[$this->position])) {
            $this->position++;
        }

        $token = substr($this->bytes, $start, $this->position - $start);

        if (preg_match('/^\d+$/', $token) === 1 && preg_match('/\G\s+(\d+)\s+R(?![^\x00\t\n\x0C\r ()<>\[\]{}\/%])/', $this->bytes, $reference, 0, $this->position) === 1) {
            $this->position += strlen($reference[0]);

            return "{$token} {$reference[1]} R";
        }

        return match (true) {
            $token === 'true' => true,
            $token === 'false' => false,
            $token === 'null' => null,
            is_numeric($token) => str_contains($token, '.') ? (float) $token : (int) $token,
            default => $token,
        };
    }

    private function skipWhitespace(): void
    {
        $length = strlen($this->bytes);

        while ($this->position < $length) {
            $char = $this->bytes[$this->position];

            if ($char === '%') {
                // A comment runs to the end of the line.
                while ($this->position < $length && ! in_array($this->bytes[$this->position], ["\r", "\n"], true)) {
                    $this->position++;
                }

                continue;
            }

            if (! str_contains(self::WHITESPACE, $char)) {
                return;
            }

            $this->position++;
        }
    }

    private function isDelimiterOrWhitespace(string $char): bool
    {
        return str_contains(self::WHITESPACE, $char) || str_contains(self::DELIMITERS, $char);
    }
}
