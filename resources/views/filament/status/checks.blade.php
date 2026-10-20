{{--
    A service card's check lines on the Status page: a mark, what was
    checked, an optional tag and what was found, with an optional note
    under the line (what stops working without a program).

    @var list<\App\Admin\Status\Data\ServiceCheckData> $checks
--}}
<ul class="cv-checks">
    @foreach ($checks as $check)
        <li @class(['cv-check', 'cv-check--'.$check->mark->value, 'cv-check--noted' => $check->note !== null])>
            <x-filament::icon :icon="$check->mark->icon()" class="cv-check__mark" />
            <span class="cv-check__label">{{ $check->label }}</span>

            @if ($check->tag !== null)
                <span class="cv-check__tag">{{ $check->tag }}</span>
            @endif

            <span class="cv-check__value">{{ $check->value }}</span>

            @if ($check->note !== null)
                <span class="cv-check__note">{{ $check->note }}</span>
            @endif
        </li>
    @endforeach
</ul>
