<?php

declare(strict_types=1);

namespace Tests\Support\Llm;

use Closure;
use Domain\Llm\Contracts\Transcriber;

/**
 * Offline stand-in for a speech-to-text service: it is set up, turns every
 * recording into the same text, and takes a file as it is when the rule it
 * was made with says so (every file by default).
 */
final readonly class FakeTranscriber implements Transcriber
{
    /**
     * @param  (Closure(string, int): bool)|null  $takesAsIs  takes a file of this extension and size as it is; null for every file
     */
    public function __construct(
        private string $text = 'text',
        private ?Closure $takesAsIs = null,
        private string $name = 'fake',
    ) {}

    /** A service that takes only these formats as they are, up to $maxBytes (a non-empty file). */
    public static function takingAsIs(array $extensions, int $maxBytes): self
    {
        return new self(takesAsIs: static fn (string $extension, int $bytes): bool => in_array($extension, $extensions, true) && $bytes > 0 && $bytes <= $maxBytes);
    }

    public function transcribe(string $filePath): string
    {
        return $this->text;
    }

    public function takesAsIs(string $extension, int $bytes): bool
    {
        return ! $this->takesAsIs instanceof Closure || ($this->takesAsIs)($extension, $bytes);
    }

    public function isEnabled(): bool
    {
        return true;
    }

    public function name(): string
    {
        return $this->name;
    }
}
