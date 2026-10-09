<script setup lang="ts">
/**
 * Builds the `<iframe>` code that embeds the Hosted Chat in a page: width and
 * height inputs, four size presets, and the resulting snippet with its copy
 * button. Emits `copy` with the current code.
 */
import DeploySnippet from '@/components/agents/deploy/DeploySnippet.vue';
import { iframeSnippet } from '@/components/agents/deploy/installSnippets';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { MoveHorizontal, MoveVertical } from 'lucide-vue-next';
import { computed, reactive, watchEffect } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps<{
    src: string;
    agentName: string;
    copied: boolean;
}>();

const emit = defineEmits<{
    copy: [code: string];
}>();

const { t } = useI18n();

const iframeOptions = reactive({ width: '100%', height: '600px', title: '' });
watchEffect(() => {
    iframeOptions.title = t('agents.deploy.chat.iframe_title', { name: props.agentName });
});

const SIZES = {
    full: ['100%', '100vh'],
    large: ['800px', '600px'],
    medium: ['600px', '500px'],
    small: ['400px', '400px'],
} as const;

type Preset = keyof typeof SIZES;

const presets = computed<{ key: Preset; label: string }[]>(() => [
    { key: 'full', label: t('agents.deploy.chat.preset_full') },
    { key: 'large', label: t('agents.deploy.chat.preset_large') },
    { key: 'medium', label: t('agents.deploy.chat.preset_medium') },
    { key: 'small', label: t('agents.deploy.chat.preset_small') },
]);

function setPreset(preset: Preset): void {
    [iframeOptions.width, iframeOptions.height] = SIZES[preset];
}

const code = computed(() => iframeSnippet(props.src, iframeOptions));
</script>

<template>
    <div class="space-y-3 border-t pt-4">
        <Label class="text-sm font-medium">{{ t('agents.deploy.chat.embed_label') }}</Label>
        <div class="grid grid-cols-2 gap-4">
            <div class="space-y-1.5">
                <Label for="deploy-width" class="text-muted-foreground text-xs">{{ t('agents.deploy.chat.width') }}</Label>
                <div class="relative">
                    <div class="text-muted-foreground pointer-events-none absolute inset-y-0 start-0 flex items-center ps-2.5">
                        <MoveHorizontal class="size-3.5" />
                    </div>
                    <Input id="deploy-width" v-model="iframeOptions.width" type="text" class="ps-8 text-sm" />
                </div>
            </div>
            <div class="space-y-1.5">
                <Label for="deploy-height" class="text-muted-foreground text-xs">{{ t('agents.deploy.chat.height') }}</Label>
                <div class="relative">
                    <div class="text-muted-foreground pointer-events-none absolute inset-y-0 start-0 flex items-center ps-2.5">
                        <MoveVertical class="size-3.5" />
                    </div>
                    <Input id="deploy-height" v-model="iframeOptions.height" type="text" class="ps-8 text-sm" />
                </div>
            </div>
        </div>
        <div class="grid grid-cols-4 gap-2">
            <Button
                v-for="preset in presets"
                :key="preset.key"
                type="button"
                variant="outline"
                size="sm"
                class="text-xs"
                @click="setPreset(preset.key)"
                >{{ preset.label }}</Button
            >
        </div>
        <DeploySnippet :code="code" :copied="copied" wrap @copy="emit('copy', code)" />
    </div>
</template>
