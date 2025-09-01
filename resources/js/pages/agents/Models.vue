<script lang="ts" setup>
import { ref, computed, onMounted } from 'vue';
import { useForm, usePage, Link } from '@inertiajs/vue3';
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
import HeaderActions from '@/components/HeaderActions.vue';
import {
    Brain,
    Zap,
    Settings,
    Thermometer,
    Target,
    HelpCircle,
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
const toggleLoadingModels = ref<Set<string>>(new Set());

const form = useForm({
    temperature: 1.0,
    top_k: 0,
    top_p: 1.0,
    presence_penalty: 0.0,
    frequency_penalty: 0.0,
});

// Computed properties for header actions
const hasUnsavedChanges = computed(() => {
    return (
        form.temperature !== 1.0 ||
        form.top_k !== 0 ||
        form.top_p !== 1.0 ||
        form.presence_penalty !== 0.0 ||
        form.frequency_penalty !== 0.0
    );
});

const hasFormErrors = computed(() => {
    return Object.keys(form.errors).length > 0;
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
    if (!model.user_allowed_to_use || toggleLoadingModels.value.has(model.id)) return;

    // Add to loading set
    toggleLoadingModels.value.add(model.id);

    try {
        // For widget agents, handle single selection differently
        if (props.agent.type === 'widget') {
            // If this model is already selected, don't allow deselection (must have one selected)
            if (model.is_selected) {
                toggleLoadingModels.value.delete(model.id);
                return;
            }

            // Deselect all other models first
            for (const otherModel of models.value) {
                if (otherModel.id !== model.id && otherModel.is_selected) {
                    const otherModelIndex = models.value.findIndex(m => m.id === otherModel.id);
                    if (otherModelIndex !== -1) {
                        models.value[otherModelIndex].is_selected = false;
                    }

                    // Send request to deselect other model
                    await axios.patch(route('agents.models.toggle', { agent: props.agent.id }), {
                        model_id: otherModel.id,
                        status: false
                    });
                }
            }
        }

        // Optimistic update - immediately update UI
        const originalStatus = model.is_selected;
        const newStatus = !model.is_selected;

        const modelIndex = models.value.findIndex(m => m.id === model.id);
        if (modelIndex !== -1) {
            models.value[modelIndex].is_selected = newStatus;
        }

        // Send PATCH request to toggle model status
        await axios.patch(route('agents.models.toggle', { agent: props.agent.id }), {
            model_id: model.id,
            status: newStatus
        });

    } catch (error) {
        console.error('Failed to toggle model:', error);

        // Revert optimistic update on error
        const modelIndex = models.value.findIndex(m => m.id === model.id);
        if (modelIndex !== -1) {
            models.value[modelIndex].is_selected = !models.value[modelIndex].is_selected;
        }

        alert('Failed to update model. Please try again.');
    } finally {
        // Remove from loading set
        toggleLoadingModels.value.delete(model.id);
    }
};

const isModelSelected = (model: AIModel) => {
    return model.is_selected && model.user_allowed_to_use;
};

const isModelDisabled = (model: AIModel) => {
    return !model.user_allowed_to_use;
};

const isModelToggling = (model: AIModel) => {
    return toggleLoadingModels.value.has(model.id);
};

const submit = () => {
    // Include selected models in the form data for all agent types
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
        <template #header-actions>
            <HeaderActions
                :is-submitting="form.processing"
                :has-unsaved-changes="hasUnsavedChanges"
                :show-success-message="showSuccessMessage"
                success-message="Settings saved successfully"
                :show-error-message="hasFormErrors"
                error-message="Please fix errors above"
                save-label="Save Settings"
                :save-icon="Zap"
                form-id="models-form"
                :secondary-actions="[
                    {
                        id: 'reset-defaults',
                        label: 'Reset to Recommended',
                        icon: RotateCcw,
                        onClick: resetToDefaults
                    }
                ]"
            />
        </template>
        <TooltipProvider>
            <form id="models-form" @submit.prevent="submit">
                <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">
                    <!-- Left Column - Model Selection -->
                    <div class="space-y-6">
                        <Card class="overflow-hidden">
                        <CardHeader>
                            <CardTitle class="flex items-center gap-3">
                                <Brain class="h-6 w-6 text-primary" />
                                AI Models
                            </CardTitle>
                            <CardDescription class="text-base text-foreground/80">
                                {{ props.agent.type === 'widget'
                                    ? 'Select an AI model for your widget agent. Click on a model card to select it. Only one model can be active at a time for widget agents. Hover over the info icons to learn more about each model\'s capabilities.'
                                    : 'Select one or more AI models for your agent. Click on model cards to enable/disable them and hover over the info icons to learn more about each model\'s capabilities.'
                                }}
                            </CardDescription>

                            <!-- Info about disabled models -->
                            <div v-if="models.some(m => !m.user_allowed_to_use)" class="mt-4 p-3 bg-muted/50 rounded-lg border">
                                <p class="text-sm text-muted-foreground">
                                    <strong>Note:</strong> Some models may show as disabled based on your current subscription plan or account settings.
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
                            <div v-else-if="models.length > 0" class="grid gap-4 grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-3">
                                <div
                                    v-for="model in models"
                                    :key="model.id"
                                    class="group relative"
                                >
                                    <div
                                        class="border rounded-lg p-4 transition-all duration-200"
                                        :class="[
                                            isModelDisabled(model)
                                                ? 'border-muted bg-muted/30 opacity-60 cursor-not-allowed'
                                                : isModelToggling(model)
                                                    ? 'border-muted bg-muted/10 opacity-75 cursor-wait'
                                                    : isModelSelected(model)
                                                        ? props.agent.type === 'widget'
                                                            ? 'border-primary bg-primary/5 shadow-md cursor-default'
                                                            : 'border-primary bg-primary/5 shadow-md cursor-pointer'
                                                        : 'border-border hover:border-primary/30 hover:bg-accent/20 cursor-pointer hover:shadow-lg'
                                        ]"
                                        @click="toggleModel(model)"
                                    >
                                        <!-- Model Info -->
                                        <div class="space-y-3">
                                            <!-- Header Row: Icon and Selection Indicator -->
                                            <div class="flex items-center justify-between">
                                                <!-- Icon -->
                                                <div class="flex-shrink-0">
                                                    <img
                                                        v-if="model.icon"
                                                        :src="`/images/llms/${model.icon}`"
                                                        :alt="model.name"
                                                        class="h-8 w-8"
                                                        :class="[
                                                            isModelDisabled(model) ? 'opacity-50' : ''
                                                        ]"
                                                    />
                                                    <Brain
                                                        v-else
                                                        class="h-8 w-8"
                                                        :class="[
                                                            isModelDisabled(model)
                                                                ? 'text-muted-foreground/50'
                                                                : 'text-muted-foreground'
                                                        ]"
                                                    />
                                                </div>

                                                <!-- Selection Indicator -->
                                                <div
                                                    class="w-5 h-5 rounded-full border flex items-center justify-center transition-all flex-shrink-0"
                                                    :class="[
                                                        isModelDisabled(model)
                                                            ? 'border-muted-foreground/20 bg-muted/50'
                                                            : isModelSelected(model)
                                                                ? 'border-primary bg-primary text-primary-foreground'
                                                                : 'border-muted-foreground/30 group-hover:border-primary/50'
                                                    ]"
                                                >
                                                    <!-- Loading Spinner -->
                                                    <div
                                                        v-if="isModelToggling(model)"
                                                        class="h-3 w-3 rounded-full border border-white/30 border-t-white animate-spin"
                                                    />
                                                    <!-- Checkmark -->
                                                    <CheckCircle2
                                                        v-else-if="isModelSelected(model) && !isModelDisabled(model)"
                                                        class="h-3 w-3"
                                                    />
                                                </div>
                                            </div>

                                            <!-- Bottom Row: Model Name and Help Button -->
                                            <div class="flex items-center justify-between">
                                                <div class="flex items-center gap-2">
                                                    <h3 class="font-medium text-sm leading-tight">{{ model.name }}</h3>
                                                    <!-- Active indicator for widget agents -->
                                                    <Badge
                                                        v-if="props.agent.type === 'widget' && isModelSelected(model)"
                                                        variant="secondary"
                                                        class="text-[10px] h-auto py-0.5 px-1.5"
                                                    >
                                                        Active
                                                    </Badge>
                                                </div>

                                                <!-- Help Button -->
                                                <Tooltip>
                                                    <TooltipTrigger asChild>
                                                        <button type="button" class="p-1 rounded-full hover:bg-muted transition-colors flex-shrink-0">
                                                            <HelpCircle class="h-4 w-4 text-muted-foreground" />
                                                        </button>
                                                    </TooltipTrigger>
                                                    <TooltipContent class="max-w-sm" side="top">
                                                        <div class="space-y-2">
                                                            <p class="font-medium">{{ model.name }}</p>
                                                            <p class="text-sm">{{ model.description }}</p>
                                                            <div class="pt-2 border-t space-y-1">
                                                                <div class="flex justify-between text-xs">
                                                                    <span class="text-muted-foreground">Context Window:</span>
                                                                    <span>{{ model.context_window.toLocaleString() }} tokens</span>
                                                                </div>
                                                                <div class="flex justify-between text-xs">
                                                                    <span class="text-muted-foreground">Location:</span>
                                                                    <span>{{ model.location }}</span>
                                                                </div>
                                                                <div v-if="model.reasoning" class="flex justify-between text-xs">
                                                                    <span class="text-muted-foreground">Reasoning:</span>
                                                                    <span class="text-emerald-600">✓ Supported</span>
                                                                </div>
                                                                <div v-if="model.features && model.features.length > 0" class="pt-1">
                                                                    <div class="text-xs text-muted-foreground mb-1">Features:</div>
                                                                    <div class="flex flex-wrap gap-1">
                                                                        <Badge
                                                                            v-for="feature in model.features"
                                                                            :key="feature"
                                                                            variant="secondary"
                                                                            class="text-[10px] h-auto py-0.5"
                                                                        >
                                                                            {{ feature }}
                                                                        </Badge>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </TooltipContent>
                                                </Tooltip>
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
                    </div>

                    <!-- Right Column - Response Settings -->
                    <div class="space-y-6">
                        <Card class="overflow-hidden">
                        <CardHeader class="bg-gradient-to-r from-secondary/5 to-secondary/10">
                            <CardTitle class="flex items-center gap-3">
                                <Settings class="h-6 w-6 text-secondary-foreground" />
                                Response Settings
                            </CardTitle>
                            <CardDescription class="text-base text-foreground/80">
                                Control how your AI agent responds. These settings will be applied to {{ props.agent.type === 'widget' ? 'the selected model' : 'all selected models' }} above and affect the style and creativity of all responses.
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
                        </CardContent>
                    </Card>
                    </div>
                </div>
            </form>
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
    line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
</style>
