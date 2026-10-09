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

type Theme = (typeof THEMES)[number];

// Literal keys (not built from the theme name) so the i18n key check sees each one.
const themeLabels = computed<Record<Theme, string>>(() => ({
    black: t('shared.color_picker.colors.black'),
    red: t('shared.color_picker.colors.red'),
    orange: t('shared.color_picker.colors.orange'),
    amber: t('shared.color_picker.colors.amber'),
    yellow: t('shared.color_picker.colors.yellow'),
    lime: t('shared.color_picker.colors.lime'),
    green: t('shared.color_picker.colors.green'),
    emerald: t('shared.color_picker.colors.emerald'),
    teal: t('shared.color_picker.colors.teal'),
    cyan: t('shared.color_picker.colors.cyan'),
    sky: t('shared.color_picker.colors.sky'),
    blue: t('shared.color_picker.colors.blue'),
    indigo: t('shared.color_picker.colors.indigo'),
    violet: t('shared.color_picker.colors.violet'),
    purple: t('shared.color_picker.colors.purple'),
    fuchsia: t('shared.color_picker.colors.fuchsia'),
    pink: t('shared.color_picker.colors.pink'),
    rose: t('shared.color_picker.colors.rose'),
    slate: t('shared.color_picker.colors.slate'),
    gray: t('shared.color_picker.colors.gray'),
    zinc: t('shared.color_picker.colors.zinc'),
    neutral: t('shared.color_picker.colors.neutral'),
    stone: t('shared.color_picker.colors.stone'),
}));

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

function selectTheme(theme: Theme): void {
    if (props.disabled) return;
    emit('update:theme', theme);
}

function onShadeInput(event: Event): void {
    emit('update:shade', SHADES[Number((event.target as HTMLInputElement).value)]);
}
</script>

<template>
    <div class="space-y-6" :class="{ 'pointer-events-none opacity-60': disabled }">
        <div class="grid grid-cols-8 gap-2" role="group" :aria-label="t('shared.color_theme_picker.title')">
            <button
                v-for="theme in THEMES"
                :key="theme"
                type="button"
                class="group focus-visible:ring-ring/50 relative cursor-pointer rounded-lg outline-none focus-visible:ring-[3px]"
                :aria-pressed="props.theme === theme"
                :disabled="disabled"
                @click="selectTheme(theme)"
            >
                <span
                    class="block h-12 w-full rounded-lg border-2 shadow-sm transition-all duration-200"
                    :class="{
                        'border-primary ring-primary/20 ring-2': props.theme === theme,
                        'border-border hover:border-primary/50 hover:shadow-md': props.theme !== theme,
                    }"
                    :style="{ backgroundColor: `var(--${theme}-${activeShade})` }"
                >
                    <span class="flex h-full items-center justify-center">
                        <span v-if="props.theme === theme" class="flex h-6 w-6 items-center justify-center rounded-full bg-white shadow-lg">
                            <span class="h-3 w-3 rounded-full" :style="{ backgroundColor: `var(--${theme}-${activeShade})` }"></span>
                        </span>
                    </span>
                </span>
                <span
                    class="mt-1 block text-center text-xs font-medium"
                    :class="{
                        'text-primary': props.theme === theme,
                        'text-muted-foreground': props.theme !== theme,
                    }"
                >
                    {{ themeLabels[theme] }}
                </span>
            </button>
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
                    :aria-label="t('shared.color_picker.color_intensity')"
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
