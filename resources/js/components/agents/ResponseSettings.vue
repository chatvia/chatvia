<script lang="ts" setup>
import { computed } from 'vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import { Settings, Thermometer } from 'lucide-vue-next';

interface ModelSettings {
    temperature: number;
    top_k: number;
    top_p: number;
    presence_penalty: number;
    frequency_penalty: number;
}

interface Props {
    modelValue: ModelSettings;
    errors?: Record<string, string>;
    agentType?: string;
}

const props = defineProps<Props>();

const emit = defineEmits<{
    'update:modelValue': [value: ModelSettings];
}>();

const settings = computed({
    get: () => props.modelValue,
    set: (value) => emit('update:modelValue', value)
});

const updateSetting = (key: keyof ModelSettings, value: number) => {
    emit('update:modelValue', {
        ...settings.value,
        [key]: value
    });
};

const getTemperatureColor = (temp: number) => {
    if (temp <= 0.3) return 'text-blue-500';
    if (temp <= 0.7) return 'text-green-500';
    return 'text-orange-500';
};

const getTemperatureLabel = (temp: number) => {
    if (temp <= 0.3) return 'Conservative';
    if (temp <= 0.7) return 'Balanced';
    return 'Creative';
};

const getTopKColor = (topK: number) => {
    if (topK === 0) return 'text-purple-500';
    if (topK <= 20) return 'text-blue-500';
    if (topK <= 60) return 'text-green-500';
    return 'text-orange-500';
};

const getTopKLabel = (topK: number) => {
    if (topK === 0) return 'Disabled';
    if (topK <= 20) return 'Focused';
    if (topK <= 60) return 'Balanced';
    return 'Diverse';
};

const getTopPColor = (topP: number) => {
    if (topP <= 0.5) return 'text-blue-500';
    if (topP <= 0.8) return 'text-green-500';
    return 'text-orange-500';
};

const getTopPLabel = (topP: number) => {
    if (topP <= 0.5) return 'Focused';
    if (topP <= 0.8) return 'Balanced';
    return 'Broad';
};

const getPenaltyColor = (penalty: number) => {
    if (penalty < -0.1) return 'text-red-500';
    if (penalty <= 0.1) return 'text-gray-500';
    return 'text-green-500';
};

const getPresencePenaltyLabel = (penalty: number) => {
    if (penalty < -0.1) return 'Repetitive';
    if (penalty <= 0.1) return 'Neutral';
    return 'Diverse';
};

const getFrequencyPenaltyLabel = (penalty: number) => {
    if (penalty < -0.1) return 'Repetitive';
    if (penalty <= 0.1) return 'Neutral';
    return 'Varied';
};
</script>

<template>
    <Card class="overflow-hidden">
        <CardHeader class="bg-gradient-to-r from-secondary/5 to-secondary/10">
            <CardTitle class="flex items-center gap-3">
                <Settings class="h-6 w-6 text-secondary-foreground" />
                Response Settings
            </CardTitle>
            <CardDescription class="text-base text-foreground/80">
                Control how your AI agent responds. These settings will be applied to {{ agentType === 'widget' ? 'the selected model' : 'all selected models' }} and affect the style and creativity of all responses.
            </CardDescription>
        </CardHeader>
        <CardContent class="p-6">
            <div class="space-y-8">
                <!-- Section Header -->
                <div class="flex items-center gap-2">
                    <Thermometer class="h-5 w-5 text-primary" />
                    <h3 class="text-lg font-semibold">Response Parameters</h3>
                </div>

                <!-- Temperature -->
                <div class="space-y-4">
                    <div class="flex items-center justify-between">
                        <Label class="text-base font-medium">Creativity Level <span class="text-sm text-muted-foreground">(Temperature)</span></Label>
                        <div class="flex items-center gap-2">
                            <span
                                class="text-sm font-medium px-2 py-1 rounded"
                                :class="getTemperatureColor(settings.temperature)"
                            >
                                {{ getTemperatureLabel(settings.temperature) }}
                            </span>
                            <Badge variant="outline">{{ settings.temperature }}</Badge>
                        </div>
                    </div>
                    <div class="relative">
                        <Input
                            :model-value="settings.temperature"
                            @update:model-value="(val) => updateSetting('temperature', Number(val))"
                            type="range"
                            :min="0"
                            :max="2"
                            :step="0.1"
                            class="w-full h-2 rounded-lg appearance-none cursor-pointer range-slider"
                            :class="{ 'border-destructive': errors?.temperature }"
                        />
                        <p v-if="errors?.temperature" class="text-sm text-destructive mt-1">
                            {{ errors.temperature }}
                        </p>
                        <div class="flex justify-between text-xs text-muted-foreground mt-1">
                            <span>Focused</span>
                            <span>Balanced</span>
                            <span>Creative</span>
                        </div>
                    </div>
                    <p class="text-sm text-muted-foreground">
                        Low = predictable, consistent responses. High = more creative, varied answers.
                    </p>
                </div>

                <!-- Top-K -->
                <div class="space-y-4">
                    <div class="flex items-center justify-between">
                        <Label class="text-base font-medium">Word Choice Variety <span class="text-sm text-muted-foreground">(Top-K)</span></Label>
                        <div class="flex items-center gap-2">
                            <span
                                class="text-sm font-medium px-2 py-1 rounded"
                                :class="getTopKColor(settings.top_k)"
                            >
                                {{ getTopKLabel(settings.top_k) }}
                            </span>
                            <Badge variant="outline">{{ settings.top_k }}</Badge>
                        </div>
                    </div>
                    <div class="relative">
                        <Input
                            :model-value="settings.top_k"
                            @update:model-value="(val) => updateSetting('top_k', Number(val))"
                            type="range"
                            :min="0"
                            :max="100"
                            :step="1"
                            class="w-full range-slider"
                            :class="{ 'border-destructive': errors?.top_k }"
                        />
                        <div class="flex justify-between text-xs text-muted-foreground mt-1">
                            <span>Disabled</span>
                            <span>Focused</span>
                            <span>Balanced</span>
                            <span>Diverse</span>
                        </div>
                    </div>
                    <p v-if="errors?.top_k" class="text-sm text-destructive mt-1">
                        {{ errors.top_k }}
                    </p>
                    <p class="text-sm text-muted-foreground">
                        0 = disabled. Lower = uses more common words. Higher = considers more word options.
                    </p>
                </div>

                <!-- Top-P -->
                <div class="space-y-4">
                    <div class="flex items-center justify-between">
                        <Label class="text-base font-medium">Response Focus <span class="text-sm text-muted-foreground">(Top-P)</span></Label>
                        <div class="flex items-center gap-2">
                            <span
                                class="text-sm font-medium px-2 py-1 rounded"
                                :class="getTopPColor(settings.top_p)"
                            >
                                {{ getTopPLabel(settings.top_p) }}
                            </span>
                            <Badge variant="outline">{{ settings.top_p }}</Badge>
                        </div>
                    </div>
                    <div class="relative">
                        <Input
                            :model-value="settings.top_p"
                            @update:model-value="(val) => updateSetting('top_p', Number(val))"
                            type="range"
                            :min="0.1"
                            :max="1"
                            :step="0.05"
                            class="w-full range-slider"
                            :class="{ 'border-destructive': errors?.top_p }"
                        />
                        <div class="flex justify-between text-xs text-muted-foreground mt-1">
                            <span>Focused</span>
                            <span>Balanced</span>
                            <span>Broad</span>
                        </div>
                    </div>
                    <p v-if="errors?.top_p" class="text-sm text-destructive mt-1">
                        {{ errors.top_p }}
                    </p>
                    <p class="text-sm text-muted-foreground">
                        Lower = more focused responses. Higher = broader, more diverse responses.
                    </p>
                </div>

                <!-- Presence Penalty -->
                <div class="space-y-4">
                    <div class="flex items-center justify-between">
                        <Label class="text-base font-medium">Avoid Repeating Topics <span class="text-sm text-muted-foreground">(Presence Penalty)</span></Label>
                        <div class="flex items-center gap-2">
                            <span
                                class="text-sm font-medium px-2 py-1 rounded"
                                :class="getPenaltyColor(settings.presence_penalty)"
                            >
                                {{ getPresencePenaltyLabel(settings.presence_penalty) }}
                            </span>
                            <Badge variant="outline">{{ settings.presence_penalty }}</Badge>
                        </div>
                    </div>
                    <div class="relative">
                        <Input
                            :model-value="settings.presence_penalty"
                            @update:model-value="(val) => updateSetting('presence_penalty', Number(val))"
                            type="range"
                            :min="-2"
                            :max="2"
                            :step="0.1"
                            class="w-full range-slider"
                            :class="{ 'border-destructive': errors?.presence_penalty }"
                        />
                        <div class="flex justify-between text-xs text-muted-foreground mt-1">
                            <span>Repetitive</span>
                            <span>Neutral</span>
                            <span>Diverse</span>
                        </div>
                    </div>
                    <p v-if="errors?.presence_penalty" class="text-sm text-destructive mt-1">
                        {{ errors.presence_penalty }}
                    </p>
                    <p class="text-sm text-muted-foreground">
                        Higher values discourage repeating the same topics or ideas.
                    </p>
                </div>

                <!-- Frequency Penalty -->
                <div class="space-y-4">
                    <div class="flex items-center justify-between">
                        <Label class="text-base font-medium">Avoid Repeating Words <span class="text-sm text-muted-foreground">(Frequency Penalty)</span></Label>
                        <div class="flex items-center gap-2">
                            <span
                                class="text-sm font-medium px-2 py-1 rounded"
                                :class="getPenaltyColor(settings.frequency_penalty)"
                            >
                                {{ getFrequencyPenaltyLabel(settings.frequency_penalty) }}
                            </span>
                            <Badge variant="outline">{{ settings.frequency_penalty }}</Badge>
                        </div>
                    </div>
                    <div class="relative">
                        <Input
                            :model-value="settings.frequency_penalty"
                            @update:model-value="(val) => updateSetting('frequency_penalty', Number(val))"
                            type="range"
                            :min="-2"
                            :max="2"
                            :step="0.1"
                            class="w-full range-slider"
                            :class="{ 'border-destructive': errors?.frequency_penalty }"
                        />
                        <div class="flex justify-between text-xs text-muted-foreground mt-1">
                            <span>Repetitive</span>
                            <span>Neutral</span>
                            <span>Varied</span>
                        </div>
                    </div>
                    <p v-if="errors?.frequency_penalty" class="text-sm text-destructive mt-1">
                        {{ errors.frequency_penalty }}
                    </p>
                    <p class="text-sm text-muted-foreground">
                        Higher values discourage using the same words multiple times.
                    </p>
                </div>
            </div>
        </CardContent>
    </Card>
</template>

<style scoped>
/* Custom styling for range inputs */
.range-slider {
    -webkit-appearance: none;
    appearance: none;
    background: transparent;
    cursor: pointer;
    height: 30px;
}

.range-slider::-webkit-slider-track {
    background: hsl(var(--muted));
    height: 6px;
    border-radius: 3px;
}

.range-slider::-webkit-slider-thumb {
    -webkit-appearance: none;
    appearance: none;
    background: hsl(var(--primary));
    height: 20px;
    width: 20px;
    border-radius: 50%;
    border: 2px solid hsl(var(--background));
    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.2);
    cursor: pointer;
}

.range-slider::-webkit-slider-thumb:hover {
    background: hsl(var(--primary) / 0.8);
    transform: scale(1.1);
}

.range-slider::-moz-range-track {
    background: hsl(var(--muted));
    height: 6px;
    border-radius: 3px;
    border: none;
}

.range-slider::-moz-range-thumb {
    background: hsl(var(--primary));
    height: 20px;
    width: 20px;
    border-radius: 50%;
    border: 2px solid hsl(var(--background));
    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.2);
    cursor: pointer;
}
</style>

