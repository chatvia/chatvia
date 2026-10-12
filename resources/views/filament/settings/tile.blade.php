{{--
    The inside of one Settings tile (see tiles.blade.php).

    @var array{key: string, title: string, description: string, icon: mixed, status: ?string, tone: string, href: string} $tile
--}}
<span class="cv-tile__icon">
    <x-filament::icon :icon="$tile['icon']" />
</span>

<span class="cv-tile__title">{{ $tile['title'] }}</span>
<span class="cv-tile__description">{{ $tile['description'] }}</span>

@if (filled($tile['status']))
    <span class="cv-tile__status cv-tile__status--{{ $tile['tone'] }}">{{ $tile['status'] }}</span>
@endif
