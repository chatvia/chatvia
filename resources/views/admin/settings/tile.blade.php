{{--
    The inside of one Settings tile (see tiles.blade.php). The status is a dot
    in its tone (green: on or set up; amber: needs a look; indigo: changed
    from the defaults; grey: off or nothing set up); pointing at the dot
    shows it in words, and screen readers read it with the tile.

    @var array{key: string, title: string, description: string, icon: mixed, status: ?string, tone: string, href: string} $tile
--}}
<span class="cv-tile__icon {{ \App\Admin\Clusters\Settings\IconTone::cssClass($tile['icon']) }}">
    <x-filament::icon :icon="$tile['icon']" />
</span>

<span class="cv-tile__title">{{ $tile['title'] }}</span>
<span class="cv-tile__description">{{ $tile['description'] }}</span>

@if (filled($tile['status']))
    <span
        class="cv-tile__status cv-tile__status--{{ $tile['tone'] }}"
        x-tooltip="{ content: @js($tile['status']), theme: $store.theme }"
    >
        <span class="fi-sr-only">{{ $tile['status'] }}</span>
    </span>
@endif
