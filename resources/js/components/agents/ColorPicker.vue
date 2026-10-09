<script setup lang="ts">
/**
 * The theme-color grid and shade slider of the Branding section. Emits the
 * picked palette name and shade; the shade snaps to the nearest valid stop.
 */
import { Label } from '@/components/ui/label';
import { nearestShade, SHADES } from '@/lib/colorShades';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

const THEMES = [
    'black',
    'red',
    'orange',
    'amber',
    'yellow',
    'lime',
    'green',
    'emerald',
    'teal',
    'cyan',
    'sky',
    'blue',
    'indigo',
    'violet',
    'purple',
    'fuchsia',
    'pink',
    'rose',
    'slate',
    'gray',
    'zinc',
    'neutral',
    'stone',
] as const;

const props = withDefaults(
    defineProps<{
        theme: string;
        shade: number;
        disabled?: boolean;
    }>(),
    { disabled: false },
);

const emit = defineEmits<{
    'update:theme': [value: string];
    'update:shade': [value: number];
}>();

// Snap to the nearest valid stop so an out-of-range stored shade (e.g. a legacy
// 550) still previews a real color and the slider rests on a defined position.
const activeShade = computed(() => nearestShade(props.shade));
const shadeIndex = computed(() => SHADES.indexOf(activeShade.value));

function selectTheme(theme: string): void {
    if (props.disabled) return;
    emit('update:theme', theme);
}

function onShadeInput(event: Event): void {
    emit('update:shade', SHADES[Number((event.target as HTMLInputElement).value)]);
}
</script>

<template>
    <div class="space-y-6" :class="{ 'pointer-events-none opacity-60': disabled }">
        <div class="grid grid-cols-8 gap-2">
            <div v-for="theme in THEMES" :key="theme" class="group relative cursor-pointer" @click="selectTheme(theme)">
                <div
                    class="h-12 w-full rounded-lg border-2 shadow-sm transition-all duration-200"
                    :class="{
                        'border-primary ring-primary/20 ring-2': props.theme === theme,
                        'border-border hover:border-primary/50 hover:shadow-md': props.theme !== theme,
                    }"
                    :style="{ backgroundColor: `var(--${theme}-${activeShade})` }"
                >
                    <div class="flex h-full items-center justify-center">
                        <div v-if="props.theme === theme" class="flex h-6 w-6 items-center justify-center rounded-full bg-white shadow-lg">
                            <div class="h-3 w-3 rounded-full" :style="{ backgroundColor: `var(--${theme}-${activeShade})` }"></div>
                        </div>
                    </div>
                </div>
                <p
                    class="mt-1 text-center text-xs font-medium capitalize"
                    :class="{
                        'text-primary': props.theme === theme,
                        'text-muted-foreground': props.theme !== theme,
                    }"
                >
                    {{ theme }}
                </p>
            </div>
        </div>

        <div class="space-y-2">
            <Label class="text-sm">{{ t('shared.color_picker.color_intensity') }}</Label>
            <div class="px-1">
                <input
                    type="range"
                    min="0"
                    :max="SHADES.length - 1"
                    step="1"
                    :value="shadeIndex"
                    :disabled="disabled"
                    class="slider h-3 w-full cursor-pointer appearance-none rounded-lg bg-gray-200"
                    :style="{ background: `linear-gradient(to right, var(--${props.theme}-50), var(--${props.theme}-950))` }"
                    @input="onShadeInput"
                />
            </div>
            <div class="text-muted-foreground flex justify-between text-xs">
                <span>{{ t('shared.color_picker.light') }}</span>
                <span class="font-medium">{{ activeShade }}</span>
                <span>{{ t('shared.color_picker.dark') }}</span>
            </div>
        </div>
    </div>
</template>
