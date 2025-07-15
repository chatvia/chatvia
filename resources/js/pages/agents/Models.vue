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
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import FormSuccessMessage from '@/components/FormSuccessMessage.vue';
import {
    Brain,
    Zap,
    Settings,
    Thermometer,
    Target,

    RotateCcw,
    CheckCircle2,
    Sparkles,
    Loader2,
} from 'lucide-vue-next';

interface AIModel {
    id: string;
    name: string;
    context_window: number;
    icon: string;
    description: string;
    features?: string[];
    location: string;
    reasoning: boolean;
    user_allowed_to_use: boolean;
    is_selected: boolean;
}

interface ModelSettings {
    temperature: number;
    top_k: number;
    top_p: number;
    presence_penalty: number;
    frequency_penalty: number;
}

interface ModelsResponse {
    models: AIModel[];
    settings: ModelSettings;
    default_settings: ModelSettings;
}

const props = defineProps<{
    agent: {
        id: string;
        name: string;
        type: string;
        is_active: boolean;
        is_public: boolean;
    };
}>();

// Reactive state for models
const models = ref<AIModel[]>([]);
const isLoadingModels = ref(true);
const modelsError = ref<string | null>(null);
const showSuccessMessage = ref(false);
const currentSettings = ref<ModelSettings | null>(null);
const defaultSettings = ref<ModelSettings | null>(null);

const form = useForm({
    temperature: 1.0,
    top_k: 0,
    top_p: 1.0,
    presence_penalty: 0.0,
    frequency_penalty: 0.0,
});

const fetchModels = async () => {
    try {
        isLoadingModels.value = true;
        modelsError.value = null;

        const response = await axios.get<ModelsResponse>(route('agents.models.list', { agent: props.agent.id }));
        models.value = response.data.models;
        currentSettings.value = response.data.settings;
        defaultSettings.value = response.data.default_settings;
        
        // Update form with settings from API
        form.temperature = response.data.settings.temperature;
        form.top_k = response.data.settings.top_k;
        form.top_p = response.data.settings.top_p;
        form.presence_penalty = response.data.settings.presence_penalty;
        form.frequency_penalty = response.data.settings.frequency_penalty;
    } catch (error) {
        console.error('Failed to fetch models:', error);
        modelsError.value = 'Failed to load available models. Please try again.';
    } finally {
        isLoadingModels.value = false;
    }
};

const toggleModel = async (model: AIModel) => {
    if (!model.user_allowed_to_use) return;

    try {
        // Send PATCH request to toggle model status
        const newStatus = !model.is_selected;
        await axios.patch(route('agents.models.toggle', { agent: props.agent.id }), {
            model_id: model.id,
            status: newStatus
        });

        // Update the local model state
        const modelIndex = models.value.findIndex(m => m.id === model.id);
        if (modelIndex !== -1) {
            models.value[modelIndex].is_selected = newStatus;
        }
    } catch (error) {
        console.error('Failed to toggle model:', error);
        // You could add a toast notification here to inform the user of the error
    }
};

const isModelSelected = (model: AIModel) => {
    return model.is_selected && model.user_allowed_to_use;
};

const isModelDisabled = (model: AIModel) => {
    return !model.user_allowed_to_use;
};

const submit = () => {
    // Include selected models in the form data
    const selectedModelIds = models.value
        .filter(model => model.is_selected && model.user_allowed_to_use)
        .map(model => model.id);

    form.transform((data) => ({
        ...data,
        selected_models: selectedModelIds,
    })).patch(route('agents.models.settings.update', { agent: props.agent.id }), {
        preserveScroll: true,
        onSuccess: () => {
            showSuccessMessage.value = true;
            setTimeout(() => {
                showSuccessMessage.value = false;
            }, 3000);
        }
    });
};

const resetToDefaults = () => {
    if (defaultSettings.value) {
        form.temperature = defaultSettings.value.temperature;
        form.top_k = defaultSettings.value.top_k;
        form.top_p = defaultSettings.value.top_p;
        form.presence_penalty = defaultSettings.value.presence_penalty;
        form.frequency_penalty = defaultSettings.value.frequency_penalty;
    }
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

                            <!-- Info about disabled models -->
                            <div v-if="models.some(m => !m.user_allowed_to_use)" class="mt-4 p-3 bg-muted/50 rounded-lg border">
                                <p class="text-sm text-muted-foreground">
                                    <strong>Note:</strong> Some models may show as "Not Available" based on your current subscription plan or account settings.
                                </p>
                            </div>
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
                                        class="border-2 rounded-xl p-6 transition-all duration-200"
                                        :class="[
                                            isModelDisabled(model)
                                                ? 'border-muted bg-muted/30 opacity-60 cursor-not-allowed'
                                                : isModelSelected(model)
                                                    ? 'border-primary bg-primary/5 shadow-md cursor-pointer'
                                                    : 'border-border hover:border-primary/30 hover:bg-accent/20 cursor-pointer hover:shadow-lg'
                                        ]"
                                        @click="toggleModel(model)"
                                    >
                                        <!-- Selection Indicator -->
                                        <div class="absolute top-4 right-4">
                                            <div
                                                class="w-6 h-6 rounded-full border-2 flex items-center justify-center transition-all"
                                                :class="[
                                                    isModelDisabled(model)
                                                        ? 'border-muted-foreground/20 bg-muted/50'
                                                        : isModelSelected(model)
                                                            ? 'border-primary bg-primary text-primary-foreground'
                                                            : 'border-muted-foreground/30 group-hover:border-primary/50'
                                                ]"
                                            >
                                                <CheckCircle2
                                                    v-if="isModelSelected(model) && !isModelDisabled(model)"
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
                                                        isModelDisabled(model)
                                                            ? 'bg-muted/50'
                                                            : isModelSelected(model)
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
                                                    <div class="flex items-center gap-2">
                                                        <h3 class="font-semibold text-lg">{{ model.name }}</h3>
                                                    </div>
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
                                                            isModelDisabled(model)
                                                                ? 'bg-muted/50 text-muted-foreground/50'
                                                                : isModelSelected(model)
                                                                    ? 'bg-primary/20 text-primary'
                                                                    : ''
                                                        ]"
                                                    >
                                                        {{ model.context_window.toLocaleString() }} tokens
                                                    </Badge>

                                                    <!-- Features -->
                                                    <template v-if="model.features && model.features.length > 0">
                                                        <Badge
                                                            v-for="feature in model.features.slice(0, 2)"
                                                            :key="feature"
                                                            :variant="isModelDisabled(model) ? 'outline' : getFeatureBadgeVariant(feature)"
                                                            class="text-[10px] capitalize px-1.5 py-0.5 h-auto"
                                                            :class="isModelDisabled(model) ? 'opacity-50' : ''"
                                                        >
                                                            {{ feature }}
                                                        </Badge>
                                                        <Badge
                                                            v-if="model.features.length > 2"
                                                            variant="outline"
                                                            class="text-[10px] px-1.5 py-0.5 h-auto"
                                                            :class="isModelDisabled(model) ? 'opacity-50' : ''"
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
                            
                            <!-- Model selection errors -->
                            <div v-if="(form.errors as any)['selected_models']" class="mt-4 p-3 bg-destructive/10 border border-destructive/20 rounded-lg">
                                <p class="text-sm text-destructive">{{ (form.errors as any)['selected_models'] }}</p>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Model Parameters - Now Second -->
                    <Card class="overflow-hidden">
                        <CardHeader class="bg-gradient-to-r from-secondary/5 to-secondary/10">
                            <CardTitle class="flex items-center gap-3">
                                <Settings class="h-6 w-6 text-secondary-foreground" />
                                Response Settings
                            </CardTitle>
                            <CardDescription class="text-base text-foreground/80">
                                Control how your AI agent responds. These settings will be applied to all selected models above and affect the style and creativity of all responses.
                            </CardDescription>
                        </CardHeader>
                        <CardContent class="p-6">
                            <div class="grid gap-8 lg:grid-cols-2">
                                <!-- Creativity & Style -->
                                <div class="space-y-6">
                                    <div class="flex items-center gap-2 mb-4">
                                        <Thermometer class="h-5 w-5 text-primary" />
                                        <h3 class="text-lg font-semibold">Creativity & Style</h3>
                                    </div>

                                    <!-- Temperature -->
                                    <div class="space-y-4">
                                        <div class="flex items-center justify-between">
                                            <Label class="text-base font-medium">Creativity Level <span class="text-sm text-muted-foreground">(Temperature)</span></Label>
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
                                            :class="{ 'border-destructive': form.errors.temperature }"
                                        />
                                        <p v-if="form.errors.temperature" class="text-sm text-destructive mt-1">
                                            {{ form.errors.temperature }}
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
                                                    :class="getTopKColor(form.top_k)"
                                                >
                                                    {{ getTopKLabel(form.top_k) }}
                                                </span>
                                                <Badge variant="outline">{{ form.top_k }}</Badge>
                                            </div>
                                        </div>
                                        <div class="relative">
                                            <Input
                                                v-model.number="form.top_k"
                                                type="range"
                                                :min="0"
                                                :max="100"
                                                :step="1"
                                                class="w-full range-slider"
                                                :class="{ 'border-destructive': form.errors.top_k }"
                                            />
                                            <div class="flex justify-between text-xs text-muted-foreground mt-1">
                                                <span>Disabled</span>
                                                <span>Focused</span>
                                                <span>Balanced</span>
                                                <span>Diverse</span>
                                            </div>
                                        </div>
                                        <p v-if="form.errors.top_k" class="text-sm text-destructive mt-1">
                                            {{ form.errors.top_k }}
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
                                                    :class="getTopPColor(form.top_p)"
                                                >
                                                    {{ getTopPLabel(form.top_p) }}
                                                </span>
                                                <Badge variant="outline">{{ form.top_p }}</Badge>
                                            </div>
                                        </div>
                                        <div class="relative">
                                            <Input
                                                v-model.number="form.top_p"
                                                type="range"
                                                :min="0.1"
                                                :max="1"
                                                :step="0.05"
                                                class="w-full range-slider"
                                                :class="{ 'border-destructive': form.errors.top_p }"
                                            />
                                            <div class="flex justify-between text-xs text-muted-foreground mt-1">
                                                <span>Focused</span>
                                                <span>Balanced</span>
                                                <span>Broad</span>
                                            </div>
                                        </div>
                                        <p v-if="form.errors.top_p" class="text-sm text-destructive mt-1">
                                            {{ form.errors.top_p }}
                                        </p>
                                        <p class="text-sm text-muted-foreground">
                                            Lower = more focused responses. Higher = broader, more diverse responses.
                                        </p>
                                    </div>
                                </div>

                                <!-- Repetition Control -->
                                <div class="space-y-6">
                                    <div class="flex items-center gap-2 mb-4">
                                        <Target class="h-5 w-5 text-primary" />
                                        <h3 class="text-lg font-semibold">Repetition Control</h3>
                                    </div>



                                    <!-- Presence Penalty -->
                                    <div class="space-y-4">
                                        <div class="flex items-center justify-between">
                                            <Label class="text-base font-medium">Avoid Repeating Topics <span class="text-sm text-muted-foreground">(Presence Penalty)</span></Label>
                                            <div class="flex items-center gap-2">
                                                <span
                                                    class="text-sm font-medium px-2 py-1 rounded"
                                                    :class="getPenaltyColor(form.presence_penalty)"
                                                >
                                                    {{ getPresencePenaltyLabel(form.presence_penalty) }}
                                                </span>
                                                <Badge variant="outline">{{ form.presence_penalty }}</Badge>
                                            </div>
                                        </div>
                                        <div class="relative">
                                            <Input
                                                v-model.number="form.presence_penalty"
                                                type="range"
                                                :min="-2"
                                                :max="2"
                                                :step="0.1"
                                                class="w-full range-slider"
                                                :class="{ 'border-destructive': form.errors.presence_penalty }"
                                            />
                                            <div class="flex justify-between text-xs text-muted-foreground mt-1">
                                                <span>Repetitive</span>
                                                <span>Neutral</span>
                                                <span>Diverse</span>
                                            </div>
                                        </div>
                                        <p v-if="form.errors.presence_penalty" class="text-sm text-destructive mt-1">
                                            {{ form.errors.presence_penalty }}
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
                                                    :class="getPenaltyColor(form.frequency_penalty)"
                                                >
                                                    {{ getFrequencyPenaltyLabel(form.frequency_penalty) }}
                                                </span>
                                                <Badge variant="outline">{{ form.frequency_penalty }}</Badge>
                                            </div>
                                        </div>
                                        <div class="relative">
                                            <Input
                                                v-model.number="form.frequency_penalty"
                                                type="range"
                                                :min="-2"
                                                :max="2"
                                                :step="0.1"
                                                class="w-full range-slider"
                                                :class="{ 'border-destructive': form.errors.frequency_penalty }"
                                            />
                                            <div class="flex justify-between text-xs text-muted-foreground mt-1">
                                                <span>Repetitive</span>
                                                <span>Neutral</span>
                                                <span>Varied</span>
                                            </div>
                                        </div>
                                        <p v-if="form.errors.frequency_penalty" class="text-sm text-destructive mt-1">
                                            {{ form.errors.frequency_penalty }}
                                        </p>
                                        <p class="text-sm text-muted-foreground">
                                            Higher values discourage using the same words multiple times.
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
                            Reset to Recommended Settings
                        </Button>

                                                <div class="flex items-center gap-3">
                            <FormSuccessMessage 
                                :show="showSuccessMessage"
                                message="Settings saved successfully"
                            />
                            
                            <!-- General form errors -->
                            <div v-if="Object.keys(form.errors).length > 0 && !showSuccessMessage" class="text-sm text-destructive">
                                Please fix the errors above and try again.
                            </div>
                            
                            <Button
                                type="submit"
                                :disabled="form.processing"
                                class="gap-2 min-w-[160px]"
                                size="lg"
                            >
                                <Zap v-if="!form.processing" class="h-4 w-4" />
                                <div v-else class="h-4 w-4 rounded-full border-2 border-white/30 border-t-white animate-spin" />
                                {{ form.processing ? 'Saving...' : 'Save Settings' }}
                            </Button>
                        </div>
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
