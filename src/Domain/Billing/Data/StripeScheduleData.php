<?php

namespace Domain\Billing\Data;

use Spatie\LaravelData\Data;

class StripeScheduleData extends Data
{
    public function __construct(
        public ?string $schedule_id,
        public ?string $pending_change,
        public ?string $from_package,
        public ?string $to_package,
        public ?string $new_price_id,
        public ?string $is_yearly,
        public ?string $effective_date,
        public ?string $requested_at,
        public ?string $canceled_at,
    ) {}

}
