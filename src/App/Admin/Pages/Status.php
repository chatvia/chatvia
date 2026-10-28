<?php

declare(strict_types=1);

namespace App\Admin\Pages;

use App\Admin\AdminPanelProvider;
use App\Admin\Settings\Actions\ReloadSavedSettingsAction;
use App\Admin\StaffToolsAccess;
use App\Admin\Status\AiService;
use App\Admin\Status\AiServiceChecks;
use App\Admin\Status\Data\ServiceStatusData;
use App\Admin\Status\EnvironmentReport;
use App\Admin\Status\Service;
use App\Admin\Status\ServiceChecks;
use App\Admin\Status\ServiceState;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;
use UnitEnum;

/**
 * Status: whether the install is up and running, checked live each time the
 * page opens (and on "Check again"): the services it needs ({@see Service}),
 * the AI and other outside services it calls ({@see AiService}, with the
 * sites it reads from, such as models.dev), and its
 * environment, as `php artisan about` tells it ({@see EnvironmentReport}).
 *
 * The page opens at once; the browser then asks for the services (quick),
 * and after them for the outside services, which wait on other servers.
 * Each result is kept as an array between those requests.
 */
class Status extends Page
{
    #[\Override]
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHeart;

    #[\Override]
    protected static string|UnitEnum|null $navigationGroup = AdminPanelProvider::SYSTEM_NAVIGATION_GROUP;

    #[\Override]
    protected static ?int $navigationSort = 98;

    #[\Override]
    protected static ?string $slug = 'status';

    /** @var array<string, array<string, mixed>> The checked services, by {@see Service} value. */
    public array $services = [];

    /** @var array<string, array<string, mixed>> The checked outside services, by {@see AiService} value. */
    public array $aiServices = [];

    /** When the services were last checked (ISO 8601). */
    public ?string $checkedAt = null;

    #[\Override]
    public static function canAccess(): bool
    {
        return app(StaffToolsAccess::class)->allows();
    }

    #[\Override]
    public static function getNavigationLabel(): string
    {
        return __('Status');
    }

    #[\Override]
    public function getTitle(): string
    {
        return __('Status');
    }

    public function getSubheading(): string
    {
        return __('Whether the services this install needs are up and running, checked live.');
    }

    /** Checks the services, and forgets the outside services until they are checked again. */
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

    /** The reload button of Status → Caches → Settings ({@see ReloadSavedSettingsAction}). */
    public function reloadSettings(ReloadSavedSettingsAction $action): void
    {
        $action->execute();

        Notification::make()->success()->title(__('Settings reloaded'))->send();
    }

    #[\Override]
    public function content(Schema $schema): Schema
    {
        return $schema->components([
            View::make('admin.status')->viewData(fn (): array => [
                'services' => $this->serviceResults(fn (Service $service): bool => ! $service->isOutside()),
                'aiServices' => array_map(fn (AiService $service): array => [$service, $this->result($this->aiServices, $service->value)], AiService::cases()),
                'outsideServices' => $this->serviceResults(fn (Service $service): bool => $service->isOutside()),
                'environment' => app(EnvironmentReport::class)->groups(),
                ...$this->summary(),
            ]),
        ]);
    }

    /**
     * The services that pass the filter, each with its result.
     *
     * @param  callable(Service): bool  $filter
     * @return list<array{0: Service, 1: ?ServiceStatusData}>
     */
    private function serviceResults(callable $filter): array
    {
        return collect(Service::cases())
            ->filter($filter)
            ->map(fn (Service $service): array => [$service, $this->result($this->services, $service->value)])
            ->values()
            ->all();
    }

    /**
     * @param  array<string, array<string, mixed>>  $results
     */
    private function result(array $results, string $key): ?ServiceStatusData
    {
        return isset($results[$key]) ? ServiceStatusData::from($results[$key]) : null;
    }

    /**
     * The banner over the page: the worst state found, a headline that
     * counts the services down (or needing a look), and when they were
     * checked.
     *
     * @return array{overall: ?ServiceState, headline: string, subline: string}
     */
    private function summary(): array
    {
        if ($this->services === [] || $this->checkedAt === null) {
            return ['overall' => null, 'headline' => __('Checking…'), 'subline' => __('Asking each service how it is.')];
        }

        $states = array_map(
            fn (array $result): ServiceState => ServiceStatusData::from($result)->state,
            [...array_values($this->services), ...array_values($this->aiServices)],
        );
        $down = count(array_filter($states, fn (ServiceState $state): bool => $state === ServiceState::Down));
        $warnings = count(array_filter($states, fn (ServiceState $state): bool => $state === ServiceState::Warning));

        [$overall, $headline] = match (true) {
            $down > 0 => [ServiceState::Down, trans_choice(':count service is down|:count services are down', $down)],
            $warnings > 0 => [ServiceState::Warning, trans_choice(':count service needs a look|:count services need a look', $warnings)],
            default => [ServiceState::Running, __('Everything is running')],
        };

        $checkedAt = __('Checked at :time', ['time' => Carbon::parse($this->checkedAt)->timezone((string) config('app.timezone'))->format('H:i:s')]);

        return [
            'overall' => $overall,
            'headline' => $headline,
            'subline' => $this->aiServices === [] ? "{$checkedAt} · ".__('Still checking the AI services…') : $checkedAt,
        ];
    }
}
