{{--
    The admin panel's Tools page: a card per staff tool, centered, with its
    logo, what it looks after and what it is for. A tool the admin can open
    has a button that opens it in a new tab; any other says why it cannot,
    with no link. Styled by the panel theme
    (resources/css/admin/theme.css), so it follows dark mode.

    @var list<\App\Admin\StaffTool> $tools
--}}
<div class="cv-tools">
    @foreach ($tools as $tool)
        @php($unavailable = $tool->unavailableReason())

        <article @class(['cv-tool', 'cv-tool--unavailable' => $unavailable !== null]) data-tool="{{ $tool->value }}">
            <span class="cv-tool__icon">
                <img src="{{ $tool->logo() }}" alt="" class="cv-tool__logo" />
            </span>

            <h2 class="cv-tool__title">{{ $tool->label() }}</h2>
            <p class="cv-tool__tagline">{{ $tool->tagline() }}</p>
            <p class="cv-tool__description">{{ $tool->description() }}</p>

            <div class="cv-tool__action">
                @if ($unavailable === null)
                    <x-filament::button
                        tag="a"
                        :href="$tool->url()"
                        target="_blank"
                        rel="noopener"
                        color="gray"
                        icon="heroicon-m-arrow-top-right-on-square"
                        icon-position="after"
                    >
                        {{ __('Open :tool', ['tool' => $tool->label()]) }}
                    </x-filament::button>
                @else
                    <x-filament::badge color="gray" icon="heroicon-m-no-symbol">
                        {{ $unavailable }}
                    </x-filament::badge>
                @endif
            </div>
        </article>
    @endforeach
</div>
