{{--
    The update notice on top of the admin dashboard ({@see \App\Admin\Widgets\UpdateBanner}):
    the newer release, how this install upgrades, and its release notes.

    @var ?\App\Admin\Update\Data\ReleaseData $release
    @var string $installed
    @var ?string $hint
    @var bool $newMajor
--}}
<x-filament-widgets::widget>
    @if ($release !== null)
        <a href="{{ $release->url }}" target="_blank" rel="noopener" class="cv-status-alert cv-status-alert--warning" role="status">
            <x-filament::icon icon="heroicon-m-arrow-up-circle" class="cv-status-alert__mark" />
            <span class="cv-status-alert__text">
                <strong>{{ __('ChatVia.ai :version is out.', ['version' => $release->version]) }}</strong>
                {{ __('This install runs :version.', ['version' => $installed]) }}
                @if ($newMajor)
                    {{ __('It is a new major version: read the release notes before you upgrade.') }}
                @endif
                {{ $hint }}
            </span>
            <span class="cv-status-alert__link">{{ __('Release notes') }}</span>
        </a>
    @endif
</x-filament-widgets::widget>
