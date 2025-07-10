<script setup lang="ts">
import { ref, computed, onMounted, watch, nextTick, onUnmounted } from "vue";
import { vOnClickOutside } from '@vueuse/components'
import { router } from "@inertiajs/vue3";
import { usePage } from "@inertiajs/vue3";
import { ChevronDown, Search } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import type { Model } from '@/types/chat';

const page = usePage();

// Use models directly from props - they should already have external_id
const availableModels = ref<Model[]>(page.props.models as Model[]);
const selectedModel = ref<Model | null>(null);

const searchQuery = ref('');
const isModelDropdownOpen = ref(false);
const highlightedIndex = ref(0);
const searchInput = ref<HTMLInputElement>();

const emit = defineEmits<{
    modelChanged: [model: Model]
}>();

// Filter models based on search
const filteredModels = computed(() => {
    if (!searchQuery.value) return availableModels.value;
    
    const query = searchQuery.value.toLowerCase();
    return availableModels.value.filter(model =>
        model.name.toLowerCase().includes(query) ||
        (model.description && model.description.toLowerCase().includes(query))
    );
});

// Get badge variant based on feature type
const getFeatureBadgeVariant = (feature: string): "default" | "secondary" | "outline" | "destructive" => {
    const featureLower = feature.toLowerCase();
    
    // Map features to badge variants
    if (featureLower === 'advanced' || featureLower === 'pro' || featureLower === 'powerful') {
        return 'default';
    }
    if (featureLower === 'fast' || featureLower === 'quick' || featureLower === 'lightweight') {
        return 'secondary';
    }
    if (featureLower === 'new' || featureLower === 'beta' || featureLower === 'experimental') {
        return 'destructive';
    }
    // Default for other features
    return 'outline';
};

// Get the primary feature to display (prioritize certain features)
const getPrimaryFeature = (features?: string[]): string | null => {
    if (!features || features.length === 0) return null;
    
    // Priority order for features
    const priorityFeatures = ['new', 'advanced', 'fast', 'pro', 'beta'];
    
    // Find the first priority feature
    for (const priority of priorityFeatures) {
        const found = features.find(f => f.toLowerCase() === priority.toLowerCase());
        if (found) return found;
    }
    
    // Return the first feature if no priority feature found
    return features[0];
};

const findModelByExternalId = (externalId: string): Model | undefined => {
    return availableModels.value.find(m => m.external_id === externalId);
};

const getQueryParam = (param: string): string | null => {
    const urlParams = new URLSearchParams(window.location.search);
    return urlParams.get(param);
};

const toggleModelDropdown = (event?: Event) => {
    if (event) event.stopPropagation();

    isModelDropdownOpen.value = !isModelDropdownOpen.value;

    if (isModelDropdownOpen.value) {
        nextTick(() => {
            searchInput.value?.focus();
        });
    } else {
        searchQuery.value = '';
        highlightedIndex.value = 0;
    }
};

const closeModal = () => {
    isModelDropdownOpen.value = false;
    searchQuery.value = '';
    highlightedIndex.value = 0;
};

function modelSelected(model: Model) {
    // Always use external_id for routing
    router.visit(
        route(route().current(), {
            ...route().params,
            model: model.external_id,
        }),
        {
            preserveState: true,
            preserveScroll: true,
            only: [],
            replace: true,
            onSuccess: () => {
                emit('modelChanged', model);
                selectedModel.value = model;
                closeModal();
            }
        }
    );
}

// Keyboard navigation
const handleKeyDown = (event: KeyboardEvent) => {
    if (!isModelDropdownOpen.value) return;
    
    switch (event.key) {
        case 'ArrowDown':
            event.preventDefault();
            highlightedIndex.value = (highlightedIndex.value + 1) % filteredModels.value.length;
            break;
        case 'ArrowUp':
            event.preventDefault();
            highlightedIndex.value = highlightedIndex.value === 0 ? filteredModels.value.length - 1 : highlightedIndex.value - 1;
            break;
        case 'Enter':
            event.preventDefault();
            if (filteredModels.value[highlightedIndex.value]) {
                modelSelected(filteredModels.value[highlightedIndex.value]);
            }
            break;
        case 'Escape':
            event.preventDefault();
            closeModal();
            break;
    }
};

// Reset highlighted index when search changes
watch(searchQuery, () => {
    highlightedIndex.value = 0;
});

onMounted(() => {
    const modelExternalId = getQueryParam('model');

    if (modelExternalId) {
        const foundModel = findModelByExternalId(modelExternalId);
        if (foundModel) {
            selectedModel.value = foundModel;
        } else {
            // Fallback to default model
            selectedModel.value = page.props.defaultModel as Model;
        }
    } else {
        selectedModel.value = page.props.defaultModel as Model;
    }

    if (selectedModel.value) {
        emit('modelChanged', selectedModel.value);
    }
    
    // Add global keyboard listener
    document.addEventListener('keydown', handleKeyDown);
});

onUnmounted(() => {
    document.removeEventListener('keydown', handleKeyDown);
});
</script>

<template>
    <div class="relative">
        <Button
            @click="toggleModelDropdown"
            variant="ghost"
            size="sm"
            class="gap-2 text-muted-foreground hover:text-foreground"
        >
            <img 
                v-if="selectedModel?.icon"
                :src="`/images/llms/${selectedModel.icon}`" 
                :alt="selectedModel.name"
                class="h-4 w-4"
            />
            <span class="text-sm font-medium">{{ selectedModel?.name || 'Select model' }}</span>
            <ChevronDown class="h-3 w-3 transition-transform" :class="{ 'rotate-180': isModelDropdownOpen }" />
        </Button>

        <div
            v-if="isModelDropdownOpen"
            v-on-click-outside="closeModal"
            class="absolute left-0 bottom-full z-50 mb-2 w-72 rounded-lg border border-border bg-background shadow-lg"
        >
            <!-- Search -->
            <div class="border-b p-3">
                <div class="relative">
                    <Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                    <Input
                        ref="searchInput"
                        v-model="searchQuery"
                        type="text"
                        placeholder="Search models..."
                        class="h-8 pl-9 pr-3 text-sm"
                        @click.stop
                    />
                </div>
            </div>

            <!-- Model list -->
            <div class="max-h-[320px] overflow-y-auto">
                <!-- No results -->
                <div v-if="filteredModels.length === 0" class="py-8 text-center text-sm text-muted-foreground">
                    No models found
                </div>

                <!-- Models -->
                <div v-else class="p-2">
                    <div
                        v-for="(model, index) in filteredModels"
                        :key="model.external_id"
                        @click="modelSelected(model)"
                        @mouseenter="highlightedIndex = index"
                        class="flex cursor-pointer items-center gap-3 rounded-md px-3 py-2 transition-colors hover:bg-accent"
                        :class="{ 
                            'bg-accent': model.external_id === selectedModel?.external_id,
                            'ring-1 ring-primary': highlightedIndex === index
                        }"
                    >
                        <div class="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-md bg-muted">
                            <img 
                                v-if="model.icon"
                                :src="`/images/llms/${model.icon}`" 
                                :alt="model.name"
                                class="h-4 w-4"
                            />
                        </div>

                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2">
                                <span class="text-sm font-medium">{{ model.name }}</span>
                                <Badge 
                                    v-if="getPrimaryFeature(model.features)"
                                    :variant="getFeatureBadgeVariant(getPrimaryFeature(model.features)!)" 
                                    class="h-5 px-1.5 text-xs capitalize"
                                >
                                    {{ getPrimaryFeature(model.features) }}
                                </Badge>
                            </div>
                            <div class="flex items-center gap-3 text-xs text-muted-foreground">
                                <span>{{ model.context.toLocaleString() }} tokens</span>
                                <!-- Show additional features if any -->
                                <div v-if="model.features && model.features.length > 1" class="flex items-center gap-1">
                                    <span 
                                        v-for="(feature, idx) in model.features.slice(1, 3)" 
                                        :key="idx"
                                        class="text-xs opacity-70"
                                    >
                                        {{ feature }}{{ idx < Math.min(model.features.length - 2, 1) ? ',' : '' }}
                                    </span>
                                    <span v-if="model.features.length > 3" class="text-xs opacity-50">
                                        +{{ model.features.length - 3 }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Footer -->
            <div class="border-t bg-muted/50 px-3 py-2">
                <div class="flex items-center gap-3 text-xs text-muted-foreground">
                    <div class="flex items-center gap-1">
                        <kbd class="rounded border bg-background px-1 py-0.5 text-xs">↑↓</kbd>
                        <span>navigate</span>
                    </div>
                    <div class="flex items-center gap-1">
                        <kbd class="rounded border bg-background px-1 py-0.5 text-xs">Enter</kbd>
                        <span>select</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
/* Custom scrollbar for model list */
.overflow-y-auto::-webkit-scrollbar {
    width: 6px;
}

.overflow-y-auto::-webkit-scrollbar-track {
    background: transparent;
}

.overflow-y-auto::-webkit-scrollbar-thumb {
    background-color: hsl(var(--muted-foreground) / 0.3);
    border-radius: 3px;
}

.overflow-y-auto::-webkit-scrollbar-thumb:hover {
    background-color: hsl(var(--muted-foreground) / 0.5);
}
</style>
