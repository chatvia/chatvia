<?php

declare(strict_types=1);

namespace App\Admin\Status\Data;

use App\Admin\Status\CheckMark;
use App\Admin\Status\ServiceState;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;

/**
 * What one check on the Status page found: the state, one short sentence
 * about it, what to do or know beyond that (the hint), and its details. A service card lists them as marked lines (a
 * version, a response time, each extension or program); an AI service row
 * shows short facts as chips and the logos of the providers it runs on.
 */
class ServiceStatusData extends Data
{
    /** How many check lines a card shows; the rest open in a dialog. */
    public const int CARD_CHECKS = 4;

    /**
     * @param  list<string>  $facts
     * @param  list<string>  $providers  AiProvider values.
     * @param  list<ServiceCheckData>  $checks
     */
    public function __construct(
        public ServiceState $state,
        public string $summary,
        public array $facts = [],
        public array $providers = [],
        #[DataCollectionOf(ServiceCheckData::class)]
        public array $checks = [],
        public ?string $hint = null,
    ) {}

    /**
     * The lines the card shows: a failed line first, then one that needs a
     * look, so a problem is never hidden behind "Show all".
     *
     * @return list<ServiceCheckData>
     */
    public function cardChecks(): array
    {
        $rank = fn (ServiceCheckData $check): int => match ($check->mark) {
            CheckMark::Failed => 0,
            CheckMark::Warning => 1,
            default => 2,
        };

        return collect($this->checks)
            ->sortBy($rank)
            ->take(self::CARD_CHECKS)
            ->values()
            ->all();
    }
}
