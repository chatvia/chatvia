{{--
    The alert on top of the admin dashboard ({@see \App\Admin\Widgets\ServiceStatusBanner}):
    one line naming the services that are down, with a link to the Status
    page. While nothing is down the widget stays hidden, so it leaves no gap.

    The checks start once the dashboard is open: the services first, then
    the outside services.

    @var list<string> $down
    @var string $statusUrl
--}}
<x-filament-widgets::widget
    x-data
    x-init="$wire.checkServices().then(() => $wire.checkAiServices())"
    :style="$down === [] ? 'display: none' : null"
>
    @if ($down !== [])
        <a href="{{ $statusUrl }}" class="cv-status-alert" role="alert">
            <x-filament::icon icon="heroicon-m-x-circle" class="cv-status-alert__mark" />
            <span class="cv-status-alert__text">
                <strong>{{ trans_choice(':count service is down|:count services are down', count($down)) }}:</strong>
                {{ implode(', ', $down) }}
            </span>
            <span class="cv-status-alert__link">{{ __('View status') }}</span>
        </a>
    @endif
</x-filament-widgets::widget>
