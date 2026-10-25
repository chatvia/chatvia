{{--
    The state of an AI or other outside service on the Status page: a check
    mark once it is set up, else the badge ("Off", "Down", "Needs a look",
    "Checking…").

    @var ?\App\Admin\Status\Data\ServiceStatusData $status
--}}
@if ($status?->state === \App\Admin\Status\ServiceState::Running)
    <span class="cv-state-check" title="{{ $status->state->outsideServiceLabel() }}">
        <x-filament::icon icon="heroicon-s-check-circle" aria-hidden="true" />
        <span class="sr-only">{{ $status->state->outsideServiceLabel() }}</span>
    </span>
@else
    @include('filament.status.state', ['status' => $status, 'label' => $status?->state->outsideServiceLabel()])
@endif
