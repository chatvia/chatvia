<?php

declare(strict_types=1);

namespace Domain\Booking\Services;

use Domain\Agent\Models\Agent;
use Domain\Booking\Actions\EnsureBookingFormAction;
use Domain\Booking\Contracts\Scheduler;
use Domain\Booking\Contracts\Schedulers;
use Domain\Booking\Exceptions\BookingException;
use Domain\Integration\Connector\Services\CapabilityProvider;
use Domain\Integration\Connector\Services\ConnectorCatalog;

/**
 * {@see Schedulers} over Calendly and Cal.com: the Calendar capability's
 * provider for the agent (its owner's pick, or the first connected one) picks
 * the scheduler.
 */
class SchedulerRouter implements Schedulers
{
    public function __construct(
        private readonly CapabilityProvider $providers,
        private readonly ConnectorCatalog $catalog,
        private readonly CalendlyScheduler $calendly,
        private readonly CalcomScheduler $calcom,
    ) {}

    public function providerFor(Agent $agent): string
    {
        $capability = $this->catalog->capabilities()->get(EnsureBookingFormAction::CAPABILITY_SLUG);

        return ($capability !== null ? $this->providers->providerFor($agent, $capability) : null)
            ?? throw BookingException::notConnected();
    }

    public function forAgent(Agent $agent): Scheduler
    {
        return $this->using($this->providerFor($agent));
    }

    public function using(string $provider): Scheduler
    {
        return match ($provider) {
            CalendlyScheduler::CONNECTOR_SLUG => $this->calendly,
            CalcomScheduler::CONNECTOR_SLUG => $this->calcom,
            default => throw BookingException::notConnected(),
        };
    }
}
