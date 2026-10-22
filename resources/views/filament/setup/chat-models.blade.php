{{--
    The chat models agents can use, as badges, and "Manage models", which
    opens the list to change them ({@see \App\Admin\Pages\Setup::manageChatModelsAction()}).

    @var array<string, string> $chosen The chosen models' names, by id.
--}}
<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    <div class="cv-model-badges">
        @forelse ($chosen as $id => $name)
            <x-filament::badge color="primary" :tooltip="$name === (string) $id ? null : $id">{{ $name }}</x-filament::badge>
        @empty
            <span class="cv-model-badges__empty">No models picked yet.</span>
        @endforelse

        <x-filament::link
            tag="button"
            type="button"
            size="sm"
            icon="heroicon-m-adjustments-horizontal"
            wire:click="mountAction('manageChatModels')"
        >
            Manage models
        </x-filament::link>
    </div>
</x-dynamic-component>
