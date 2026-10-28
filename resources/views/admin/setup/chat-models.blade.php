{{--
    The chat models agents can use, as badges, and "Manage models", which
    opens the list to change them ({@see \App\Admin\Pages\Setup::manageChatModelsAction()}).

    @var array<string, string> $chosen The chosen models' names, by id.
    @var list<string> $withoutTools The listed models that cannot call tools.
    @var array<string, string|null> $logos Each chosen model's logo URL, by id ({@see \App\Admin\Settings\ModelLogo}).
--}}
<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    <div class="cv-model-badges">
        @forelse ($chosen as $id => $name)
            @php($withoutToolCalls = in_array((string) $id, $withoutTools, true))
            <x-filament::badge
                :color="$withoutToolCalls ? 'warning' : 'primary'"
                :icon="$withoutToolCalls ? 'heroicon-m-exclamation-triangle' : null"
                :tooltip="$withoutToolCalls ? 'No tool calling: tools will not work' : ($name === (string) $id ? null : $id)"
            >
                <span class="cv-model-label">
                    @if (filled($logos[$id] ?? null))
                        {!! \App\Admin\Settings\ModelLogo::mark($logos[$id]) !!}
                    @endif
                    <span>{{ $name }}</span>
                </span>
            </x-filament::badge>
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
