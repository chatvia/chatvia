<?php

declare(strict_types=1);

namespace Domain\Booking\Contracts;

use Domain\Agent\Models\Agent;
use Domain\Booking\Exceptions\BookingException;

/**
 * The schedulers the Calendar capability can book through, by connector slug
 * (`calendly`, `cal-com`). An agent uses the one its owner picked, or the
 * first connected one; a calendar already shown keeps the one it was shown
 * with, so it is looked up by slug.
 */
interface Schedulers
{
    /**
     * The connector slug of the scheduler this agent books through now.
     *
     * @throws BookingException when the workspace has connected none
     */
    public function providerFor(Agent $agent): string;

    /**
     * @throws BookingException when the workspace has connected none
     */
    public function forAgent(Agent $agent): Scheduler;

    /**
     * @throws BookingException for a slug that is no scheduler
     */
    public function using(string $provider): Scheduler;
}
