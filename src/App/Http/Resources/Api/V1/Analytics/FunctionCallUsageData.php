<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Analytics;

use Domain\Analytics\Data\Agent\Usage\FunctionUsageData;
use Spatie\LaravelData\Data;

/**
 * One webhook function the agent called, labelled as it was at call time,
 * with its call count and the last day it was called.
 */
class FunctionCallUsageData extends Data
{
    public function __construct(
        /**
         * The function's name when it was called.
         */
        public ?string $name,
        /**
         * The function's description when it was called.
         */
        public ?string $description,
        /**
         * How many times the agent called it.
         */
        public int $calls,
        /**
         * The last day it was called (`Y-m-d`).
         */
        public ?string $last_used_on,
    ) {}

    public static function fromUsage(FunctionUsageData $function): self
    {
        return new self(
            name: $function->name,
            description: $function->description,
            calls: $function->calls,
            last_used_on: $function->lastUsedOn,
        );
    }
}
