<script setup lang="ts">
/**
 * The surface tab bar at the top of the Setup page (Hosted Chat / Widget /
 * Command Bar). It switches the live preview and the surface-specific
 * sections; content settings apply to every surface.
 */
import type { SetupNavItem } from '@/composables/agents/setup/useSetupSections';
import type { SetupSurface } from '@/composables/agents/setup/useSetupPreview';

defineProps<{
    tabs: SetupNavItem<SetupSurface>[];
}>();

const surface = defineModel<SetupSurface>({ required: true });
</script>

<template>
    <div class="bg-background border-border flex flex-shrink-0 flex-wrap items-center justify-between gap-3 border-b pe-4 lg:pe-6">
        <nav class="flex items-center gap-1 px-4">
            <button
                v-for="tab in tabs"
                :key="tab.value"
                type="button"
                class="relative flex items-center gap-2 px-4 py-3 text-sm transition-colors"
                :class="[surface === tab.value ? 'text-foreground font-medium' : 'text-muted-foreground hover:text-foreground']"
                @click="surface = tab.value"
            >
                <component :is="tab.icon" class="h-4 w-4" />
                <span>{{ tab.label }}</span>
                <span v-if="surface === tab.value" class="bg-foreground absolute start-0 end-0 -bottom-px h-0.5"></span>
            </button>
        </nav>
    </div>
</template>
