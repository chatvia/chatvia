{{--
    The test window of the models step: each row is checked as the window
    opens, one after another, and turns from a spinner to a tick (with the
    model's reply, when the test shows it), a warning when the provider is
    busy, or a cross with the reason ({@see \App\Admin\Pages\Setup::testModel()}).
    A section's Test button shows its own rows; Next shows each section under
    its title. When every row is done it sends `model-tests-done` with the
    number that failed, so the window's Continue can wait for it
    ({@see \App\Admin\Pages\Setup::testModelsAction()}).

    @var list<array{section: string, title: string|null, rows: list<array{key: string, label: string}>}> $sections
        What is tested, by section (chat, embeddings, reranker, images or files),
        each row with its label (HTML); a title only when there are several.
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
        count(state) {
            return this.tests.filter((test) => this.state(this.id(test)) === state).length
        },
        get done() {
            return this.tests.every((test) => ['passed', 'busy', 'failed'].includes(this.state(this.id(test))))
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
        @if ($section['title'])
            <p class="cv-model-tests__title">{{ $section['title'] }}</p>
        @endif

        <ul class="cv-model-tests__list">
            @foreach ($section['rows'] as $row)
                @php($id = "{$section['section']}:{$row['key']}")
                <li class="cv-model-test" x-bind:data-state="state(@js($id))" wire:key="model-test-{{ $section['section'] }}-{{ $loop->index }}">
                    <span class="cv-model-test__status" aria-hidden="true">
                        {{-- Each mark in a span of its own: Filament's icon components do not pass x-show on to the SVG. --}}
                        <span x-show="state(@js($id)) === 'waiting'" class="cv-model-test__waiting"></span>
                        <span x-show="state(@js($id)) === 'running'" x-cloak class="cv-model-test__mark">
                            <x-filament::loading-indicator class="cv-model-test__spinner" />
                        </span>
                        <span x-show="state(@js($id)) === 'passed'" x-cloak class="cv-model-test__mark">
                            <x-filament::icon icon="heroicon-s-check-circle" class="cv-model-test__passed" />
                        </span>
                        <span x-show="state(@js($id)) === 'busy'" x-cloak class="cv-model-test__mark">
                            <x-filament::icon icon="heroicon-s-exclamation-triangle" class="cv-model-test__busy" />
                        </span>
                        <span x-show="state(@js($id)) === 'failed'" x-cloak class="cv-model-test__mark">
                            <x-filament::icon icon="heroicon-s-x-circle" class="cv-model-test__failed" />
                        </span>
                    </span>

                    <span class="cv-model-test__body">
                        <span class="cv-model-test__name">{!! $row['label'] !!}</span>
                        <span
                            class="cv-model-test__message"
                            x-show="message(@js($id))"
                            x-cloak
                            x-text="message(@js($id))"
                        ></span>
                    </span>

                    <span class="sr-only" role="status" x-text="{ waiting: 'Waiting', running: 'Testing', passed: 'Answered', busy: 'Busy', failed: 'Failed' }[state(@js($id))]"></span>
                </li>
            @endforeach
        </ul>
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
