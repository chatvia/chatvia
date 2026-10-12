<?php

declare(strict_types=1);

namespace Support\Json;

use RuntimeException;

/**
 * A JSON array written to a file one value at a time, so a large export never
 * holds all its rows in memory. The file is valid JSON once closed: one value
 * per line between the brackets.
 *
 * {@see open()} owns a new file; {@see within()} writes the array into a file
 * the caller already has open (for an array inside a larger JSON object), and
 * leaves that file open.
 */
final class JsonArrayFile
{
    private const int FLAGS = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR;

    private bool $empty = true;

    /**
     * @param  resource  $handle
     */
    private function __construct(
        private readonly mixed $handle,
        private readonly bool $ownsHandle,
    ) {
        $this->write('[');
    }

    public static function open(string $path): self
    {
        $handle = fopen($path, 'wb');

        if ($handle === false) {
            throw new RuntimeException("Unable to open {$path} for writing.");
        }

        return new self($handle, ownsHandle: true);
    }

    /**
     * @param  resource  $handle
     */
    public static function within(mixed $handle): self
    {
        return new self($handle, ownsHandle: false);
    }

    public function push(mixed $value): void
    {
        $this->write(($this->empty ? "\n" : ",\n").json_encode($value, self::FLAGS));

        $this->empty = false;
    }

    public function close(): void
    {
        $this->write($this->empty ? ']' : "\n]");

        if ($this->ownsHandle) {
            fclose($this->handle);
        }
    }

    private function write(string $data): void
    {
        if (fwrite($this->handle, $data) === false) {
            throw new RuntimeException('Unable to write the JSON file.');
        }
    }
}
