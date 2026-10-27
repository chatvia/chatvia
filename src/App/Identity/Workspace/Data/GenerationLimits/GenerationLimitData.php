<?php

declare(strict_types=1);

namespace App\Identity\Workspace\Data\GenerationLimits;

use Spatie\LaravelData\Data;

/**
 * A workspace's monthly caps on what its agents make: pictures and audio
 * files, and videos. Null leaves that kind without a cap.
 */
class GenerationLimitData extends Data
{
    public function __construct(
        public ?int $monthlyLimit = null,
        public ?int $monthlyVideoLimit = null,
    ) {}

    /** Whether neither kind has a cap. */
    public function isEmpty(): bool
    {
        return $this->monthlyLimit === null && $this->monthlyVideoLimit === null;
    }
}
