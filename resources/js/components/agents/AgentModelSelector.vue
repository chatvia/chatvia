<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import axios from 'axios';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Input } from '@/components/ui/input';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { Brain, Loader2, Save, ChevronDown, Search, Check, Info } from 'lucide-vue-next';
import { toast } from 'vue-sonner';
import { Alert, AlertDescription } from '@/components/ui/alert';

type AIModel = App.Data.Chat.LlmModelData;

const props = withDefaults(defineProps<{
    agentId: number | string;
    description?: string;
    multiple?: boolean;
}>(), {
    multiple: false,
});

const emit = defineEmits<{
    modelChanged: [model: AIModel];
    saved: [];
}>();

// Models state
const models = ref<AIModel[]>([]);
const isLoadingModels = ref(false);
const modelsError = ref<string | null>(null);
const isSaving = ref(false);

// Selection state
const selectedModelId = ref<string>('');
const selectedModelIds = ref<string[]>([]); // Use array for better reactivity
const searchQuery = ref('');
const isDropdownOpen = ref(false);

// Track original selection to detect changes
const originalSelectedIds = ref<Set<string>>(new Set());

const hasChanges = computed(() => {
    if (props.multiple) {
        if (selectedModelIds.value.length !== originalSelectedIds.value.size) return true;
        for (const id of selectedModelIds.value) {
            if (!originalSelectedIds.value.has(id)) return true;
        }
        return false;
    } else {
        const originalId = [...originalSelectedIds.value][0] || '';
        return selectedModelId.value !== originalId;
    }
});

const filteredModels = computed(() => {
    if (!searchQuery.value) return models.value;
    const query = searchQuery.value.toLowerCase();
    return models.value.filter(m =>
        m.name.toLowerCase().includes(query) ||
        m.description?.toLowerCase().includes(query)
    );
});

const selectedModelsDisplay = computed(() => {
    if (props.multiple) {
        const selected = models.value.filter(m => selectedModelIds.value.includes(m.id));
        if (selected.length === 0) return 'Select models...';
        if (selected.length === 1) return selected[0].name;
        return `${selected.length} models selected`;
    } else {
        const selected = models.value.find(m => m.id === selectedModelId.value);
        return selected?.name || 'Select a model...';
    }
});

const selectedModelIcon = computed(() => {
    if (props.multiple) {
        // For multi-select, show icon only if one model is selected
        if (selectedModelIds.value.length === 1) {
            const selected = models.value.find(m => m.id === selectedModelIds.value[0]);
            return selected?.icon || null;
        }
        return null;
    } else {
        const selected = models.value.find(m => m.id === selectedModelId.value);
        return selected?.icon || null;
    }
});

const fetchModels = async () => {
    try {
        isLoadingModels.value = true;
        modelsError.value = null;

        const response = await axios.get(route('agents.models.list', { agent: props.agentId }));
        models.value = response.data.models;

        // Initialize selection state from API response
        const selectedIds = new Set<string>();
        for (const model of models.value) {
            if (model.is_selected) {
                selectedIds.add(model.id);
            }
        }

        if (props.multiple) {
            selectedModelIds.value = [...selectedIds];
        } else {
            selectedModelId.value = [...selectedIds][0] || '';
        }

        // Store original selection
        originalSelectedIds.value = new Set(selectedIds);
    } catch (error) {
        console.error('Failed to fetch models:', error);
        modelsError.value = 'Failed to load available models. Please try again.';
    } finally {
        isLoadingModels.value = false;
    }
};

const selectModel = (modelId: string) => {
    if (props.multiple) {
        // Toggle for multi-select
        const index = selectedModelIds.value.indexOf(modelId);
        if (index > -1) {
            // Prevent unchecking if it's the last selected model
            if (selectedModelIds.value.length > 1) {
                selectedModelIds.value.splice(index, 1);
            }
        } else {
            selectedModelIds.value.push(modelId);
        }
    } else {
        // Single select - just set and close
        selectedModelId.value = modelId;
        isDropdownOpen.value = false;
    }
};

const saveSelection = async () => {
    isSaving.value = true;

    try {
        if (props.multiple) {
            const toEnable = selectedModelIds.value.filter(id => !originalSelectedIds.value.has(id));
            const toDisable = [...originalSelectedIds.value].filter(id => !selectedModelIds.value.includes(id));

            for (const modelId of toEnable) {
                await axios.patch(route('agents.models.toggle', { agent: props.agentId }), {
                    model_id: modelId,
                    status: true
                });
            }
            for (const modelId of toDisable) {
                await axios.patch(route('agents.models.toggle', { agent: props.agentId }), {
                    model_id: modelId,
                    status: false
                });
            }

            originalSelectedIds.value = new Set(selectedModelIds.value);

            for (const model of models.value) {
                model.is_selected = selectedModelIds.value.includes(model.id);
            }
        } else {
            const originalId = [...originalSelectedIds.value][0] || '';

            if (originalId && originalId !== selectedModelId.value) {
                await axios.patch(route('agents.models.toggle', { agent: props.agentId }), {
                    model_id: originalId,
                    status: false
                });
            }

            if (selectedModelId.value) {
                await axios.patch(route('agents.models.toggle', { agent: props.agentId }), {
                    model_id: selectedModelId.value,
                    status: true
                });
            }

            originalSelectedIds.value = new Set(selectedModelId.value ? [selectedModelId.value] : []);

            for (const model of models.value) {
                model.is_selected = model.id === selectedModelId.value;
            }
        }

        toast.success('Model selection saved');
        emit('saved');
    } catch (error) {
        console.error('Failed to save model selection:', error);
        toast.error('Failed to save model selection');
    } finally {
        isSaving.value = false;
    }
};

const isSelected = (modelId: string) => {
    if (props.multiple) {
        return selectedModelIds.value.includes(modelId);
    }
    return selectedModelId.value === modelId;
};

onMounted(() => {
    fetchModels();
});
</script>

<template>
    <div class="space-y-4">
        <div>
            <Label class="text-sm font-medium">Model</Label>
            <p v-if="description" class="text-xs text-muted-foreground mt-1">
                {{ description }}
            </p>
        </div>

        <!-- Loading State -->
        <div v-if="isLoadingModels" class="flex items-center gap-2 text-sm text-muted-foreground">
            <Loader2 class="h-4 w-4 animate-spin" />
            <span>Loading models...</span>
        </div>

        <!-- Error State -->
        <div v-else-if="modelsError" class="rounded-md border border-destructive/20 bg-destructive/10 p-3">
            <p class="text-sm text-destructive">{{ modelsError }}</p>
        </div>

        <!-- Model Selection -->
        <div v-else class="space-y-3">
            <Popover v-model:open="isDropdownOpen">
                <PopoverTrigger asChild>
                    <Button
                        variant="outline"
                        role="combobox"
                        :aria-expanded="isDropdownOpen"
                        class="w-full justify-between font-normal h-10"
                    >
                        <div class="flex items-center gap-2 truncate">
                            <img
                                v-if="selectedModelIcon"
                                :src="`/images/llms/${selectedModelIcon}`"
                                :alt="selectedModelsDisplay"
                                class="h-5 w-5 flex-shrink-0"
                            />
                            <Brain v-else class="h-5 w-5 text-muted-foreground flex-shrink-0" />
                            <span class="truncate">{{ selectedModelsDisplay }}</span>
                        </div>
                        <ChevronDown class="ml-2 h-4 w-4 shrink-0 opacity-50" />
                    </Button>
                </PopoverTrigger>
                <PopoverContent class="w-[var(--radix-popover-trigger-width)] p-0" align="start">
                    <!-- Search -->
                    <div class="p-2 border-b">
                        <div class="relative">
                            <Search class="absolute left-2 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
                            <Input
                                v-model="searchQuery"
                                placeholder="Search Models..."
                                class="pl-8 h-8"
                            />
                        </div>
                    </div>

                    <!-- Model List -->
                    <TooltipProvider :delay-duration="300">
                        <div class="max-h-[300px] overflow-y-auto p-1">
                            <Tooltip v-for="model in filteredModels" :key="model.id">
                                <TooltipTrigger asChild>
                                    <button
                                        type="button"
                                        class="flex items-center gap-3 w-full px-2 py-2 rounded-md hover:bg-accent text-left transition-colors"
                                        :class="{
                                            'bg-accent': isSelected(model.id)
                                        }"
                                        @click="selectModel(model.id)"
                                    >
                                        <!-- Checkmark for selection -->
                                        <div class="w-5 h-5 flex items-center justify-center">
                                            <Check
                                                v-if="isSelected(model.id)"
                                                class="h-4 w-4 text-primary"
                                            />
                                        </div>

                                        <!-- Model Icon -->
                                        <img
                                            v-if="model.icon"
                                            :src="`/images/llms/${model.icon}`"
                                            :alt="model.name"
                                            class="h-5 w-5 flex-shrink-0"
                                        />
                                        <Brain v-else class="h-5 w-5 text-muted-foreground flex-shrink-0" />

                                        <!-- Model Name -->
                                        <div class="flex items-center gap-1.5 flex-1 min-w-0">
                                            <span class="text-sm">{{ model.name }}</span>
                                            
                                        </div>
                                    </button>
                                </TooltipTrigger>

                                <!-- Tooltip Content - Model Details -->
                                <TooltipContent
                                    side="right"
                                    :side-offset="10"
                                    :hide-arrow="true"
                                    class="w-72 bg-popover text-popover-foreground border shadow-md p-4"
                                >
                                    <div class="space-y-3">
                                        <!-- Model Name -->
                                        <div class="flex items-center gap-2">
                                            <img
                                                v-if="model.icon"
                                                :src="`/images/llms/${model.icon}`"
                                                :alt="model.name"
                                                class="h-5 w-5"
                                            />
                                            <Brain v-else class="h-5 w-5 text-muted-foreground" />
                                            <span class="font-medium text-sm">{{ model.name }}</span>
                                        </div>

                                        <!-- Credits Cost -->
                                        <div class="text-sm">
                                            <span class="font-medium">Credits cost:</span>
                                            <span class="ml-1">{{ model.credits }}</span>
                                        </div>

                                        <!-- Location -->
                                        <div class="text-sm">
                                            <span class="font-medium">Hosted in:</span>
                                            <span class="ml-1">
                                                <span v-if="model.location === 'us'">🇺🇸 United States</span>
                                                <span v-else-if="model.location === 'eu'">🇪🇺 Europe</span>
                                                <span v-else-if="model.location === 'cn'">🇨🇳 China</span>
                                                <span v-else>{{ model.location.toUpperCase() }}</span>
                                            </span>
                                        </div>

                                        <!-- Description -->
                                        <p v-if="model.description" class="text-sm text-muted-foreground leading-relaxed">
                                            {{ model.description }}
                                        </p>

                                        <!-- Features -->
                                        <div v-if="model.features && model.features.length > 0" class="flex flex-wrap gap-1">
                                            <span
                                                v-for="feature in model.features"
                                                :key="feature"
                                                class="text-xs bg-muted px-2 py-0.5 rounded"
                                            >
                                                {{ feature }}
                                            </span>
                                        </div>

                                        <!-- Reasoning badge -->
                                        <div v-if="model.reasoning" class="text-xs text-emerald-600">
                                            ✓ Reasoning model
                                        </div>
                                    </div>
                                </TooltipContent>
                            </Tooltip>

                            <!-- No results -->
                            <div v-if="filteredModels.length === 0" class="px-2 py-4 text-center text-sm text-muted-foreground">
                                No models found
                            </div>
                        </div>
                    </TooltipProvider>
                </PopoverContent>
            </Popover>

            <!-- Divider and Save Button -->
            <div class="border-t pt-3">
                <Button
                    @click="saveSelection"
                    :disabled="!hasChanges || isSaving"
                    size="sm"
                >
                    <Loader2 v-if="isSaving" class="h-4 w-4 mr-2 animate-spin" />
                    <Save v-else class="h-4 w-4 mr-2" />
                    Save
                </Button>
            </div>

            <div class="space-y-2">
                <Alert>
                    <Info class="h-4 w-4" />
                    <AlertDescription>
                        <strong>Recommended:</strong> Mistral is an excellent choice for most use cases.
                    </AlertDescription>
                </Alert>

                <Alert>
                    <Info class="h-4 w-4" />
                    <AlertDescription>
                        Credits are deducted per message. Different models have different costs based on their capabilities.
                    </AlertDescription>
                </Alert>
            </div>
        </div>
    </div>
</template>
