<script setup lang="ts">
/**
 * The live preview on the right of the Setup page: a toolbar (device size,
 * copy agent id, reload, open in a new tab) above an iframe of the active
 * surface. The iframe re-mounts when the surface, the device or the reload
 * key changes, so it always shows the saved settings.
 */
import SetupPreviewButton from '@/dashboard/agents/components/setup/SetupPreviewButton.vue';
import { TooltipProvider } from '@/shared/ui/tooltip';
import { useSetupContext } from '@/dashboard/agents/composables/setup/useSetupContext';
import { PREVIEW_DEVICES, type PreviewDevice } from '@/dashboard/agents/composables/setup/useSetupPreview';
import { Check, Copy, ExternalLink, Monitor, RotateCcw, Smartphone, Tablet } from 'lucide-vue-next';
import { computed, type Component } from 'vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();
const { surface, device, isResizing, reloadKey, reload, url, frameClass, openInNewTab, isAgentIdCopied, copyAgentId } = useSetupContext().preview;

const deviceIcons: Record<PreviewDevice, Component> = { desktop: Monitor, tablet: Tablet, mobile: Smartphone };
const deviceLabels = computed<Record<PreviewDevice, string>>(() => ({
    desktop: t('agents.playground.devices.desktop'),
    tablet: t('agents.playground.devices.tablet'),
    mobile: t('agents.playground.devices.mobile'),
}));
</script>

<template>
    <div class="bg-muted/40 flex h-full flex-col border-s">
        <div class="border-border flex flex-shrink-0 items-center justify-between border-b px-4 py-2">
            <div class="text-foreground text-sm font-medium">{{ t('agents.setup.preview.title') }}</div>
            <TooltipProvider :delay-duration="0">
                <div class="flex items-center gap-1">
                    <SetupPreviewButton
                        v-for="option in PREVIEW_DEVICES"
                        :key="option"
                        :label="deviceLabels[option]"
                        :icon="deviceIcons[option]"
                        :class="{ 'text-foreground bg-background shadow-sm': device === option }"
                        @click="device = option"
                    />
                    <SetupPreviewButton
                        :label="isAgentIdCopied ? t('agents.setup.preview.copied') : t('agents.setup.preview.copy_agent_id')"
                        :icon="isAgentIdCopied ? Check : Copy"
                        :class="{ 'text-foreground': isAgentIdCopied }"
                        @click="copyAgentId"
                    />
                    <SetupPreviewButton :label="t('agents.setup.preview.refresh')" :icon="RotateCcw" @click="reload" />
                    <SetupPreviewButton :label="t('agents.setup.preview.open_new_tab')" :icon="ExternalLink" @click="openInNewTab" />
                </div>
            </TooltipProvider>
        </div>
        <div class="flex flex-1 items-center justify-center overflow-hidden p-4">
            <div :class="frameClass" class="transition-all duration-300">
                <iframe
                    :key="`${surface}-${device}-${reloadKey}`"
                    :src="url"
                    class="h-full w-full border-0"
                    :class="{ 'pointer-events-none': isResizing }"
                    :title="t('agents.setup.embed.surface_preview')"
                    allow="microphone"
                    loading="lazy"
                />
            </div>
        </div>
    </div>
</template>
