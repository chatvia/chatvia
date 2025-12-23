<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { router, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import { ChevronDown, Search } from 'lucide-vue-next';
import { computed, nextTick, onMounted, ref, watch } from 'vue';

type AIModel = App.Data.Chat.LlmModelData;

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

const page = usePage();

const props = defineProps<{
    agentId?: string;
    models?: AIModel[];
    defaultModel?: AIModel | null;
    isLoadingModels?: boolean;
}>();

// Reactive state for models
const availableModels = ref<AIModel[]>(props.models || []);
const selectedModel = ref<AIModel | null>(props.defaultModel || null);
const isLoading = ref(props.isLoadingModels ?? true);
const error = ref<string | null>(null);

const searchQuery = ref('');
const isModelDropdownOpen = ref(false);
const searchInput = ref<any>();

const emit = defineEmits<{
    modelChanged: [model: AIModel];
}>();

// Filter models based on search
const filteredModels = computed(() => {
    if (!searchQuery.value) return availableModels.value;

    const query = searchQuery.value.toLowerCase();
    return availableModels.value.filter(
        (model) => model.name.toLowerCase().includes(query) || (model.description && model.description.toLowerCase().includes(query)),
    );
});

// Fetch models from API (only if not provided as props)
const fetchModels = async () => {
    try {
        isLoading.value = true;
        error.value = null;

        const response = await axios.get<ModelsResponse>(route('chat.models.index'));
        availableModels.value = response.data.models;
    } catch (err) {
        console.error('Failed to fetch models:', err);
        error.value = 'Failed to load available models.';
    } finally {
        isLoading.value = false;
    }
};

const findModelById = (id: string): AIModel | undefined => {
    return availableModels.value.find((m) => m.id === id);
};

const getQueryParam = (param: string): string | null => {
    const urlParams = new URLSearchParams(window.location.search);
    return urlParams.get(param);
};

// Watch for prop changes
watch(
    () => props.models,
    (newModels) => {
        if (newModels) {
            availableModels.value = newModels;
        }
    },
    { immediate: true },
);

watch(
    () => props.defaultModel,
    (newDefaultModel) => {
        if (newDefaultModel) {
            selectedModel.value = newDefaultModel;
        }
    },
    { immediate: true },
);

watch(
    () => props.isLoadingModels,
    (newIsLoading) => {
        if (newIsLoading !== undefined) {
            isLoading.value = newIsLoading;
        }
    },
    { immediate: true },
);

function modelSelected(model: AIModel) {
    // Use model id for routing
    const currentRoute = route().current();
    if (currentRoute) {
        router.visit(
            route(currentRoute, {
                ...route().params,
                model: model.id,
            }),
            {
                preserveState: true,
                preserveScroll: true,
                only: [],
                replace: true,
                onSuccess: () => {
                    emit('modelChanged', model);
                    selectedModel.value = model;
                },
            },
        );
    }
}

// Handle search input focus
const handleSearchFocus = () => {
    // Prevent dropdown from closing when search is focused
    nextTick(() => {
        if (searchInput.value) {
            searchInput.value.focus();
        }
    });
};

// Handle search input keydown to prevent dropdown navigation
const handleSearchKeydown = (event: KeyboardEvent) => {
    // Prevent dropdown navigation when typing in search
    if (event.key !== 'Escape') {
        event.stopPropagation();
    }
};

onMounted(async () => {
    // Only fetch models if not provided as props
    if (!props.models) {
        await fetchModels();
    }

    const modelId = getQueryParam('model');

    if (modelId) {
        const foundModel = findModelById(modelId);
        if (foundModel) {
            selectedModel.value = foundModel;
        } else if (availableModels.value.length > 0) {
            selectedModel.value = availableModels.value[0];
        }
    } else if (availableModels.value.length > 0) {
        selectedModel.value = availableModels.value[0];
    }

    if (selectedModel.value) {
        emit('modelChanged', selectedModel.value);
    }
});
</script>

<template>
    <DropdownMenu>
        <DropdownMenuTrigger as-child>
            <Button
                variant="ghost"
                size="sm"
                class="bg-muted/50 hover:bg-muted text-muted-foreground hover:text-foreground h-9 gap-1.5 rounded-full border border-transparent px-3 transition-all hover:border-gray-200 dark:hover:border-gray-700"
                :disabled="isLoading"
            >
                <img v-if="selectedModel?.icon" :src="`/images/llms/${selectedModel.icon}`" :alt="selectedModel.name" class="h-4 w-4" />
                <span class="max-w-[100px] truncate text-xs font-medium">{{ selectedModel?.name || (isLoading ? 'Loading...' : 'Select') }}</span>
                <ChevronDown class="h-3 w-3 shrink-0" />
            </Button>
        </DropdownMenuTrigger>

        <DropdownMenuContent class="w-56" :side="'top'" :align="'start'" :sideOffset="4">
            <!-- Search -->
            <div class="p-2">
                <div class="relative">
                    <Search class="text-muted-foreground absolute top-1/2 left-2 h-3 w-3 -translate-y-1/2" />
                    <Input
                        ref="searchInput"
                        v-model="searchQuery"
                        type="text"
                        placeholder="Search models..."
                        class="focus:ring-primary/30 bg-muted/50 h-7 border-0 pr-2 pl-6 text-xs focus:ring-1"
                        @focus="handleSearchFocus"
                        @keydown="handleSearchKeydown"
                        @click.stop
                    />
                </div>
            </div>

            <!-- Loading state -->
            <div v-if="isLoading" class="text-muted-foreground py-4 text-center text-xs">Loading models...</div>

            <!-- Error state -->
            <div v-else-if="error" class="text-destructive py-4 text-center text-xs">
                {{ error }}
            </div>

            <!-- No results -->
            <div v-else-if="filteredModels.length === 0" class="text-muted-foreground py-4 text-center text-xs">No models found</div>

            <!-- Models -->
            <div v-else>
                <DropdownMenuItem
                    v-for="model in filteredModels"
                    :key="model.id"
                    @click="modelSelected(model)"
                    class="flex cursor-pointer items-center gap-2"
                    :class="{ 'bg-accent': model.id === selectedModel?.id }"
                >
                    <img v-if="model.icon" :src="`/images/llms/${model.icon}`" :alt="model.name" class="h-4 w-4" />
                    <span class="text-sm">{{ model.name }}</span>
                </DropdownMenuItem>
            </div>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
