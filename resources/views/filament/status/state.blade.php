{{--
    The state of one check on the Status page, as a badge with a dot in its
    tone; "Checking…" while it has no result yet.

    @var ?\App\Admin\Status\Data\ServiceStatusData $status
--}}
@if ($status === null)
    <span class="cv-state cv-state--checking">
        <span class="cv-state__dot" aria-hidden="true"></span>
        {{ __('Checking…') }}
    </span>
@else
    <span class="cv-state cv-state--{{ $status->state->tone() }}">
        <span class="cv-state__dot" aria-hidden="true"></span>
        {{ $status->state->label() }}
    </span>
@endif
