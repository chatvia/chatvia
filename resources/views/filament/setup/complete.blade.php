{{-- The setup's last step: done, and where the admin signs in to their workspace after Finish setup. --}}
<div class="cv-setup-complete">
    <span class="cv-setup-complete__check">
        <x-filament::icon icon="heroicon-m-check" />
    </span>
    <h2 class="cv-setup-complete__title">Congratulations</h2>
    <p class="cv-setup-complete__text">
        ChatVia.ai is ready. Press <strong>Finish setup</strong> to save, then sign in to your workspace to create your first agent:
    </p>
    <a class="cv-setup-complete__link" href="{{ $loginUrl }}" target="_blank" rel="noopener">{{ $loginUrl }}</a>
</div>
