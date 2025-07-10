<script lang="ts" setup>
import { ref, computed, onMounted } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import AgentLayout from '@/layouts/AgentLayout.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import { Checkbox } from '@/components/ui/checkbox';
import { Separator } from '@/components/ui/separator';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import {
    Brain,
    Zap,
    Settings,
    Thermometer,
    Target,
    Hash,
    Clock,
    RotateCcw,
    CheckCircle2,
    Sparkles,
    Loader2,
} from 'lucide-vue-next';

interface AIModel {
    id: string;
    name: string;
    context: number;
    icon: string;
    description: string;
    features?: string[];
}

interface ModelSettings {
    selected_models: string[];
    temperature: number;
    top_k: number;
    top_p: number;
    max_tokens: number;
    presence_penalty: number;
    frequency_penalty: number;
}

const props = defineProps<{
    agent: {
        id: string;
        name: string;
        type: string;
        is_active: boolean;
        is_public: boolean;
    };
    currentSettings?: ModelSettings;
}>();

// Reactive state for models
const models = ref<AIModel[]>([]);
const isLoadingModels = ref(true);
const modelsError = ref<string | null>(null);

const form = useForm({
    selected_models: props.currentSettings?.selected_models || [],
    temperature: props.currentSettings?.temperature || 0.7,
    top_k: props.currentSettings?.top_k || 40,
    top_p: props.currentSettings?.top_p || 0.9,
    max_tokens: props.currentSettings?.max_tokens || 2048,
    presence_penalty: props.currentSettings?.presence_penalty || 0,
    frequency_penalty: props.currentSettings?.frequency_penalty || 0,
});

const selectedModelsCount = computed(() => form.selected_models.length);

// Fetch models from API
const fetchModels = async () => {
    try {
        isLoadingModels.value = true;
        modelsError.value = null;

        const response = await axios.get(route('agents.models.list', { agent: props.agent.id }));
        models.value = response.data;
    } catch (error) {
        console.error('Failed to fetch models:', error);
        modelsError.value = 'Failed to load available models. Please try again.';
    } finally {
        isLoadingModels.value = false;
    }
};

const toggleModel = (modelId: string) => {
    const index = form.selected_models.indexOf(modelId);
    if (index > -1) {
        form.selected_models.splice(index, 1);
    } else {
        form.selected_models.push(modelId);
    }
};

const isModelSelected = (modelId: string) => {
    return form.selected_models.includes(modelId);
};

const submit = () => {
    form.post(route('agents.models.update', { agent: props.agent.id }));
};

const resetToDefaults = () => {
    form.temperature = 0.7;
    form.top_k = 40;
    form.top_p = 0.9;
    form.max_tokens = 2048;
    form.presence_penalty = 0;
    form.frequency_penalty = 0;
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

const getFeatureBadgeVariant = (feature: string) => {
    const featureLower = feature.toLowerCase();

    if (featureLower === 'advanced' || featureLower === 'pro') {
        return 'default';
    }
    if (featureLower === 'fast') {
        return 'secondary';
    }
    if (featureLower === 'new' || featureLower === 'beta') {
        return 'destructive';
    }
    return 'outline';
};

onMounted(() => {
    fetchModels();
});
</script>

<template>
    <AgentLayout :agent="agent" title="AI Models">
        <TooltipProvider>
            <div class="space-y-6">

                <form @submit.prevent="submit" class="space-y-8">
                    <!-- Model Selection - Now First -->
                    <Card class="overflow-hidden">
                        <CardHeader>
                            <CardTitle class="flex items-center gap-3">
                                <Sparkles class="h-6 w-6 text-primary" />
                                Available AI Models
                            </CardTitle>
                            <CardDescription class="text-base text-foreground/80">
                                Select one or more AI models for your agent. The parameters configured below will be applied to all selected models. Each model has unique capabilities and performance characteristics.
                            </CardDescription>
                        </CardHeader>
                        <CardContent class="p-6">
                            <!-- Loading State -->
                            <div v-if="isLoadingModels" class="text-center py-12">
                                <Loader2 class="h-8 w-8 text-muted-foreground/50 mx-auto mb-3 animate-spin" />
                                <p class="text-muted-foreground">Loading available models...</p>
                            </div>

                            <!-- Error State -->
                            <div v-else-if="modelsError" class="text-center py-12">
                                <div class="bg-destructive/10 border border-destructive/20 rounded-lg p-6">
                                    <p class="text-destructive font-medium mb-3">{{ modelsError }}</p>
                                    <Button @click="fetchModels" variant="outline" size="sm">
                                        <RotateCcw class="h-4 w-4 mr-2" />
                                        Retry
                                    </Button>
                                </div>
                            </div>

                            <!-- Models Grid -->
                            <div v-else-if="models.length > 0" class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                                <div
                                    v-for="model in models"
                                    :key="model.id"
                                    class="group relative"
                                >
                                    <div
                                        class="border-2 rounded-xl p-6 transition-all duration-200 cursor-pointer hover:shadow-lg"
                                        :class="[
                                            isModelSelected(model.id)
                                                ? 'border-primary bg-primary/5 shadow-lg ring-4 ring-primary/20'
                                                : 'border-border hover:border-primary/30 hover:bg-accent/20'
                                        ]"
                                        @click="toggleModel(model.id)"
                                    >
                                        <!-- Selection Indicator -->
                                        <div class="absolute top-4 right-4">
                                            <div
                                                class="w-6 h-6 rounded-full border-2 flex items-center justify-center transition-all"
                                                :class="[
                                                    isModelSelected(model.id)
                                                        ? 'border-primary bg-primary text-primary-foreground'
                                                        : 'border-muted-foreground/30 group-hover:border-primary/50'
                                                ]"
                                            >
                                                <CheckCircle2
                                                    v-if="isModelSelected(model.id)"
                                                    class="h-4 w-4"
                                                />
                                            </div>
                                        </div>

                                        <!-- Model Info -->
                                        <div class="space-y-3">
                                            <div class="flex items-start gap-3">
                                                <div
                                                    class="w-12 h-12 rounded-lg flex items-center justify-center transition-all"
                                                    :class="[
                                                        isModelSelected(model.id)
                                                            ? 'bg-primary/10'
                                                            : 'bg-muted group-hover:bg-primary/10'
                                                    ]"
                                                >
                                                    <img
                                                        v-if="model.icon"
                                                        :src="`/images/llms/${model.icon}`"
                                                        :alt="model.name"
                                                        class="h-6 w-6"
                                                    />
                                                    <Brain v-else class="h-6 w-6" />
                                                </div>
                                                <div class="flex-1 min-w-0">
                                                    <h3 class="font-semibold text-lg">{{ model.name }}</h3>
                                                    <Tooltip>
                                                        <TooltipTrigger asChild>
                                                            <p class="text-sm text-muted-foreground mt-1 line-clamp-2 cursor-help">
                                                                {{ model.description }}
                                                            </p>
                                                        </TooltipTrigger>
                                                        <TooltipContent class="max-w-xs">
                                                            <p>{{ model.description }}</p>
                                                        </TooltipContent>
                                                    </Tooltip>
                                                </div>
                                            </div>

                                            <div class="flex items-center justify-between pt-2">
                                                <div class="flex items-center gap-1.5">
                                                    <Badge
                                                        variant="secondary"
                                                        class="text-[10px] font-medium px-1.5 py-0.5 h-auto"
                                                        :class="[
                                                            isModelSelected(model.id)
                                                                ? 'bg-primary/20 text-primary'
                                                                : ''
                                                        ]"
                                                    >
                                                        {{ model.context.toLocaleString() }} tokens
                                                    </Badge>

                                                    <!-- Features -->
                                                    <template v-if="model.features && model.features.length > 0">
                                                        <Badge
                                                            v-for="feature in model.features.slice(0, 2)"
                                                            :key="feature"
                                                            :variant="getFeatureBadgeVariant(feature)"
                                                            class="text-[10px] capitalize px-1.5 py-0.5 h-auto"
                                                        >
                                                            {{ feature }}
                                                        </Badge>
                                                        <Badge
                                                            v-if="model.features.length > 2"
                                                            variant="outline"
                                                            class="text-[10px] px-1.5 py-0.5 h-auto"
                                                        >
                                                            +{{ model.features.length - 2 }}
                                                        </Badge>
                                                    </template>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- No Models Available -->
                            <div v-else class="text-center py-12">
                                <Brain class="h-12 w-12 text-muted-foreground/50 mx-auto mb-3" />
                                <p class="text-muted-foreground">No models available</p>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Model Parameters - Now Second -->
                    <Card class="overflow-hidden">
                        <CardHeader class="bg-gradient-to-r from-secondary/5 to-secondary/10">
                            <CardTitle class="flex items-center gap-3">
                                <Settings class="h-6 w-6 text-secondary-foreground" />
                                Model Parameters
                            </CardTitle>
                            <CardDescription class="text-base text-foreground/80">
                                Configure general parameters that will be applied to all AI models used by this agent. These settings control how models behave and generate responses.
                            </CardDescription>
                        </CardHeader>
                        <CardContent class="p-6">
                            <div class="grid gap-8 lg:grid-cols-2">
                                <!-- Response Behavior -->
                                <div class="space-y-6">
                                    <div class="flex items-center gap-2 mb-4">
                                        <Thermometer class="h-5 w-5 text-primary" />
                                        <h3 class="text-lg font-semibold">Response Behavior</h3>
                                    </div>

                                    <!-- Temperature -->
                                    <div class="space-y-4">
                                        <div class="flex items-center justify-between">
                                            <Label class="text-base font-medium">Temperature</Label>
                                            <div class="flex items-center gap-2">
                                                <span
                                                    class="text-sm font-medium px-2 py-1 rounded"
                                                    :class="getTemperatureColor(form.temperature)"
                                                >
                                                    {{ getTemperatureLabel(form.temperature) }}
                                                </span>
                                                <Badge variant="outline">{{ form.temperature }}</Badge>
                                            </div>
                                        </div>
                                        <div class="relative">
                                            <Input
                                                v-model.number="form.temperature"
                                                type="range"
                                                :min="0"
                                                :max="2"
                                                :step="0.1"
                                                class="w-full h-2 rounded-lg appearance-none cursor-pointer range-slider"
                                            />
                                            <div class="flex justify-between text-xs text-muted-foreground mt-1">
                                                <span>Focused</span>
                                                <span>Balanced</span>
                                                <span>Creative</span>
                                            </div>
                                        </div>
                                        <p class="text-sm text-muted-foreground">
                                            Controls randomness. Lower values make responses more focused and deterministic.
                                        </p>
                                    </div>

                                    <!-- Top-K -->
                                    <div class="space-y-4">
                                        <div class="flex items-center justify-between">
                                            <Label class="text-base font-medium">Top-K Sampling</Label>
                                            <Badge variant="outline">{{ form.top_k }}</Badge>
                                        </div>
                                        <Input
                                            v-model.number="form.top_k"
                                            type="range"
                                            :min="1"
                                            :max="100"
                                            :step="1"
                                            class="w-full range-slider"
                                        />
                                        <p class="text-sm text-muted-foreground">
                                            Limits model to consider only the K most likely next tokens.
                                        </p>
                                    </div>

                                    <!-- Top-P -->
                                    <div class="space-y-4">
                                        <div class="flex items-center justify-between">
                                            <Label class="text-base font-medium">Top-P (Nucleus)</Label>
                                            <Badge variant="outline">{{ form.top_p }}</Badge>
                                        </div>
                                        <Input
                                            v-model.number="form.top_p"
                                            type="range"
                                            :min="0.1"
                                            :max="1"
                                            :step="0.05"
                                            class="w-full range-slider"
                                        />
                                        <p class="text-sm text-muted-foreground">
                                            Considers tokens with cumulative probability up to P.
                                        </p>
                                    </div>
                                </div>

                                <!-- Limits & Penalties -->
                                <div class="space-y-6">
                                    <div class="flex items-center gap-2 mb-4">
                                        <Target class="h-5 w-5 text-primary" />
                                        <h3 class="text-lg font-semibold">Limits & Penalties</h3>
                                    </div>

                                    <!-- Max Tokens -->
                                    <div class="space-y-4">
                                        <div class="flex items-center justify-between">
                                            <Label class="text-base font-medium">Max Tokens</Label>
                                            <Badge variant="outline">{{ form.max_tokens.toLocaleString() }}</Badge>
                                        </div>
                                        <Input
                                            v-model.number="form.max_tokens"
                                            type="number"
                                            :min="1"
                                            :max="8192"
                                            class="w-full"
                                        />
                                        <p class="text-sm text-muted-foreground">
                                            Maximum number of tokens in the response.
                                        </p>
                                    </div>

                                    <!-- Presence Penalty -->
                                    <div class="space-y-4">
                                        <div class="flex items-center justify-between">
                                            <Label class="text-base font-medium">Presence Penalty</Label>
                                            <Badge variant="outline">{{ form.presence_penalty }}</Badge>
                                        </div>
                                        <Input
                                            v-model.number="form.presence_penalty"
                                            type="range"
                                            :min="-2"
                                            :max="2"
                                            :step="0.1"
                                            class="w-full range-slider"
                                        />
                                        <p class="text-sm text-muted-foreground">
                                            Reduces repetition by penalizing tokens that already appeared.
                                        </p>
                                    </div>

                                    <!-- Frequency Penalty -->
                                    <div class="space-y-4">
                                        <div class="flex items-center justify-between">
                                            <Label class="text-base font-medium">Frequency Penalty</Label>
                                            <Badge variant="outline">{{ form.frequency_penalty }}</Badge>
                                        </div>
                                        <Input
                                            v-model.number="form.frequency_penalty"
                                            type="range"
                                            :min="-2"
                                            :max="2"
                                            :step="0.1"
                                            class="w-full range-slider"
                                        />
                                        <p class="text-sm text-muted-foreground">
                                            Reduces repetition based on token frequency.
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Actions -->
                    <div class="flex items-center justify-between pt-6">
                        <Button
                            type="button"
                            variant="outline"
                            @click="resetToDefaults"
                            class="gap-2"
                        >
                            <RotateCcw class="h-4 w-4" />
                            Reset Parameters to Defaults
                        </Button>

                        <Button
                            type="submit"
                            :disabled="form.processing"
                            class="gap-2 min-w-[160px]"
                            size="lg"
                        >
                            <Zap v-if="!form.processing" class="h-4 w-4" />
                            <div v-else class="h-4 w-4 rounded-full border-2 border-white/30 border-t-white animate-spin" />
                            {{ form.processing ? 'Saving...' : 'Save Configuration' }}
                        </Button>
                    </div>
                </form>
            </div>
        </TooltipProvider>
    </AgentLayout>
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

.line-clamp-2 {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
</style>
