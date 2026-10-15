<?php

declare(strict_types=1);

namespace App\Admin\Status\Data;

use App\Admin\Status\ServiceState;
use Spatie\LaravelData\Data;

/**
 * What one check on the Status page found: the state, one sentence about
 * it, short facts shown as chips (a version, a time, a count), and the AI
 * providers it runs on, whose logos the page shows.
 */
class ServiceStatusData extends Data
{
    /**
     * @param  list<string>  $facts
     * @param  list<string>  $providers  AiProvider values.
     */
    public function __construct(
        public ServiceState $state,
        public string $summary,
        public array $facts = [],
        public array $providers = [],
    ) {}
}
