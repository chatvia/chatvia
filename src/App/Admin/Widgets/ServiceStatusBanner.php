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

/**
 * A one-line alert on top of the admin dashboard when a service the Status
 * page checks is down, with a link to the Status page for the details.
 * Services that only need a look stay on the Status page, so the dashboard
 * is not crowded by setup notes; while all is well, nothing shows.
 *
 * It checks once the dashboard is open: the services first, then the
 * outside services, which wait on other servers (the same order as the
 * Status page). It leaves the page filters alone, so changing them does
 * not check again.
 */
class ServiceStatusBanner extends Widget
{
    #[\Override]
    protected static ?int $sort = 0;

    /** It shows nothing until a check finds a service down, so it needs no lazy placeholder. */
    #[\Override]
    protected static bool $isLazy = false;

    #[\Override]
    protected int|string|array $columnSpan = 'full';

    #[\Override]
    protected string $view = 'admin.status.dashboard-banner';

    /** @var array<string, array<string, mixed>> The checked services, by {@see Service} value. */
    public array $services = [];

    /** @var array<string, array<string, mixed>> The checked outside services, by {@see AiService} value. */
    public array $aiServices = [];

    /** Only staff who may open the Status page see what it found. */
    #[\Override]
    public static function canView(): bool
    {
        return Status::canAccess();
    }

    public function checkServices(ServiceChecks $checks): void
    {
        $this->services = collect(Service::cases())
            ->mapWithKeys(fn (Service $service): array => [$service->value => $checks->check($service)->toArray()])
            ->all();
    }

    public function checkAiServices(AiServiceChecks $checks): void
    {
        $this->aiServices = collect(AiService::cases())
            ->mapWithKeys(fn (AiService $service): array => [$service->value => $checks->check($service)->toArray()])
            ->all();
    }

    /**
     * @return array{down: list<string>, statusUrl: string}
     */
    #[\Override]
    protected function getViewData(): array
    {
        return [
            'down' => [...$this->down(Service::cases(), $this->services), ...$this->down(AiService::cases(), $this->aiServices)],
            'statusUrl' => Status::getUrl(),
        ];
    }

    /**
     * The labels of the checked services that are down.
     *
     * @param  list<Service|AiService>  $cases
     * @param  array<string, array<string, mixed>>  $results
     * @return list<string>
     */
    private function down(array $cases, array $results): array
    {
        return collect($cases)
            ->filter(fn (Service|AiService $service): bool => isset($results[$service->value])
                && ServiceStatusData::from($results[$service->value])->state === ServiceState::Down)
            ->map(fn (Service|AiService $service): string => $service->label())
            ->values()
            ->all();
    }
}
