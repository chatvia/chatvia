{{--
    The test window of a section of the models step: each row is checked as
    the window opens, one after another, and turns from a spinner to a tick
    (with the model's reply, when the test shows it), a warning when the
    provider is busy, or a cross with the reason
    ({@see \App\Admin\Pages\Setup::testModel()}).

    @var string $section The section tested: chat, embeddings, reranker, images or files.
    @var list<array{key: string, label: string}> $rows What is tested, each with its label (HTML).
    @var string|null $image The picture the model is shown (a data URI), for the image test.
--}}
<div
    class="cv-model-tests"
    x-data="{
        keys: @js(array_column($rows, 'key')),
        results: {},
        state(key) {
            return this.results[key]?.state ?? 'waiting'
        },
        message(key) {
            return this.results[key]?.message ?? null
        },
        count(state) {
            return this.keys.filter((key) => this.state(key) === state).length
        },
        get done() {
            return this.keys.every((key) => ['passed', 'busy', 'failed'].includes(this.state(key)))
        },
        get summary() {
            const total = this.keys.length
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
            for (const key of this.keys) {
                this.results[key] = { state: 'running' }

                try {
                    this.results[key] = await $wire.testModel(@js($section), key)
                } catch (error) {
                    this.results[key] = { state: 'failed', message: 'The test could not run. Try again.' }
                }
            }
        },
    }"
>
    @if ($image)
        <img src="{{ $image }}" alt="A cartoon cat" class="cv-model-tests__image" width="96" height="96" />
    @endif

    <ul class="cv-model-tests__list">
        @foreach ($rows as $row)
            <li class="cv-model-test" x-bind:data-state="state(@js($row['key']))" wire:key="model-test-{{ $section }}-{{ $loop->index }}">
                <span class="cv-model-test__status" aria-hidden="true">
                    {{-- Each mark in a span of its own: Filament's icon components do not pass x-show on to the SVG. --}}
                    <span x-show="state(@js($row['key'])) === 'waiting'" class="cv-model-test__waiting"></span>
                    <span x-show="state(@js($row['key'])) === 'running'" x-cloak class="cv-model-test__mark">
                        <x-filament::loading-indicator class="cv-model-test__spinner" />
                    </span>
                    <span x-show="state(@js($row['key'])) === 'passed'" x-cloak class="cv-model-test__mark">
                        <x-filament::icon icon="heroicon-s-check-circle" class="cv-model-test__passed" />
                    </span>
                    <span x-show="state(@js($row['key'])) === 'busy'" x-cloak class="cv-model-test__mark">
                        <x-filament::icon icon="heroicon-s-exclamation-triangle" class="cv-model-test__busy" />
                    </span>
                    <span x-show="state(@js($row['key'])) === 'failed'" x-cloak class="cv-model-test__mark">
                        <x-filament::icon icon="heroicon-s-x-circle" class="cv-model-test__failed" />
                    </span>
                </span>

                <span class="cv-model-test__body">
                    <span class="cv-model-test__name">{!! $row['label'] !!}</span>
                    <span
                        class="cv-model-test__message"
                        x-show="message(@js($row['key']))"
                        x-cloak
                        x-text="message(@js($row['key']))"
                    ></span>
                </span>

                <span class="sr-only" role="status" x-text="{ waiting: 'Waiting', running: 'Testing', passed: 'Answered', busy: 'Busy', failed: 'Failed' }[state(@js($row['key']))]"></span>
            </li>
        @endforeach
    </ul>

    <p
        class="cv-model-tests__summary"
        role="status"
        aria-live="polite"
        x-show="done"
        x-cloak
        x-bind:data-state="count('failed') ? 'failed' : (count('busy') ? 'busy' : 'passed')"
        x-text="summary"
    ></p>
</div>
