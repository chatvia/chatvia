{{--
    The admin panel's Status page: a banner with the overall state and a
    "Check again" button, a card per service, a row per AI (or other
    outside) service with the logos of the providers it runs on, and the
    environment as `php artisan about` tells it.

    The checks start once the page is open: the services first, then the
    outside services, which wait on other servers. Until a check answers,
    its card says "Checking…". Styled by the panel theme
    (resources/css/filament/admin/theme.css), so it follows dark mode.

    @var list<array{0: \App\Admin\Status\Service, 1: ?\App\Admin\Status\Data\ServiceStatusData}> $services
    @var list<array{0: \App\Admin\Status\AiService, 1: ?\App\Admin\Status\Data\ServiceStatusData}> $aiServices
    @var list<\App\Admin\Status\Data\EnvironmentGroupData> $environment
    @var ?\App\Admin\Status\ServiceState $overall
    @var string $headline
    @var string $subline
--}}
@php
    $mark = match ($overall) {
        \App\Admin\Status\ServiceState::Running => 'heroicon-o-check-circle',
        \App\Admin\Status\ServiceState::Warning => 'heroicon-o-exclamation-triangle',
        \App\Admin\Status\ServiceState::Down => 'heroicon-o-x-circle',
        default => 'heroicon-o-arrow-path',
    };
@endphp

<div
    class="cv-status"
    x-data="{
        checking: false,
        run() {
            this.checking = true
            $wire.checkServices()
                .then(() => $wire.checkAiServices())
                .finally(() => (this.checking = false))
        },
    }"
    x-init="run()"
    x-bind:class="{ 'cv-status--checking': checking }"
>
    <section
        @class(['cv-status-summary', 'cv-status-summary--'.($overall?->tone() ?? 'checking')])
        aria-live="polite"
    >
        <span class="cv-status-summary__mark">
            <x-filament::icon :icon="$mark" />
        </span>

        <div class="cv-status-summary__body">
            <h2 class="cv-status-summary__title">{{ $headline }}</h2>
            <p class="cv-status-summary__text">{{ $subline }}</p>
        </div>

        <x-filament::button
            color="gray"
            icon="heroicon-m-arrow-path"
            class="cv-status-summary__again"
            x-on:click="run()"
            x-bind:disabled="checking"
        >
            {{ __('Check again') }}
        </x-filament::button>
    </section>

    <section class="cv-status-section">
        <h2 class="cv-status-section__heading">{{ __('Services') }}</h2>

        <div class="cv-status-grid">
            @foreach ($services as [$service, $status])
                <article class="cv-service" data-service="{{ $service->value }}" data-state="{{ $status?->state->value ?? 'checking' }}">
                    <div class="cv-service__head">
                        <span class="cv-service__icon">
                            <x-filament::icon :icon="$service->icon()" />
                        </span>

                        <div class="cv-service__name">
                            <h3 class="cv-service__title">{{ $service->label() }}</h3>
                            <p class="cv-service__description">{{ $service->description() }}</p>
                        </div>

                        @include('filament.status.state', ['status' => $status])
                    </div>

                    @if ($status === null)
                        <span class="cv-skeleton" aria-hidden="true"></span>
                    @else
                        <p class="cv-service__summary">{{ $status->summary }}</p>

                        @if ($status->facts !== [])
                            <ul class="cv-status-facts">
                                @foreach ($status->facts as $fact)
                                    <li class="cv-status-fact">{{ $fact }}</li>
                                @endforeach
                            </ul>
                        @endif
                    @endif
                </article>
            @endforeach
        </div>
    </section>

    <section class="cv-status-section">
        <h2 class="cv-status-section__heading">{{ __('AI and other services') }}</h2>

        <div class="cv-status-list">
            @foreach ($aiServices as [$service, $status])
                <div class="cv-status-row" data-ai-service="{{ $service->value }}" data-state="{{ $status?->state->value ?? 'checking' }}">
                    <span class="cv-service__icon">
                        <x-filament::icon :icon="$service->icon()" />
                    </span>

                    <div class="cv-status-row__name">
                        <h3 class="cv-service__title">{{ $service->label() }}</h3>
                        <p class="cv-service__description">
                            {{ $status !== null && $status->state !== \App\Admin\Status\ServiceState::Running ? $status->summary : $service->description() }}
                        </p>
                    </div>

                    <div class="cv-status-row__providers">
                        @if ($status === null)
                            <span class="cv-skeleton cv-skeleton--short" aria-hidden="true"></span>
                        @else
                            @foreach ($status->providers as $provider)
                                <span class="cv-provider-logo">
                                    <x-filament::icon :icon="\App\Admin\Settings\AiProviderIcon::of(\Domain\Llm\Enums\AiProvider::from($provider))" />
                                </span>
                            @endforeach

                            @foreach ($status->facts as $fact)
                                <span class="cv-status-fact">{{ $fact }}</span>
                            @endforeach
                        @endif
                    </div>

                    @include('filament.status.state', ['status' => $status])
                </div>
            @endforeach
        </div>
    </section>

    <section class="cv-status-section">
        <h2 class="cv-status-section__heading">{{ __('Environment') }}</h2>

        <div class="cv-status-grid cv-status-grid--wide">
            @foreach ($environment as $group)
                <article class="cv-env">
                    <h3 class="cv-env__title">
                        <x-filament::icon :icon="$group->icon" />
                        {{ $group->title }}
                    </h3>

                    <dl class="cv-env__facts">
                        @foreach ($group->facts as $fact)
                            <div class="cv-env__fact">
                                <dt>{{ $fact->label }}</dt>
                                <dd @class(['cv-env__value', 'cv-env__value--'.$fact->tone => $fact->tone !== null])>{{ $fact->value }}</dd>
                            </div>
                        @endforeach
                    </dl>
                </article>
            @endforeach
        </div>
    </section>
</div>
