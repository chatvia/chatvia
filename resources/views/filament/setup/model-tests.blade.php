{{--
    The test window of the models step: each model is checked as the window
    opens, one after another ({@see \App\Admin\Pages\Setup::testModel()}).
    Each section is one short card: its title and how many are done ("3/5"),
    the model being asked now, and a row of marks, one per model, that turn
    from waiting to a spinner to a tick, a warning when the provider is busy,
    or a cross. A mark's tooltip names its model. What went wrong is listed
    under the marks, once per reason with the models it hit (and a model's
    reply, when the test shows it). When every model is done it sends
    `model-tests-done` with the number that failed, so the window's Continue
    can wait for it ({@see \App\Admin\Pages\Setup::testModelsAction()}).

    @var list<array{section: string, title: string, rows: list<array{key: string, label: string, name: string}>}> $sections
        What is tested, by section (chat, embeddings, reranker, images or files),
        each row with its label (HTML) and its name (plain text).
    @var string|null $image The picture the model is shown (a data URI), for the image test.
    @var string|null $onFailure What to do when a model did not answer, shown under the summary.
--}}
@php
    $tests = collect($sections)
        ->flatMap(fn (array $section): array => array_map(fn (array $row): array => ['section' => $section['section'], 'key' => $row['key']], $section['rows']))
        ->values()
        ->all();
@endphp
<div
    class="cv-model-tests"
    x-data="{
        tests: @js($tests),
        results: {},
        id(test) {
            return `${test.section}:${test.key}`
        },
        state(id) {
            return this.results[id]?.state ?? 'waiting'
        },
        message(id) {
            return this.results[id]?.message ?? null
        },
        finished(id) {
            return ['passed', 'busy', 'failed'].includes(this.state(id))
        },
        count(state) {
            return this.tests.filter((test) => this.state(this.id(test)) === state).length
        },
        progress(ids) {
            return `${ids.filter((id) => this.finished(id)).length}/${ids.length}`
        },
        {{-- The model a card names: the one being asked, else the first still waiting, else (one model) that one. --}}
        current(ids) {
            return ids.find((id) => this.state(id) === 'running')
                ?? (ids.some((id) => this.finished(id)) ? null : ids[0])
                ?? (ids.length === 1 ? ids[0] : null)
        },
        sectionState(ids) {
            if (! ids.every((id) => this.finished(id))) {
                return ids.some((id) => this.state(id) !== 'waiting') ? 'running' : 'waiting'
            }

            return ['failed', 'busy'].find((state) => ids.some((id) => this.state(id) === state)) ?? 'passed'
        },
        {{-- What went wrong, once per reason: models that failed the same way share a line. --}}
        notes(ids) {
            const notes = []

            for (const id of ids.filter((id) => this.message(id))) {
                const note = notes.find((note) => note.state === this.state(id) && note.message === this.message(id))

                note ? note.ids.push(id) : notes.push({ state: this.state(id), message: this.message(id), ids: [id] })
            }

            return notes
        },
        sectionSummary(ids) {
            const answered = ids.filter((id) => this.state(id) === 'passed').length

            return answered === ids.length ? 'Each one answered.' : `${answered} of ${ids.length} answered.`
        },
        get done() {
            return this.tests.every((test) => this.finished(this.id(test)))
        },
        get summary() {
            const total = this.tests.length
            const failed = this.count('failed')
            const busy = this.count('busy')

            if (! failed && ! busy) {
                return total === 1 ? 'All good: it answered.' : 'All good: each one answered.'
            }

            const parts = []

            if (failed) {
                parts.push(total === 1 ? 'It did not answer.' : (failed === total ? 'None of them answered.' : `${failed} of ${total} did not answer.`))
            }

            if (busy) {
                parts.push(total === 1 ? 'Its provider was busy: test again in a minute.' : `${busy} ${busy === 1 ? 'was' : 'were'} busy: test again in a minute.`)
            }

            return parts.join(' ')
        },
        async init() {
            for (const test of this.tests) {
                this.results[this.id(test)] = { state: 'running' }

                try {
                    this.results[this.id(test)] = await $wire.testModel(test.section, test.key)
                } catch (error) {
                    this.results[this.id(test)] = { state: 'failed', message: 'The test could not run. Try again.' }
                }
            }

            this.$dispatch('model-tests-done', { failed: this.count('failed') })
        },
    }"
>
    @if ($image)
        <img src="{{ $image }}" alt="A cartoon cat" class="cv-model-tests__image" width="96" height="96" />
    @endif

    @foreach ($sections as $section)
        @php($ids = array_map(fn (array $row): string => "{$section['section']}:{$row['key']}", $section['rows']))
        <section
            @class(['cv-model-tests__section', 'cv-model-tests__section--single' => count($ids) === 1])
            x-data="{ ids: @js($ids), names: @js(array_combine($ids, array_column($section['rows'], 'name'))) }"
            x-bind:data-state="sectionState(ids)"
            wire:key="model-tests-{{ $section['section'] }}"
        >
            <div class="cv-model-tests__head">
                <p class="cv-model-tests__title">{{ $section['title'] }}</p>
                <span class="cv-model-tests__count" x-text="progress(ids)">0/{{ count($ids) }}</span>
            </div>

            {{-- The model being asked now, and the marks: under it, or before it when the card has one. --}}
            <div class="cv-model-tests__body">
                <p class="cv-model-tests__now" aria-live="polite">
                    @foreach ($section['rows'] as $row)
                        @php($id = $ids[$loop->index])
                        <span class="cv-model-tests__current" x-show="current(ids) === @js($id)" @if (! $loop->first) x-cloak @endif>
                            <span class="cv-model-tests__verb" x-show="state(@js($id)) === 'running'" x-cloak>Testing</span>
                            <span class="cv-model-tests__verb" x-show="state(@js($id)) === 'waiting'">Waiting</span>
                            <span class="cv-model-tests__name">{!! $row['label'] !!}</span>
                        </span>
                    @endforeach
                    <span
                        class="cv-model-tests__done"
                        x-show="current(ids) === null"
                        x-cloak
                        x-text="sectionSummary(ids)"
                    ></span>
                </p>

                <ol class="cv-model-tests__marks">
                    @foreach ($section['rows'] as $row)
                        @php($id = $ids[$loop->index])
                        <li
                            class="cv-model-test"
                            x-bind:data-state="state(@js($id))"
                            x-tooltip="{ content: @js($row['name']), theme: $store.theme }"
                            wire:key="model-test-{{ $section['section'] }}-{{ $loop->index }}"
                        >
                            {{-- Each mark in a span of its own: Filament's icon components do not pass x-show on to the SVG. --}}
                            <span x-show="state(@js($id)) === 'waiting'" class="cv-model-test__waiting" aria-hidden="true"></span>
                            <span x-show="state(@js($id)) === 'running'" x-cloak class="cv-model-test__mark" aria-hidden="true">
                                <x-filament::loading-indicator class="cv-model-test__spinner" />
                            </span>
                            <span x-show="state(@js($id)) === 'passed'" x-cloak class="cv-model-test__mark" aria-hidden="true">
                                <x-filament::icon icon="heroicon-s-check-circle" class="cv-model-test__passed" />
                            </span>
                            <span x-show="state(@js($id)) === 'busy'" x-cloak class="cv-model-test__mark" aria-hidden="true">
                                <x-filament::icon icon="heroicon-s-exclamation-circle" class="cv-model-test__busy" />
                            </span>
                            <span x-show="state(@js($id)) === 'failed'" x-cloak class="cv-model-test__mark" aria-hidden="true">
                                <x-filament::icon icon="heroicon-s-x-circle" class="cv-model-test__failed" />
                            </span>

                            <span class="sr-only" x-text="@js($row['name']) + ': ' + { waiting: 'waiting', running: 'testing', passed: 'answered', busy: 'busy', failed: 'failed' }[state(@js($id))]"></span>
                        </li>
                    @endforeach
                </ol>
            </div>

            {{-- Why models are busy or failed, or a model's reply when the test shows it. --}}
            <ul class="cv-model-tests__notes" x-show="notes(ids).length" x-cloak>
                <template x-for="note in notes(ids)" x-bind:key="`${note.state}:${note.message}`">
                    <li class="cv-model-tests__note" x-bind:data-state="note.state">
                        <span
                            class="cv-model-tests__name"
                            x-show="ids.length > 1"
                            x-text="note.ids.length > 1 && note.ids.length === ids.length ? `All ${ids.length}` : note.ids.map((id) => names[id]).join(', ')"
                        ></span>
                        <span class="cv-model-tests__message" x-text="note.message"></span>
                    </li>
                </template>
            </ul>
        </section>
    @endforeach

    <p
        class="cv-model-tests__summary"
        role="status"
        aria-live="polite"
        x-show="done"
        x-cloak
        x-bind:data-state="count('failed') ? 'failed' : (count('busy') ? 'busy' : 'passed')"
        x-text="summary"
    ></p>

    @if ($onFailure)
        <p class="cv-model-tests__hint" x-show="done && count('failed')" x-cloak>{{ $onFailure }}</p>
    @endif
</div>
