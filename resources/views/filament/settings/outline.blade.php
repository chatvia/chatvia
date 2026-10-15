{{--
    The outline of a Settings page: a link to each card's section, with the
    card's icon (or its service's logo) and, for a service, a dot for its
    status; the advanced cards last under their own heading, and buttons
    that open or close every section. It stays in view beside the sections
    on wide screens and is a row of links above them on narrow ones. A link
    opens its section if it is closed, and the link of the section in view
    is highlighted. A page opened on a section (a find of the Settings
    search) opens it, scrolls to it and rings it for a moment. Styled by the
    panel theme (resources/css/filament/admin/theme.css), which also holds
    the rules for the cards themselves (`cv-settings-card`).

    @var list<array{id: string, title: string, icon: string|\Filament\Support\Icons\Heroicon, status: string, tone: ?string, advanced: bool}> $items
--}}
@php($ids = array_column($items, 'id'))
@php($firstAdvanced = array_search(true, array_column($items, 'advanced'), true))

<nav
    class="cv-outline"
    aria-label="On this page"
    wire:key="settings-outline-{{ md5(implode(',', $ids)) }}"
    x-data="{
        ids: @js($ids),
        active: @js($ids[0] ?? null),
        go(id) {
            this.$dispatch('expand-section', { id })
            history.replaceState(null, '', '#' + id)
            this.active = id
            this.$nextTick(() => document.getElementById(id)?.scrollIntoView({ behavior: 'smooth', block: 'start' }))
        },
        all(event) {
            this.ids.forEach((id) => this.$dispatch(event, { id }))
        },
        spy() {
            if (window.innerHeight + window.scrollY >= document.documentElement.scrollHeight - 4) {
                this.active = this.ids.at(-1)

                return
            }

            this.active = this.ids.findLast((id) => (document.getElementById(id)?.getBoundingClientRect().top ?? Infinity) <= 140) ?? this.ids[0]
        },
        flash(id) {
            const section = document.getElementById(id)

            section?.classList.add('cv-settings-card--found')
            setTimeout(() => section?.classList.remove('cv-settings-card--found'), 2600)
        },
        init() {
            const id = decodeURIComponent(location.hash.slice(1))

            if (this.ids.includes(id)) {
                setTimeout(() => {
                    this.go(id)
                    this.flash(id)
                }, 100)
            }
        },
    }"
    x-on:scroll.window.passive="spy()"
>
    <p class="cv-outline__heading">On this page</p>

    <ul class="cv-outline__list">
        @foreach ($items as $index => $item)
            @if ($index === $firstAdvanced && $index > 0)
                <li class="cv-outline__group" aria-hidden="true">Advanced</li>
            @endif
            <li>
                <a
                    href="#{{ $item['id'] }}"
                    class="cv-outline__link"
                    title="{{ $item['status'] }}"
                    x-on:click.prevent="go(@js($item['id']))"
                    x-bind:class="{ 'cv-outline__link--active': active === @js($item['id']) }"
                    x-bind:aria-current="active === @js($item['id']) ? 'location' : null"
                >
                    <x-filament::icon
                        :icon="$item['icon']"
                        @class(['cv-outline__icon', 'cv-outline__icon--logo' => is_string($item['icon'])])
                        aria-hidden="true"
                    />
                    <span class="cv-outline__title">{{ $item['title'] }}</span>
                    @if ($item['tone'] !== null)
                        <span class="cv-outline__dot cv-outline__dot--{{ $item['tone'] }}" aria-hidden="true"></span>
                    @endif
                </a>
            </li>
        @endforeach
    </ul>

    @if (count($items) > 1)
        <div class="cv-outline__actions">
            <button type="button" x-on:click="all('expand-section')">Expand all</button>
            <span aria-hidden="true">·</span>
            <button type="button" x-on:click="all('collapse-section')">Collapse all</button>
        </div>
    @endif
</nav>
