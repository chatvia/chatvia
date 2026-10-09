<script setup lang="ts">
/** The Setup page's left-hand section menu. */
import type { SetupNavItem, SetupSection } from '@/composables/agents/setup/useSetupSections';

defineProps<{
    items: SetupNavItem<SetupSection>[];
}>();

const active = defineModel<SetupSection>({ required: true });
</script>

<template>
    <div class="bg-muted/30 flex h-full w-[180px] flex-shrink-0 flex-col border-e">
        <nav class="flex flex-col gap-0.5 p-2">
            <button
                v-for="item in items"
                :key="item.value"
                type="button"
                class="flex h-9 w-full cursor-pointer items-center gap-2.5 rounded-md px-3 text-sm transition-colors"
                :class="[
                    active === item.value
                        ? 'bg-background text-foreground font-medium shadow-sm'
                        : 'text-muted-foreground hover:bg-background/50 hover:text-foreground',
                ]"
                @click="active = item.value"
            >
                <component :is="item.icon" class="h-4 w-4 flex-shrink-0" />
                <span>{{ item.label }}</span>
            </button>
        </nav>
    </div>
</template>
