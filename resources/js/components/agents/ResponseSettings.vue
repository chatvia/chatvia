<script lang="ts" setup>
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Settings, Thermometer } from 'lucide-vue-next';
import { computed } from 'vue';

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
    set: (value) => emit('update:modelValue', value),
});

const updateSetting = (key: keyof ModelSettings, value: number) => {
    emit('update:modelValue', {
        ...settings.value,
        [key]: value,
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
        <CardHeader class="from-secondary/5 to-secondary/10 bg-gradient-to-r">
            <CardTitle class="flex items-center gap-3">
                <Settings class="text-secondary-foreground h-6 w-6" />
                Response Settings
            </CardTitle>
            <CardDescription class="text-foreground/80 text-base">
                Control how your AI agent responds. These settings will be applied to
                {{ agentType === 'widget' ? 'the selected model' : 'all selected models' }} and affect the style and creativity of all responses.
            </CardDescription>
        </CardHeader>
        <CardContent class="p-6">
            <div class="space-y-8">
                <!-- Section Header -->
                <div class="flex items-center gap-2">
                    <Thermometer class="text-primary h-5 w-5" />
                    <h3 class="text-lg font-semibold">Response Parameters</h3>
                </div>

                <!-- Temperature -->
                <div class="space-y-4">
                    <div class="flex items-center justify-between">
                        <Label class="text-base font-medium">Creativity Level <span class="text-muted-foreground text-sm">(Temperature)</span></Label>
                        <div class="flex items-center gap-2">
                            <span class="rounded px-2 py-1 text-sm font-medium" :class="getTemperatureColor(settings.temperature)">
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
                            class="range-slider h-2 w-full cursor-pointer appearance-none rounded-lg"
                            :class="{ 'border-destructive': errors?.temperature }"
                        />
                        <p v-if="errors?.temperature" class="text-destructive mt-1 text-sm">
                            {{ errors.temperature }}
                        </p>
                        <div class="text-muted-foreground mt-1 flex justify-between text-xs">
                            <span>Focused</span>
                            <span>Balanced</span>
                            <span>Creative</span>
                        </div>
                    </div>
                    <p class="text-muted-foreground text-sm">Low = predictable, consistent responses. High = more creative, varied answers.</p>
                </div>

                <!-- Top-K -->
                <div class="space-y-4">
                    <div class="flex items-center justify-between">
                        <Label class="text-base font-medium">Word Choice Variety <span class="text-muted-foreground text-sm">(Top-K)</span></Label>
                        <div class="flex items-center gap-2">
                            <span class="rounded px-2 py-1 text-sm font-medium" :class="getTopKColor(settings.top_k)">
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
                            class="range-slider w-full"
                            :class="{ 'border-destructive': errors?.top_k }"
                        />
                        <div class="text-muted-foreground mt-1 flex justify-between text-xs">
                            <span>Disabled</span>
                            <span>Focused</span>
                            <span>Balanced</span>
                            <span>Diverse</span>
                        </div>
                    </div>
                    <p v-if="errors?.top_k" class="text-destructive mt-1 text-sm">
                        {{ errors.top_k }}
                    </p>
                    <p class="text-muted-foreground text-sm">0 = disabled. Lower = uses more common words. Higher = considers more word options.</p>
                </div>

                <!-- Top-P -->
                <div class="space-y-4">
                    <div class="flex items-center justify-between">
                        <Label class="text-base font-medium">Response Focus <span class="text-muted-foreground text-sm">(Top-P)</span></Label>
                        <div class="flex items-center gap-2">
                            <span class="rounded px-2 py-1 text-sm font-medium" :class="getTopPColor(settings.top_p)">
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
                            class="range-slider w-full"
                            :class="{ 'border-destructive': errors?.top_p }"
                        />
                        <div class="text-muted-foreground mt-1 flex justify-between text-xs">
                            <span>Focused</span>
                            <span>Balanced</span>
                            <span>Broad</span>
                        </div>
                    </div>
                    <p v-if="errors?.top_p" class="text-destructive mt-1 text-sm">
                        {{ errors.top_p }}
                    </p>
                    <p class="text-muted-foreground text-sm">Lower = more focused responses. Higher = broader, more diverse responses.</p>
                </div>

                <!-- Presence Penalty -->
                <div class="space-y-4">
                    <div class="flex items-center justify-between">
                        <Label class="text-base font-medium"
                            >Avoid Repeating Topics <span class="text-muted-foreground text-sm">(Presence Penalty)</span></Label
                        >
                        <div class="flex items-center gap-2">
                            <span class="rounded px-2 py-1 text-sm font-medium" :class="getPenaltyColor(settings.presence_penalty)">
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
                            class="range-slider w-full"
                            :class="{ 'border-destructive': errors?.presence_penalty }"
                        />
                        <div class="text-muted-foreground mt-1 flex justify-between text-xs">
                            <span>Repetitive</span>
                            <span>Neutral</span>
                            <span>Diverse</span>
                        </div>
                    </div>
                    <p v-if="errors?.presence_penalty" class="text-destructive mt-1 text-sm">
                        {{ errors.presence_penalty }}
                    </p>
                    <p class="text-muted-foreground text-sm">Higher values discourage repeating the same topics or ideas.</p>
                </div>

                <!-- Frequency Penalty -->
                <div class="space-y-4">
                    <div class="flex items-center justify-between">
                        <Label class="text-base font-medium"
                            >Avoid Repeating Words <span class="text-muted-foreground text-sm">(Frequency Penalty)</span></Label
                        >
                        <div class="flex items-center gap-2">
                            <span class="rounded px-2 py-1 text-sm font-medium" :class="getPenaltyColor(settings.frequency_penalty)">
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
                            class="range-slider w-full"
                            :class="{ 'border-destructive': errors?.frequency_penalty }"
                        />
                        <div class="text-muted-foreground mt-1 flex justify-between text-xs">
                            <span>Repetitive</span>
                            <span>Neutral</span>
                            <span>Varied</span>
                        </div>
                    </div>
                    <p v-if="errors?.frequency_penalty" class="text-destructive mt-1 text-sm">
                        {{ errors.frequency_penalty }}
                    </p>
                    <p class="text-muted-foreground text-sm">Higher values discourage using the same words multiple times.</p>
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
