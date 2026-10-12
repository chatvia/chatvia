{{--
    The tiles of the admin panel's Settings overview: an icon, a status badge,
    a title and what the page holds. Each tile links to a Settings page.
    Styled by the panel theme (resources/css/filament/admin/theme.css), so it
    follows dark mode.

    @var list<array{heading: ?string, tiles: list<array{key: string, title: string, description: string, icon: mixed, status: ?string, tone: string, href: string}>}> $groups
--}}
<div class="cv-settings">
    @foreach ($groups as $group)
        <section class="cv-settings__group">
            @if (filled($group['heading']))
                <h2 class="cv-settings__heading">{{ $group['heading'] }}</h2>
            @endif

            <div class="cv-settings__grid">
                @foreach ($group['tiles'] as $tile)
                    <a href="{{ $tile['href'] }}" class="cv-tile" data-tile="{{ $tile['key'] }}">
                        @include('filament.settings.tile', ['tile' => $tile])
                    </a>
                @endforeach
            </div>
        </section>
    @endforeach
</div>
