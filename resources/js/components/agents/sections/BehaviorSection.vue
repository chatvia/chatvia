<script setup lang="ts">
import ColorThemePicker from '@/components/agents/ColorThemePicker.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Separator } from '@/components/ui/separator';
import { Switch } from '@/components/ui/switch';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

withDefaults(
    defineProps<{
        position: string;
        windowSize: string;
        showOnLoad: boolean;
        showOnLoadDelay: number;
        language?: string;
        themeColor?: string;
        themeShade?: number;
        availableLanguages?: Record<string, string>;
        showWidgetColor?: boolean;
        showLanguage?: boolean;
        errors?: Record<string, string | undefined>;
        disabled?: boolean;
    }>(),
    {
        language: 'auto',
        themeColor: 'black',
        themeShade: 700,
        availableLanguages: () => ({}),
        showWidgetColor: false,
        showLanguage: false,
        errors: () => ({}),
        disabled: false,
    },
);

defineEmits<{
    'update:position': [value: string];
    'update:windowSize': [value: string];
    'update:showOnLoad': [value: boolean];
    'update:showOnLoadDelay': [value: number];
    'update:language': [value: string];
    'update:themeColor': [value: string];
    'update:themeShade': [value: number];
    themeApply: [];
}>();
</script>

<template>
    <div class="space-y-6">
        <div v-if="showWidgetColor" class="space-y-2">
            <Label>{{ t('agents.setup.sections.behavior_widget_color') }}</Label>
            <p class="text-muted-foreground mb-3 text-xs">{{ t('agents.setup.sections.behavior_widget_color_hint') }}</p>
            <ColorThemePicker
                :theme="themeColor"
                :shade="themeShade"
                :disabled="disabled"
                @update:theme="$emit('update:themeColor', $event)"
                @update:shade="$emit('update:themeShade', $event)"
                @apply="$emit('themeApply')"
            />
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <div class="space-y-2">
                <Label>{{ t('agents.setup.behavior.position') }}</Label>
                <Select :model-value="position" :disabled="disabled" @update:model-value="$emit('update:position', String($event))">
                    <SelectTrigger class="w-full"><SelectValue /></SelectTrigger>
                    <SelectContent>
                        <SelectItem value="bottom-right">{{ t('agents.setup.options.position_bottom_right') }}</SelectItem>
                        <SelectItem value="bottom-left">{{ t('agents.setup.options.position_bottom_left') }}</SelectItem>
                        <SelectItem value="top-right">{{ t('agents.setup.options.position_top_right') }}</SelectItem>
                        <SelectItem value="top-left">{{ t('agents.setup.options.position_top_left') }}</SelectItem>
                    </SelectContent>
                </Select>
                <p class="text-muted-foreground text-xs">{{ t('agents.setup.sections.behavior_position_hint') }}</p>
            </div>
            <div class="space-y-2">
                <Label>{{ t('agents.setup.behavior.window_size') }}</Label>
                <Select :model-value="windowSize" :disabled="disabled" @update:model-value="$emit('update:windowSize', String($event))">
                    <SelectTrigger class="w-full"><SelectValue /></SelectTrigger>
                    <SelectContent>
                        <SelectItem value="small">{{ t('agents.setup.options.size_small') }}</SelectItem>
                        <SelectItem value="medium">{{ t('agents.setup.options.size_medium') }}</SelectItem>
                        <SelectItem value="large">{{ t('agents.setup.options.size_large') }}</SelectItem>
                    </SelectContent>
                </Select>
                <p class="text-muted-foreground text-xs">{{ t('agents.setup.sections.behavior_window_hint') }}</p>
            </div>
        </div>

        <Separator />

        <div class="space-y-4">
            <div class="flex items-center justify-between">
                <div class="space-y-0.5">
                    <Label>{{ t('agents.setup.sections.behavior_open_on_load') }}</Label>
                    <p class="text-muted-foreground text-xs">{{ t('agents.setup.sections.behavior_open_on_load_hint') }}</p>
                </div>
                <Switch :model-value="showOnLoad" :disabled="disabled" @update:model-value="$emit('update:showOnLoad', Boolean($event))" />
            </div>

            <div v-if="showOnLoad" class="space-y-2">
                <Label>{{ t('agents.setup.sections.behavior_delay') }}</Label>
                <Input
                    :model-value="showOnLoadDelay"
                    type="number"
                    min="0"
                    step="100"
                    placeholder="500"
                    :disabled="disabled"
                    class="max-w-40"
                    @update:model-value="$emit('update:showOnLoadDelay', Number($event) || 0)"
                />
                <p class="text-muted-foreground text-xs">{{ t('agents.setup.sections.behavior_delay_hint') }}</p>
            </div>
        </div>

        <template v-if="showLanguage">
            <Separator />
            <div class="space-y-2">
                <Label for="widget-language">{{ t('agents.setup.sections.behavior_widget_language') }}</Label>
                <Select :model-value="language" :disabled="disabled" @update:model-value="$emit('update:language', String($event))">
                    <SelectTrigger id="widget-language" class="w-full"><SelectValue /></SelectTrigger>
                    <SelectContent>
                        <SelectItem v-for="(label, code) in availableLanguages" :key="code" :value="code">{{ label }}</SelectItem>
                    </SelectContent>
                </Select>
                <p class="text-muted-foreground text-xs">
                    {{ t('agents.setup.sections.behavior_widget_language_hint') }}
                </p>
            </div>
        </template>
    </div>
</template>
