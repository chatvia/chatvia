<script setup lang="ts">
/**
 * One install method on the agent's Deploy page (Chat Widget, Hosted Chat,
 * Command Bar): icon, title, description and the surface's on/off switch. The
 * body (default slot) is dimmed and not clickable while the surface is off.
 */
import { Switch } from '@/shared/ui/switch';
import type { Component } from 'vue';
import { useI18n } from 'vue-i18n';

withDefaults(
    defineProps<{
        icon: Component;
        title: string;
        description: string;
        enabled: boolean;
        saving: boolean;
        /** Vertical spacing between the body's blocks. */
        bodyClass?: string;
    }>(),
    { bodyClass: 'space-y-4' },
);

const emit = defineEmits<{
    toggle: [enabled: boolean];
}>();

const { t } = useI18n();
</script>

<template>
    <section class="bg-card rounded-xl border">
        <div class="flex items-start gap-4 border-b p-5">
            <div class="bg-primary/10 text-primary flex size-11 shrink-0 items-center justify-center rounded-lg">
                <component :is="icon" class="size-5" />
            </div>
            <div class="min-w-0">
                <h2 class="text-base font-semibold">{{ title }}</h2>
                <p class="text-muted-foreground mt-0.5 text-sm">{{ description }}</p>
            </div>
            <div class="ms-auto flex shrink-0 items-center gap-2">
                <span class="text-muted-foreground text-xs">{{ enabled ? t('agents.deploy.enabled') : t('agents.deploy.disabled') }}</span>
                <Switch
                    :model-value="enabled"
                    :disabled="saving"
                    :aria-label="t('agents.deploy.toggle_surface')"
                    @update:model-value="(value) => emit('toggle', value)"
                />
            </div>
        </div>
        <div class="p-5" :class="[bodyClass, { 'pointer-events-none opacity-50': !enabled }]">
            <slot />
        </div>
    </section>
</template>
