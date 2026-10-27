{{-- The setup's last step: done, where the admin signs in to their workspace after Finish setup, and the button itself (the step has no action bar). --}}
<div class="cv-setup-complete">
    <span class="cv-setup-complete__check">
        <x-filament::icon icon="heroicon-m-check" />
    </span>
    <h2 class="cv-setup-complete__title">Congratulations</h2>
    <p class="cv-setup-complete__text">
        ChatVia.ai is ready. Press <strong>Finish setup</strong> to save, then sign in to your workspace to create your first agent:
    </p>
    <a class="cv-setup-complete__link" href="{{ $loginUrl }}" target="_blank" rel="noopener">{{ $loginUrl }}</a>
    <x-filament::button type="submit" color="success" size="xl" icon="heroicon-m-rocket-launch" wire:target="complete" class="cv-setup-complete__finish">
        Finish setup
    </x-filament::button>
    <x-filament::link tag="button" type="button" color="gray" size="sm" x-on:click="goToPreviousStep()">Back</x-filament::link>
</div>
