<?php

declare(strict_types=1);

namespace App\Admin\Widgets;

use App\Admin\Pages\Status;
use App\Admin\Status\AiService;
use App\Admin\Status\AiServiceChecks;
use App\Admin\Status\Data\ServiceStatusData;
use App\Admin\Status\Service;
use App\Admin\Status\ServiceChecks;
use App\Admin\Status\ServiceState;
use Filament\Widgets\Widget;
use Illuminate\Support\Carbon;

/**
 * A banner on top of the admin dashboard that says whether every service
 * the Status page checks is fine: "All systems operational", or what is
 * down or needs a look, with a link to the Status page for the details.
 *
 * It opens at once and checks after: the services first, then the outside
 * services, which wait on other servers (the same order as the Status page).
 * It leaves the page filters alone, so changing them does not check again.
 */
class ServiceStatusBanner extends Widget
{
    #[\Override]
    protected static ?int $sort = 0;

    /** It shows "Checking…" on its own, so it needs no lazy placeholder. */
    #[\Override]
    protected static bool $isLazy = false;

    #[\Override]
    protected int|string|array $columnSpan = 'full';

    #[\Override]
    protected string $view = 'filament.status.dashboard-banner';

    /** @var array<string, array<string, mixed>> The checked services, by {@see Service} value. */
    public array $services = [];

    /** @var array<string, array<string, mixed>> The checked outside services, by {@see AiService} value. */
    public array $aiServices = [];

    /** When the services were last checked (ISO 8601). */
    public ?string $checkedAt = null;

    /** Only staff who may open the Status page see what it found. */
    #[\Override]
    public static function canView(): bool
    {
        return Status::canAccess();
    }

    public function checkServices(ServiceChecks $checks): void
    {
        $this->aiServices = [];
        $this->services = collect(Service::cases())
            ->mapWithKeys(fn (Service $service): array => [$service->value => $checks->check($service)->toArray()])
            ->all();
        $this->checkedAt = now()->toIso8601String();
    }

    public function checkAiServices(AiServiceChecks $checks): void
    {
        $this->aiServices = collect(AiService::cases())
            ->mapWithKeys(fn (AiService $service): array => [$service->value => $checks->check($service)->toArray()])
            ->all();
    }

    /**
     * @return array{overall: ?ServiceState, headline: string, subline: string, issues: list<array{0: string, 1: ServiceStatusData}>, statusUrl: string}
     */
    #[\Override]
    protected function getViewData(): array
    {
        $issues = [...$this->issues(Service::cases(), $this->services), ...$this->issues(AiService::cases(), $this->aiServices)];
        $down = count(array_filter($issues, fn (array $issue): bool => $issue[1]->state === ServiceState::Down));
        $warnings = count($issues) - $down;
        $stillChecking = $this->checkedAt === null || $this->aiServices === [];

        [$overall, $headline] = match (true) {
            $down > 0 => [ServiceState::Down, trans_choice(':count service is down|:count services are down', $down)],
            $warnings > 0 => [ServiceState::Warning, trans_choice(':count service needs a look|:count services need a look', $warnings)],
            $stillChecking => [null, __('Checking…')],
            default => [ServiceState::Running, __('All systems operational')],
        };

        return [
            'overall' => $overall,
            'headline' => $headline,
            'subline' => match (true) {
                $this->checkedAt === null => __('Asking each service how it is.'),
                $this->aiServices === [] => __('Still checking the AI services…'),
                default => __('Checked at :time', ['time' => Carbon::parse($this->checkedAt)->timezone((string) config('app.timezone'))->format('H:i:s')]),
            },
            'issues' => $issues,
            'statusUrl' => Status::getUrl(),
        ];
    }

    /**
     * The checked services that are down or need a look, with their label.
     *
     * @param  list<Service|AiService>  $cases
     * @param  array<string, array<string, mixed>>  $results
     * @return list<array{0: string, 1: ServiceStatusData}>
     */
    private function issues(array $cases, array $results): array
    {
        $issues = [];

        foreach ($cases as $service) {
            $status = isset($results[$service->value]) ? ServiceStatusData::from($results[$service->value]) : null;

            if ($status instanceof ServiceStatusData && in_array($status->state, [ServiceState::Down, ServiceState::Warning], true)) {
                $issues[] = [$service->label(), $status];
            }
        }

        return $issues;
    }
}
