{{--
    The banner on top of the admin dashboard ({@see \App\Admin\Widgets\ServiceStatusBanner}):
    "All systems operational", or the services that are down or need a
    look, each with what its check found, and a link to the Status page.

    The checks start once the dashboard is open: the services first, then
    the outside services. It reuses the Status page's banner styles.

    @var ?\App\Admin\Status\ServiceState $overall
    @var string $headline
    @var string $subline
    @var list<array{0: string, 1: \App\Admin\Status\Data\ServiceStatusData}> $issues
    @var string $statusUrl
--}}
@php
    $mark = match ($overall) {
        \App\Admin\Status\ServiceState::Running => 'heroicon-o-check-circle',
        \App\Admin\Status\ServiceState::Warning => 'heroicon-o-exclamation-triangle',
        \App\Admin\Status\ServiceState::Down => 'heroicon-o-x-circle',
        default => 'heroicon-o-arrow-path',
    };
@endphp

<x-filament-widgets::widget>
    <div
        class="cv-status"
        x-data
        x-init="$wire.checkServices().then(() => $wire.checkAiServices())"
    >
        <section
            @class(['cv-status-summary', 'cv-status-summary--compact', 'cv-status-summary--'.($overall?->tone() ?? 'checking')])
            aria-live="polite"
        >
            <span class="cv-status-summary__mark">
                <x-filament::icon :icon="$mark" />
            </span>

            <div class="cv-status-summary__body">
                <h2 class="cv-status-summary__title">{{ $headline }}</h2>
                <p class="cv-status-summary__text">{{ $subline }}</p>

                @if ($issues !== [])
                    <ul class="cv-status-issues">
                        @foreach ($issues as [$label, $status])
                            <li class="cv-status-issue" data-state="{{ $status->state->value }}">
                                @include('filament.status.state', ['status' => $status, 'label' => $status->state->label()])
                                <span class="cv-status-issue__name">{{ $label }}</span>
                                <span class="cv-status-issue__summary">{{ trim($status->summary.' '.$status->hint) }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>

            <x-filament::button
                tag="a"
                :href="$statusUrl"
                color="gray"
                icon="heroicon-m-heart"
                class="cv-status-summary__again"
            >
                {{ __('View status') }}
            </x-filament::button>
        </section>
    </div>
</x-filament-widgets::widget>
