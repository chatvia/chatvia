<?php

declare(strict_types=1);

namespace App\Admin\Issue\Actions;

use App\Admin\Issue\Enums\IssueStatus;
use App\Admin\Issue\Models\Issue;
use App\Admin\Issue\Services\IssueReporter;
use App\Admin\Status\Data\ServiceStatusData;
use App\Admin\Status\Service;
use App\Admin\Status\ServiceChecks;
use App\Admin\Status\ServiceState;
use Laravel\Horizon\WaitTimeCalculator;
use Support\Radar\IssueRaised;
use Support\Radar\IssueTypeKey;
use Support\Radar\ServiceKey;

/**
 * Radar's health check, for what stops instead of failing: runs the Status
 * page's checks, raises "down" for each part of the install that is down
 * and closes it for each that runs, closes limit issues of an earlier month
 * (the count restarted), and closes "falling behind" for queues whose wait
 * is short again. Everything is saved at once: the queue may be what is down.
 *
 * Without services given it checks them all; the admin panel asks for the
 * scheduler only, which cannot report its own stop.
 */
class CheckInstallHealthAction
{
    /** The Status checks Radar watches, by the Radar service they stand for. */
    private const array SERVICES = [
        ServiceKey::DATABASE => Service::Database,
        ServiceKey::REDIS => Service::Redis,
        ServiceKey::QUEUE => Service::Queue,
        ServiceKey::SCHEDULER => Service::Scheduler,
        ServiceKey::REALTIME => Service::Realtime,
        ServiceKey::STORAGE => Service::Storage,
        ServiceKey::MAIL => Service::Email,
    ];

    public function __construct(
        private readonly ServiceChecks $checks,
        private readonly IssueReporter $reporter,
        private readonly WaitTimeCalculator $waits,
    ) {}

    public function execute(Service ...$only): void
    {
        foreach (self::SERVICES as $key => $service) {
            if ($only === [] || collect($only)->containsStrict($service)) {
                $this->check($key, $service);
            }
        }

        if ($only === []) {
            $this->closeLimitsOfEarlierMonths();
            $this->closeShortWaits();
        }
    }

    private function check(string $key, Service $service): void
    {
        $status = rescue(fn (): ServiceStatusData => $this->checks->check($service), report: false);

        if (! $status instanceof ServiceStatusData) {
            return;
        }

        match ($status->state) {
            ServiceState::Down => $this->reporter->reportNow(new IssueRaised(
                $key,
                IssueTypeKey::SERVICE_STOPPED,
                collect([$status->summary, $status->hint])->filter()->join(' '),
            )),
            ServiceState::Running => $this->reporter->recoveredNow($key, type: IssueTypeKey::SERVICE_STOPPED),
            default => null,
        };
    }

    /** A month's limit issue closes once the month is over: the count restarted. */
    private function closeLimitsOfEarlierMonths(): void
    {
        Issue::query()
            ->where('service', ServiceKey::WORKSPACES)
            ->whereIn('kind', [IssueTypeKey::MESSAGE_LIMIT_REACHED, IssueTypeKey::GENERATION_LIMIT_REACHED])
            ->where('status', IssueStatus::Open)
            ->where('last_seen_at', '<', now()->startOfMonth())
            ->each(fn (Issue $issue) => $this->reporter->recoveredNow($issue->service, $issue->subject, $issue->kind));
    }

    /** A queue that was falling behind closes once its wait is under Horizon's limit again. */
    private function closeShortWaits(): void
    {
        $waits = rescue(fn (): array => $this->waits->calculate(), [], report: false);

        Issue::query()
            ->where('service', ServiceKey::QUEUE)
            ->where('kind', IssueTypeKey::QUEUE_BACKLOG)
            ->where('status', IssueStatus::Open)
            ->whereNotNull('subject')
            ->each(function (Issue $issue) use ($waits): void {
                $limit = (int) config("horizon.waits.{$issue->subject}", 60);

                if (array_key_exists((string) $issue->subject, $waits) && $waits[$issue->subject] < $limit) {
                    $this->reporter->recoveredNow($issue->service, $issue->subject, $issue->kind);
                }
            });
    }
}
