{{--
    The outline of a Settings page: a link to each card's section, with a dot
    for a service's status, buttons that open or close every section, and the
    page's Save button. It stays in view beside the sections on wide screens
    and is a row of links above them on narrow ones. A link opens its section
    if it is closed, and the link of the section in view is highlighted.
    Styled with the panel's own colour variables, so it needs no theme build.

    @var list<array{id: string, title: string, status: string, tone: string}> $items
--}}
@php($ids = array_column($items, 'id'))

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
        init() {
            const id = decodeURIComponent(location.hash.slice(1))

            if (this.ids.includes(id)) {
                setTimeout(() => this.go(id), 100)
            }
        },
    }"
    x-on:scroll.window.passive="spy()"
>
    <p class="cv-outline__heading">On this page</p>

    <ul class="cv-outline__list">
        @foreach ($items as $item)
            <li>
                <a
                    href="#{{ $item['id'] }}"
                    class="cv-outline__link"
                    title="{{ $item['status'] }}"
                    x-on:click.prevent="go(@js($item['id']))"
                    x-bind:class="{ 'cv-outline__link--active': active === @js($item['id']) }"
                    x-bind:aria-current="active === @js($item['id']) ? 'location' : null"
                >
                    <span class="cv-outline__dot cv-outline__dot--{{ $item['tone'] }}" aria-hidden="true"></span>
                    <span class="cv-outline__title">{{ $item['title'] }}</span>
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

    {{-- Outside the form: `form-id` ties it to the form, `form` names the submit handler. --}}
    <x-filament::button type="submit" form="save" form-id="form" class="cv-outline__save">
        Save changes
    </x-filament::button>
</nav>

<style>
    .cv-settings-card { scroll-margin-top: 5.5rem; }
    /* A long status (an error, a summary) wraps beside the title instead of squeezing it. */
    .cv-settings-card .fi-section-header-text-ctn { flex: 1 1 14rem; min-width: 0; }
    .cv-settings-card .fi-section-header-after-ctn { flex: 0 1 auto; max-width: min(50%, 24rem); }
    .cv-settings-card .fi-section-header-after-ctn .fi-sc-text:not(.fi-badge) { display: -webkit-box; overflow: hidden; text-align: end; -webkit-box-orient: vertical; -webkit-line-clamp: 2; }

    /* Phones: the status and the console link get their own line below the title. */
    @media (max-width: 39.99rem) {
        .cv-settings-card .fi-section-header { flex-wrap: wrap; }
        .cv-settings-card .fi-section-header-after-ctn { order: 1; flex-basis: 100%; max-width: none; padding-inline-start: 2.25rem; }
        .cv-settings-card .fi-section-header-after-ctn .fi-sc { justify-content: flex-start; }
        .cv-settings-card .fi-section-header-after-ctn .fi-sc-text:not(.fi-badge) { text-align: start; }
    }

    .cv-outline { display: grid; gap: .75rem; min-width: 0; }
    .cv-outline__heading { margin: 0; font-size: .75rem; font-weight: 600; letter-spacing: .04em; text-transform: uppercase; color: var(--gray-500); }

    /* Narrow screens: a row of links above the sections. */
    .cv-outline__list { display: flex; gap: .5rem; margin: 0; padding: 0 0 .25rem; overflow-x: auto; list-style: none; scrollbar-width: thin; }
    .cv-outline__link { display: flex; align-items: center; gap: .5rem; padding: .375rem .75rem; border: 1px solid var(--gray-200); border-radius: 9999px; background: #fff; font-size: .875rem; line-height: 1.25; color: var(--gray-600); text-decoration: none; white-space: nowrap; transition: color .15s ease, border-color .15s ease, background-color .15s ease; }
    .cv-outline__link:hover { color: var(--gray-950); }
    .cv-outline__link:focus-visible { outline: 2px solid var(--primary-500); outline-offset: 2px; }
    .cv-outline__link--active { border-color: var(--primary-500); color: var(--primary-600); }

    .cv-outline__dot { flex: none; width: .5rem; height: .5rem; border-radius: 9999px; background: var(--gray-300); }
    .cv-outline__dot--success { background: var(--success-500); }
    .cv-outline__dot--warning { background: var(--warning-500); }
    .cv-outline__dot--primary { background: var(--primary-500); }
    .cv-outline__dot--options { border: 1.5px solid var(--gray-300); background: transparent; }
    .cv-outline__title { overflow: hidden; text-overflow: ellipsis; }

    .cv-outline__actions { display: flex; align-items: center; gap: .375rem; font-size: .8125rem; color: var(--gray-400); }
    .cv-outline__actions button { padding: 0; border: 0; background: none; font: inherit; color: var(--gray-500); cursor: pointer; }
    .cv-outline__actions button:hover { color: var(--gray-950); text-decoration: underline; text-underline-offset: 2px; }
    .cv-outline__save { display: none; }

    /* Wide screens: a list beside the sections that stays in view. */
    @media (min-width: 80rem) {
        .cv-outline { position: sticky; top: 5.5rem; padding-inline-start: .25rem; }
        .cv-outline__list { flex-direction: column; gap: 0; padding: 0; overflow: visible; border-inline-start: 1px solid var(--gray-200); }
        .cv-outline__link { margin-inline-start: -1px; padding: .4375rem .75rem; border: 0; border-inline-start: 2px solid transparent; border-radius: 0; background: none; white-space: normal; }
        .cv-outline__link--active { border-color: var(--primary-600); font-weight: 500; }
        .cv-outline__actions { padding-inline-start: .75rem; }
        .cv-outline__save { display: inline-grid; justify-self: start; margin-top: .25rem; }
    }

    .dark .cv-outline__link { border-color: rgb(255 255 255 / .1); background: var(--gray-900); color: var(--gray-400); }
    .dark .cv-outline__link:hover { color: #fff; }
    .dark .cv-outline__link--active { border-color: var(--primary-400); color: var(--primary-400); }
    .dark .cv-outline__dot--options { border-color: var(--gray-600); }
    .dark .cv-outline__actions button { color: var(--gray-400); }
    .dark .cv-outline__actions button:hover { color: #fff; }

    @media (min-width: 80rem) {
        .dark .cv-outline__list { border-color: rgb(255 255 255 / .1); }
        .dark .cv-outline__link { border-color: transparent; background: none; }
        .dark .cv-outline__link--active { border-color: var(--primary-400); }
    }
</style>
