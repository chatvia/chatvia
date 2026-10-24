{{--
    One service card of the Status page: its logo or icon, name, state, the check lines that matter most, and a
    dialog with every line and the hint. "Checking…" until it has a result.

    @var \App\Admin\Status\Service $service
    @var ?\App\Admin\Status\Data\ServiceStatusData $status
--}}
<article class="cv-service" data-service="{{ $service->value }}" data-state="{{ $status?->state->value ?? 'checking' }}">
    <div class="cv-service__head">
        @include('filament.status.service-icon', ['service' => $service])

        <div class="cv-service__name">
            <h3 class="cv-service__title">
                @if ($service->url() !== null)
                    <a href="{{ $service->url() }}" target="_blank" rel="noopener noreferrer" class="cv-service__link">{{ $service->label() }}</a>
                @else
                    {{ $service->label() }}
                @endif
            </h3>
            <p class="cv-service__description cv-service__description--line">{{ $service->description() }}</p>
        </div>

        @include('filament.status.state', ['status' => $status, 'label' => $status?->state->label()])
    </div>

    @if ($status === null)
        <span class="cv-skeleton" aria-hidden="true"></span>
    @else
        @if ($status->checks !== [])
            @include('filament.status.checks', ['checks' => $status->cardChecks()])
        @endif

        @if (count($status->checks) > \App\Admin\Status\Data\ServiceStatusData::CARD_CHECKS || $status->hint !== null)
            <x-filament::modal width="md" :heading="$service->label()" :description="$status->summary">
                <x-slot name="trigger">
                    <button type="button" class="cv-checks__more">
                        {{ count($status->checks) > \App\Admin\Status\Data\ServiceStatusData::CARD_CHECKS
                            ? __('Show all :count', ['count' => count($status->checks)])
                            : __('Details') }}
                    </button>
                </x-slot>

                @if ($status->hint !== null)
                    <p @class(['cv-status-hint', 'cv-status-hint--'.$status->state->tone()])>{{ $status->hint }}</p>
                @endif

                @if ($status->checks !== [])
                    @include('filament.status.checks', ['checks' => $status->checks])
                @endif
            </x-filament::modal>
        @endif
    @endif
</article>
