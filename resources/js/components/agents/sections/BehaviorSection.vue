<script setup lang="ts">
import ColorThemePicker from '@/components/agents/ColorThemePicker.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Separator } from '@/components/ui/separator';
import { Switch } from '@/components/ui/switch';

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
            <Label>Widget theme color</Label>
            <p class="text-muted-foreground mb-3 text-xs">Click the color swatch to choose the widget's primary color</p>
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
                <Label>Position</Label>
                <Select :model-value="position" :disabled="disabled" @update:model-value="$emit('update:position', String($event))">
                    <SelectTrigger class="w-full"><SelectValue /></SelectTrigger>
                    <SelectContent>
                        <SelectItem value="bottom-right">Bottom right</SelectItem>
                        <SelectItem value="bottom-left">Bottom left</SelectItem>
                        <SelectItem value="top-right">Top right</SelectItem>
                        <SelectItem value="top-left">Top left</SelectItem>
                    </SelectContent>
                </Select>
                <p class="text-muted-foreground text-xs">Where the widget appears on the page</p>
            </div>
            <div class="space-y-2">
                <Label>Window size</Label>
                <Select :model-value="windowSize" :disabled="disabled" @update:model-value="$emit('update:windowSize', String($event))">
                    <SelectTrigger class="w-full"><SelectValue /></SelectTrigger>
                    <SelectContent>
                        <SelectItem value="small">Small</SelectItem>
                        <SelectItem value="medium">Medium</SelectItem>
                        <SelectItem value="large">Large</SelectItem>
                    </SelectContent>
                </Select>
                <p class="text-muted-foreground text-xs">Chat window dimensions</p>
            </div>
        </div>

        <Separator />

        <div class="space-y-4">
            <div class="flex items-center justify-between">
                <div class="space-y-0.5">
                    <Label>Open on page load</Label>
                    <p class="text-muted-foreground text-xs">Automatically show the widget when the page loads.</p>
                </div>
                <Switch :model-value="showOnLoad" :disabled="disabled" @update:model-value="$emit('update:showOnLoad', Boolean($event))" />
            </div>

            <div v-if="showOnLoad" class="space-y-2">
                <Label>Open delay (ms)</Label>
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
                <p class="text-muted-foreground text-xs">Time to wait before showing the widget</p>
            </div>
        </div>

        <template v-if="showLanguage">
            <Separator />
            <div class="space-y-2">
                <Label for="widget-language">Widget language</Label>
                <Select :model-value="language" :disabled="disabled" @update:model-value="$emit('update:language', String($event))">
                    <SelectTrigger id="widget-language" class="w-full"><SelectValue /></SelectTrigger>
                    <SelectContent>
                        <SelectItem v-for="(label, code) in availableLanguages" :key="code" :value="code">{{ label }}</SelectItem>
                    </SelectContent>
                </Select>
                <p class="text-muted-foreground text-xs">
                    Choose the language for the widget interface. Auto-detect uses the visitor's browser language.
                </p>
            </div>
        </template>
    </div>
</template>
