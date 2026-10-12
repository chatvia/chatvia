{{--
    The tiles of the admin panel's Settings overview: a centred icon, a title,
    what the page holds and a status badge. Each tile links to a Settings
    page. Styled with the panel's own colour variables, so it needs no theme
    build and follows dark mode.

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

<style>
    .cv-settings { display: grid; gap: 2.5rem; }
    .cv-settings__heading { margin: 0 0 1rem; font-size: 1.0625rem; font-weight: 500; color: var(--gray-700); }
    .cv-settings__grid { display: grid; gap: 1.25rem; grid-template-columns: repeat(auto-fill, minmax(17rem, 1fr)); }

    .cv-tile { display: flex; flex-direction: column; align-items: center; padding: 2rem 1.5rem 1.75rem; border: 1px solid var(--gray-200); border-radius: 1rem; background: #fff; color: inherit; text-align: center; text-decoration: none; box-shadow: 0 1px 2px rgb(0 0 0 / .05); transition: border-color .15s ease, box-shadow .15s ease, transform .15s ease; }
    .cv-tile:hover { border-color: var(--gray-300); box-shadow: 0 10px 24px -12px rgb(0 0 0 / .18); transform: translateY(-2px); }
    .cv-tile:focus-visible { outline: 2px solid var(--primary-500); outline-offset: 2px; }

    .cv-tile__icon { display: grid; place-items: center; color: var(--gray-500); transition: color .15s ease; }
    .cv-tile__icon svg { width: 2.75rem; height: 2.75rem; }
    .cv-tile:hover .cv-tile__icon { color: var(--primary-600); }

    .cv-tile__title { margin-top: 1.125rem; font-size: 1rem; font-weight: 600; line-height: 1.35; color: var(--gray-950); }
    .cv-tile__description { max-width: 22rem; margin: .5rem 0 1rem; font-size: .875rem; line-height: 1.5; color: var(--gray-600); text-wrap: pretty; }

    /* Pushed to the bottom, so the badges of a row line up. */
    .cv-tile__status { margin-top: auto; padding: .125rem .5rem; border: 1px solid var(--gray-200); border-radius: .375rem; background: var(--gray-50); font-size: .75rem; font-weight: 500; line-height: 1.5; color: var(--gray-600); white-space: nowrap; }
    .cv-tile__status--success { border-color: color-mix(in oklab, var(--success-600) 25%, transparent); background: var(--success-50); color: var(--success-700); }
    .cv-tile__status--warning { border-color: color-mix(in oklab, var(--warning-600) 25%, transparent); background: var(--warning-50); color: var(--warning-700); }
    .cv-tile__status--primary { border-color: color-mix(in oklab, var(--primary-600) 25%, transparent); background: var(--primary-50); color: var(--primary-700); }

    .dark .cv-settings__heading { color: var(--gray-300); }
    .dark .cv-tile { border-color: rgb(255 255 255 / .1); background: var(--gray-900); box-shadow: none; }
    .dark .cv-tile:hover { border-color: rgb(255 255 255 / .2); }
    .dark .cv-tile__icon { color: var(--gray-400); }
    .dark .cv-tile:hover .cv-tile__icon { color: var(--primary-400); }
    .dark .cv-tile__title { color: #fff; }
    .dark .cv-tile__description { color: var(--gray-400); }
    .dark .cv-tile__status { border-color: rgb(255 255 255 / .1); background: rgb(255 255 255 / .05); color: var(--gray-300); }
    .dark .cv-tile__status--success { color: var(--success-400); }
    .dark .cv-tile__status--warning { color: var(--warning-400); }
    .dark .cv-tile__status--primary { color: var(--primary-400); }
</style>
