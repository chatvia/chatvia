<script lang="ts" setup>
import { ref, onMounted } from 'vue';
import { Link } from '@inertiajs/vue3';
import axios from 'axios';
import AgentLayout from '@/layouts/AgentLayout.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import {
    Brain,
    HelpCircle,
    CheckCircle2,
    Loader2,
    RotateCcw,
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
const currentSettings = ref<ModelSettings | null>(null);
const defaultSettings = ref<ModelSettings | null>(null);
const toggleLoadingModels = ref<Set<string>>(new Set());

const fetchModels = async () => {
    try {
        isLoadingModels.value = true;
        modelsError.value = null;

        const response = await axios.get<ModelsResponse>(route('agents.models.list', { agent: props.agent.id }));
        models.value = response.data.models;
        currentSettings.value = response.data.settings;
        defaultSettings.value = response.data.default_settings;
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


onMounted(() => {
    fetchModels();
});
</script>

<template>
    <AgentLayout 
        :agent="agent" 
        title="AI Models"
        description="Select and configure AI models for your agent to use when generating responses"
    >
        <TooltipProvider>
            <div class="space-y-6">
                <div class="flex justify-center">
                    <!-- Model Selection -->
                    <Card class="w-full max-w-6xl overflow-hidden">
                        <CardHeader>
                            <CardTitle class="flex items-center gap-3">
                                <Brain class="h-6 w-6 text-primary" />
                                Choose AI Models
                            </CardTitle>
                            <CardDescription class="text-base">
                                {{ props.agent.type === 'widget'
                                    ? 'Click on a model to select it for your widget agent.'
                                    : 'Click on models to enable or disable them for your agent.'
                                }}
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
                            <div v-else-if="models.length > 0" class="grid gap-4 grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                                <div
                                    v-for="model in models"
                                    :key="model.id"
                                    class="group relative"
                                >
                                    <button
                                        type="button"
                                        class="w-full border rounded-lg p-5 transition-all duration-200 text-left"
                                        :class="[
                                            isModelDisabled(model)
                                                ? 'border-muted bg-muted/30 opacity-60 cursor-not-allowed'
                                                : isModelToggling(model)
                                                    ? 'border-muted bg-muted/10 opacity-75 cursor-wait'
                                                    : isModelSelected(model)
                                                        ? 'border-primary bg-primary/5 shadow-lg ring-2 ring-primary/20'
                                                        : 'border-border hover:border-primary/50 hover:bg-accent/30 cursor-pointer hover:shadow-md'
                                        ]"
                                        @click="toggleModel(model)"
                                        :disabled="isModelDisabled(model) || isModelToggling(model)"
                                    >
                                        <div class="space-y-4">
                                            <!-- Top: Icon and Selection Status -->
                                            <div class="flex items-start justify-between gap-3">
                                                <!-- Icon -->
                                                <div class="flex-shrink-0">
                                                    <img
                                                        v-if="model.icon"
                                                        :src="`/images/llms/${model.icon}`"
                                                        :alt="model.name"
                                                        class="h-10 w-10"
                                                        :class="[
                                                            isModelDisabled(model) ? 'opacity-50' : ''
                                                        ]"
                                                    />
                                                    <Brain
                                                        v-else
                                                        class="h-10 w-10"
                                                        :class="[
                                                            isModelDisabled(model)
                                                                ? 'text-muted-foreground/50'
                                                                : 'text-muted-foreground'
                                                        ]"
                                                    />
                                                </div>

                                                <!-- Selection Indicator -->
                                                <div
                                                    class="w-6 h-6 rounded-full border-2 flex items-center justify-center transition-all flex-shrink-0"
                                                    :class="[
                                                        isModelDisabled(model)
                                                            ? 'border-muted-foreground/20 bg-muted/50'
                                                            : isModelSelected(model)
                                                                ? 'border-primary bg-primary text-primary-foreground'
                                                                : 'border-muted-foreground/30 group-hover:border-primary/70'
                                                    ]"
                                                >
                                                    <!-- Loading Spinner -->
                                                    <Loader2
                                                        v-if="isModelToggling(model)"
                                                        class="h-4 w-4 animate-spin"
                                                    />
                                                    <!-- Checkmark -->
                                                    <CheckCircle2
                                                        v-else-if="isModelSelected(model) && !isModelDisabled(model)"
                                                        class="h-4 w-4"
                                                    />
                                                </div>
                                            </div>

                                            <!-- Middle: Model Name -->
                                            <div>
                                                <h3 class="font-semibold text-base leading-tight">{{ model.name }}</h3>
                                            </div>

                                            <!-- Bottom: Learn More Button -->
                                            <div class="flex items-center justify-between pt-2 border-t">
                                                <Tooltip>
                                                    <TooltipTrigger asChild>
                                                        <div class="flex items-center gap-1 text-xs text-muted-foreground hover:text-foreground transition-colors">
                                                            <HelpCircle class="h-3.5 w-3.5" />
                                                            <span>Details</span>
                                                        </div>
                                                    </TooltipTrigger>
                                                    <TooltipContent class="max-w-sm" side="top">
                                                        <div class="space-y-2">
                                                            <p class="font-medium">{{ model.name }}</p>
                                                            <p class="text-sm">{{ model.description }}</p>
                                                            <div v-if="model.reasoning || (model.features && model.features.length > 0)" class="pt-2 border-t space-y-1">
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
                                                
                                                <Link
                                                    v-if="isModelDisabled(model)"
                                                    :href="route('billing.packages')"
                                                    class="inline-block"
                                                    @click.stop
                                                >
                                                    <Badge
                                                        variant="outline"
                                                        class="text-[10px] cursor-pointer hover:bg-primary hover:text-primary-foreground transition-colors"
                                                    >
                                                        Upgrade
                                                    </Badge>
                                                </Link>
                                            </div>
                                        </div>
                                    </button>
                                </div>
                            </div>

                            <!-- No Models Available -->
                            <div v-else class="text-center py-12">
                                <Brain class="h-12 w-12 text-muted-foreground/50 mx-auto mb-3" />
                                <p class="text-muted-foreground">No models available</p>
                            </div>

                        </CardContent>
                    </Card>
                </div>
            </div>
        </TooltipProvider>
    </AgentLayout>
</template>

