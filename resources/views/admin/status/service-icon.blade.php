{{--
    A service's tile: the logo of its product, or its icon in the service's
    colour. Models lists the models of many AI providers, so its sparkles
    are stroked in a gradient; a stroke takes more than one colour only from
    an SVG gradient, which the tile carries (the stops are coloured in CSS).

    @var \App\Admin\Status\Service $service
--}}
<span class="cv-service__icon cv-service__icon--tinted">
    @if ($service->logo() !== null)
        <img src="{{ $service->logo() }}" alt="" class="cv-service__logo" />
    @else
        @if ($service === \App\Admin\Status\Service::ModelDetails)
            <svg class="cv-service__gradient" aria-hidden="true" focusable="false">
                <linearGradient id="cv-service-gradient-models" x1="0" y1="0" x2="1" y2="1">
                    <stop offset="0" />
                    <stop offset="0.5" />
                    <stop offset="1" />
                </linearGradient>
            </svg>
        @endif

        <x-filament::icon :icon="$service->icon()" />
    @endif
</span>
