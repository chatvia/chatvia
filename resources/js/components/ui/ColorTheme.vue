<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { Label } from '@/components/ui/label';

const { t } = useI18n();

interface Props {
    modelValue: string;
    showShading?: boolean;
    shade?: number;
    label?: string;
    description?: string;
    error?: string;
}

interface Emits {
    (e: 'update:modelValue', value: string): void;
    (e: 'update:shade', value: number): void;
}

const props = withDefaults(defineProps<Props>(), {
    showShading: true,
    shade: 500,
    label: 'Color Theme',
    description: 'Choose a color theme',
});

const emit = defineEmits<Emits>();

const colorThemes = [
    'black', 'red', 'orange', 'amber', 'yellow', 'lime', 'green', 'emerald', 
    'teal', 'cyan', 'sky', 'blue', 'indigo', 'violet', 'purple', 'fuchsia', 
    'pink', 'rose', 'slate', 'gray', 'zinc', 'neutral', 'stone'
];

const currentShade = computed(() => props.shade);

const selectTheme = (theme: string) => {
    emit('update:modelValue', theme);
};

const updateShade = (event: Event) => {
    if (!props.showShading) return;
    
    const target = event.target as HTMLInputElement;
    const value = parseInt(target.value);
    const validShades = [50, 100, 200, 300, 400, 500, 600, 700, 800, 900, 950];
    const closest = validShades.reduce((prev, curr) => 
        Math.abs(curr - value) < Math.abs(prev - value) ? curr : prev
    );
    emit('update:shade', closest);
};
</script>

<template>
    <div class="space-y-4">
        <div class="grid gap-2">
            <Label>{{ label }}</Label>
            
            <!-- Color Grid -->
            <div class="grid grid-cols-8 gap-2">
                <div
                    v-for="theme in colorThemes"
                    :key="theme"
                    class="relative cursor-pointer group"
                    @click="selectTheme(theme)"
                >
                    <div
                        class="h-12 w-full rounded-lg border-2 transition-all duration-200 shadow-sm"
                        :class="{
                            'border-primary ring-2 ring-primary/20': modelValue === theme,
                            'border-border hover:border-primary/50 hover:shadow-md': modelValue !== theme
                        }"
                        :style="{
                            backgroundColor: showShading ? `var(--${theme}-${currentShade})` : `var(--${theme}-500)`
                        }"
                    >
                        <div class="flex h-full items-center justify-center">
                            <div
                                v-if="modelValue === theme"
                                class="h-6 w-6 rounded-full bg-white shadow-lg flex items-center justify-center transform scale-100 transition-transform duration-200"
                            >
                                <div 
                                    class="h-3 w-3 rounded-full" 
                                    :style="{ 
                                        backgroundColor: showShading ? `var(--${theme}-${currentShade})` : `var(--${theme}-500)` 
                                    }"
                                ></div>
                            </div>
                            <div
                                v-else
                                class="h-6 w-6 rounded-full bg-white/80 shadow-sm flex items-center justify-center transform scale-75 opacity-0 group-hover:opacity-100 group-hover:scale-100 transition-all duration-200"
                            >
                                <div 
                                    class="h-3 w-3 rounded-full" 
                                    :style="{ 
                                        backgroundColor: showShading ? `var(--${theme}-${currentShade})` : `var(--${theme}-500)` 
                                    }"
                                ></div>
                            </div>
                        </div>
                    </div>
                    <p 
                        class="mt-1 text-center text-xs font-medium capitalize transition-colors duration-200" 
                        :class="{
                            'text-primary': modelValue === theme,
                            'text-muted-foreground': modelValue !== theme
                        }"
                    >
                        {{ theme }}
                    </p>
                </div>
            </div>

            <!-- Color Shade Slider (only if shading is enabled) -->
            <div v-if="showShading" class="space-y-2">
                <Label class="text-sm">{{ t('shared.color_picker.color_intensity') }}</Label>
                <div class="px-1">
                    <input
                        type="range"
                        min="50"
                        max="950"
                        step="50"
                        :value="currentShade"
                        class="w-full h-3 bg-gray-200 rounded-lg appearance-none cursor-pointer slider"
                        :style="{
                            background: `linear-gradient(to right, var(--${modelValue}-50), var(--${modelValue}-950))`
                        }"
                        @input="updateShade"
                    />
                </div>
                <div class="flex justify-between text-xs text-muted-foreground">
                    <span>{{ t('shared.color_picker.light') }}</span>
                    <span class="font-medium">{{ currentShade }}</span>
                    <span>{{ t('shared.color_picker.dark') }}</span>
                </div>
            </div>
            
            <p v-if="error" class="text-sm text-destructive">{{ error }}</p>
            <p v-if="description" class="text-xs text-muted-foreground">{{ description }}</p>
        </div>
    </div>
</template>

<style scoped>
/* Custom slider styling */
.slider::-webkit-slider-thumb {
    appearance: none;
    height: 20px;
    width: 20px;
    border-radius: 50%;
    background: #ffffff;
    border: 2px solid #e2e8f0;
    cursor: pointer;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    transition: all 0.2s ease;
}

.slider::-webkit-slider-thumb:hover {
    transform: scale(1.1);
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
}

.slider::-moz-range-thumb {
    height: 20px;
    width: 20px;
    border-radius: 50%;
    background: #ffffff;
    border: 2px solid #e2e8f0;
    cursor: pointer;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}
</style>
