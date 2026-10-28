{{--
    The admin panel's Settings overview: a search box over all of Settings,
    then the tiles (an icon, a status badge, a title and what the page holds;
    each links to a Settings page). While the box holds a question, its finds
    take the place of the tiles: pages and sections, the matching words
    marked, and the options that match as chips. Each find opens its page on
    its section.

    Keys: "/" goes to the box, Down and Up walk the finds, Enter opens the
    first, Escape empties the box. Styled by the panel theme
    (resources/css/admin/theme.css), so it follows dark mode.

    @var list<array{heading: ?string, tiles: list<array{key: string, title: string, description: string, icon: mixed, status: ?string, tone: string, href: string}>}> $groups
    @var \App\Admin\Clusters\Settings\SettingsSearch $search
    @var string $query
    @var list<string> $terms
    @var list<\App\Admin\Clusters\Settings\Data\SettingsSearchHitData> $hits
--}}
<div
    class="cv-settings"
    x-data="{
        hits() {
            return [...this.$root.querySelectorAll('.cv-search-hit')]
        },
        move(step) {
            const hits = this.hits()
            const next = hits.indexOf(document.activeElement) + step

            next < 0 ? this.$refs.search.focus() : hits[Math.min(next, hits.length - 1)]?.focus()
        },
        openFirst() {
            this.$wire.$commit().then(() => this.hits()[0]?.click())
        },
    }"
    x-on:keydown.window="if ($event.key === '/' && ! $event.target.closest('input, textarea, select, [contenteditable]')) { $event.preventDefault(); $refs.search.focus() }"
>
    <div class="cv-settings-search" role="search">
        <x-filament::input.wrapper prefix-icon="heroicon-m-magnifying-glass" :inline-prefix="true">
            <x-filament::input
                type="search"
                x-ref="search"
                wire:model.live.debounce.250ms="search"
                placeholder="Search all settings: a page, a section or an option"
                aria-label="Search all settings"
                autocomplete="off"
                spellcheck="false"
                x-on:keydown.down.prevent="move(1)"
                x-on:keydown.enter.prevent="openFirst()"
                x-on:keydown.escape="$wire.set('search', '')"
            />
        </x-filament::input.wrapper>
        <kbd class="cv-settings-search__key" aria-hidden="true">/</kbd>
    </div>

    @if ($terms !== [])
        <section class="cv-search" aria-label="Search results" x-on:keydown.down.prevent="move(1)" x-on:keydown.up.prevent="move(-1)">
            @if ($hits === [])
                <div class="cv-search__empty">
                    <x-filament::icon icon="heroicon-o-magnifying-glass" class="cv-search__empty-icon" />
                    <p class="cv-search__empty-title">Nothing in Settings matches “{{ $query }}”</p>
                    <p class="cv-search__empty-text">Try one word: a service (Cohere, SMTP) or an option (port, limit).</p>
                </div>
            @else
                <p class="cv-search__count" aria-live="polite">{{ count($hits) }} {{ str('result')->plural(count($hits)) }}</p>

                <div class="cv-search__list">
                    @foreach ($hits as $hit)
                        <a href="{{ $hit->entry->url }}" class="cv-search-hit" wire:key="hit-{{ md5($hit->entry->url) }}">
                            <span @class(['cv-search-hit__icon', 'cv-search-hit__icon--logo' => is_string($hit->entry->icon), \App\Admin\Clusters\Settings\IconTone::cssClass($hit->entry->icon)])>
                                <x-filament::icon :icon="$hit->entry->icon" />
                            </span>

                            <span class="cv-search-hit__body">
                                <span class="cv-search-hit__path">Settings{{ $hit->entry->page !== null ? ' › '.$hit->entry->page : '' }}</span>
                                <span class="cv-search-hit__title">{{ $search->highlight($hit->entry->title, $terms) }}</span>
                                <span class="cv-search-hit__description">{{ $search->highlight($hit->entry->description, $terms) }}</span>

                                @if ($hit->options !== [])
                                    <span class="cv-chips">
                                        @foreach ($hit->options as $option)
                                            <span class="cv-chip">{{ $search->highlight($option, $terms) }}</span>
                                        @endforeach
                                    </span>
                                @endif
                            </span>

                            <x-filament::icon icon="heroicon-m-arrow-right" class="cv-search-hit__go" />
                        </a>
                    @endforeach
                </div>
            @endif
        </section>
    @else
        @foreach ($groups as $group)
            <section class="cv-settings__group">
                @if (filled($group['heading']))
                    <h2 class="cv-settings__heading">{{ $group['heading'] }}</h2>
                @endif

                <div class="cv-settings__grid">
                    @foreach ($group['tiles'] as $tile)
                        <a href="{{ $tile['href'] }}" class="cv-tile" data-tile="{{ $tile['key'] }}">
                            @include('admin.settings.tile', ['tile' => $tile])
                        </a>
                    @endforeach
                </div>
            </section>
        @endforeach
    @endif
</div>
