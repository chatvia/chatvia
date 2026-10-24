<?php

declare(strict_types=1);

namespace App\Admin\Settings\Data;

use Spatie\LaravelData\Data;

/**
 * How a model answered a test: it answered (with its reply, when the test
 * shows it), it was busy (the provider is limiting requests: it may answer
 * in a minute), or it failed (the message says why).
 */
final class ModelCheckData extends Data
{
    public const string PASSED = 'passed';

    public const string BUSY = 'busy';

    public const string FAILED = 'failed';

    public function __construct(
        public string $state,
        public ?string $message = null,
    ) {}

    public static function passed(?string $reply = null): self
    {
        return new self(self::PASSED, $reply);
    }

    public static function busy(string $message): self
    {
        return new self(self::BUSY, $message);
    }

    public static function failed(string $message): self
    {
        return new self(self::FAILED, $message);
    }

    public function isPassed(): bool
    {
        return $this->state === self::PASSED;
    }
}
