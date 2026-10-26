<?php

declare(strict_types=1);

use Domain\Agent\Models\Agent;
use Domain\Booking\Exceptions\BookingException;
use Domain\Booking\Services\CalcomScheduler;
use Domain\Booking\Services\CalendlyScheduler;
use Domain\Booking\Services\SchedulerRouter;
use Domain\Integration\Connector\Services\CapabilityProvider;
use Domain\Integration\Mcp\Models\AgentMcpServer;

/**
 * The router hands out the scheduler the agent books through, or the one a
 * calendar was shown from by its slug.
 */
it('picks the scheduler the agent books through', function (): void {
    $agent = Agent::factory()->create();
    AgentMcpServer::factory()->create(['workspace_id' => $agent->workspace_id, 'connector_slug' => 'cal-com', 'slug' => 'cal-com', 'is_enabled' => true]);

    expect(app(SchedulerRouter::class)->providerFor($agent))->toBe('cal-com')
        ->and(app(SchedulerRouter::class)->forAgent($agent))->toBeInstanceOf(CalcomScheduler::class);
});

it('follows the agent\'s pick', function (): void {
    $agent = Agent::factory()->create();
    foreach (['calendly', 'cal-com'] as $slug) {
        AgentMcpServer::factory()->create(['workspace_id' => $agent->workspace_id, 'connector_slug' => $slug, 'slug' => $slug, 'is_enabled' => true]);
    }

    expect(app(SchedulerRouter::class)->forAgent($agent))->toBeInstanceOf(CalendlyScheduler::class);

    $agent->setSetting(key: 'calendar', value: 'cal-com', group: CapabilityProvider::SETTINGS_GROUP);

    expect(app(SchedulerRouter::class)->forAgent($agent->fresh()))->toBeInstanceOf(CalcomScheduler::class);
});

it('looks a scheduler up by its slug', function (): void {
    expect(app(SchedulerRouter::class)->using('calendly'))->toBeInstanceOf(CalendlyScheduler::class)
        ->and(app(SchedulerRouter::class)->using('cal-com'))->toBeInstanceOf(CalcomScheduler::class);
});

it('has no scheduler when the workspace connected none', function (): void {
    app(SchedulerRouter::class)->providerFor(Agent::factory()->create());
})->throws(BookingException::class);

it('has no scheduler for an unknown slug', function (): void {
    app(SchedulerRouter::class)->using('google-calendar');
})->throws(BookingException::class);
