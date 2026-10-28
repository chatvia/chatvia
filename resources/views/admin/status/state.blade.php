{{--
    The state of one check on the Status page, as a badge with a dot in its
    tone; "Checking…" while it has no result yet. The caller picks the label
    (a service is "Running", an outside service "Set up").

    @var ?\App\Admin\Status\Data\ServiceStatusData $status
    @var ?string $label
--}}
@if ($status === null)
    <span class="cv-state cv-state--checking">
        <span class="cv-state__dot" aria-hidden="true"></span>
        {{ __('Checking…') }}
    </span>
@else
    <span class="cv-state cv-state--{{ $status->state->tone() }}">
        <span class="cv-state__dot" aria-hidden="true"></span>
        {{ $label }}
    </span>
@endif
