{{--
    The setup's first step: a row for each service Chatvia runs on, checked
    live once the step opens and again on "Check again". A row shows only
    the service and its state (or, when it does not run, what is wrong);
    "Details" opens how to fix it and every check line.

    @var list<array{0: \App\Admin\Status\Service, 1: ?\App\Admin\Status\Data\ServiceStatusData}> $services
    @var bool $checked
    @var string $headline
--}}
<div
    class="cv-setup-server"
    x-data="{
        checking: false,
        run() {
            this.checking = true
            $wire.checkServer().finally(() => (this.checking = false))
        },
    }"
    @if (! $checked) x-init="run()" @endif
    x-bind:class="{ 'cv-status--checking': checking }"
>
    <div class="cv-setup-server__bar">
        <p class="cv-setup-server__headline" aria-live="polite">{{ $headline }}</p>

        <x-filament::button
            color="gray"
            size="sm"
            icon="heroicon-m-arrow-path"
            class="cv-status-summary__again"
            x-on:click="run()"
            x-bind:disabled="checking"
        >
            Check again
        </x-filament::button>
    </div>

    <div class="cv-status-list">
        @foreach ($services as [$service, $status])
            <div class="cv-status-row cv-status-row--service" data-service="{{ $service->value }}" data-state="{{ $status?->state->value ?? 'checking' }}">
                <span class="cv-service__icon cv-service__icon--tinted">
                    @if ($service->logo() !== null)
                        <img src="{{ $service->logo() }}" alt="" class="cv-service__logo" />
                    @else
                        <x-filament::icon :icon="$service->icon()" />
                    @endif
                </span>

                <div class="cv-status-row__name">
                    <h3 class="cv-service__title">{{ $service->label() }}</h3>
                    <p class="cv-service__description">
                        {{ $status !== null && $status->state !== \App\Admin\Status\ServiceState::Running ? $status->summary : $service->description() }}
                    </p>
                </div>

                @include('filament.status.state', ['status' => $status, 'label' => $status?->state->label()])

                <div class="cv-status-row__details">
                    @if ($status !== null && ($status->checks !== [] || $status->hint !== null))
                        <x-filament::modal width="md" :heading="$service->label()" :description="$status->summary">
                            <x-slot name="trigger">
                                <x-filament::button color="gray" size="xs" outlined>
                                    {{ __('Details') }}
                                </x-filament::button>
                            </x-slot>

                            @if ($status->hint !== null)
                                <p @class(['cv-status-hint', 'cv-status-hint--'.$status->state->tone()])>{{ $status->hint }}</p>
                            @endif

                            @if ($status->checks !== [])
                                @include('filament.status.checks', ['checks' => $status->checks])
                            @endif
                        </x-filament::modal>
                    @endif
                </div>
            </div>
        @endforeach
    </div>
</div>
