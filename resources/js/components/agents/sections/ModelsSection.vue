<script setup lang="ts">
import ResponseSettings from '@/components/agents/ResponseSettings.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import { Brain, Check, ChevronDown, Search, X } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

type AIModel = Domain.Llm.Data.LlmModelData;

interface LlmSettings {
    temperature: number;
    top_k: number;
    top_p: number;
    presence_penalty: number;
    frequency_penalty: number;
}

const props = withDefaults(
    defineProps<{
        models: string[];
        llmSettings?: LlmSettings;
        catalog: AIModel[];
        single?: boolean;
        showResponseSettings?: boolean;
        errors?: Record<string, string | undefined>;
        disabled?: boolean;
    }>(),
    { single: false, showResponseSettings: false, errors: () => ({}), disabled: false },
);

const emit = defineEmits<{
    'update:models': [value: string[]];
    'update:llmSettings': [value: LlmSettings];
}>();

const searchQuery = ref('');
const isOpen = ref(false);

const filtered = computed(() => {
    const q = searchQuery.value.trim().toLowerCase();
    if (!q) return props.catalog;
    return props.catalog.filter((m) => m.name.toLowerCase().includes(q) || m.description?.toLowerCase().includes(q));
});

const selectedModels = computed(() => props.catalog.filter((m) => props.models.includes(m.id)));

const triggerLabel = computed(() => {
    if (selectedModels.value.length === 0) return props.single ? t('agents.setup.sections.models_select_one') : t('agents.setup.sections.models_select_many');
    if (selectedModels.value.length === 1) return selectedModels.value[0].name;
    return t('agents.setup.sections.models_selected', { count: selectedModels.value.length });
});

function isSelected(id: string): boolean {
    return props.models.includes(id);
}

function toggle(id: string): void {
    if (props.disabled) return;
    if (props.single) {
        emit('update:models', [id]);
        isOpen.value = false;
        return;
    }
    const next = isSelected(id) ? props.models.filter((m) => m !== id) : [...props.models, id];
    emit('update:models', next);
}

function remove(id: string): void {
    if (props.disabled) return;
    emit(
        'update:models',
        props.models.filter((m) => m !== id),
    );
}
</script>

<template>
    <div class="space-y-6">
        <div class="space-y-3">
            <div class="space-y-1">
                <Label>{{ t('agents.setup.sections.models_label', single ? 1 : 2) }}</Label>
                <p class="text-muted-foreground text-xs">{{ t('agents.setup.sections.models_hint') }}</p>
            </div>

            <Popover v-model:open="isOpen">
                <PopoverTrigger as-child>
                    <Button
                        type="button"
                        variant="outline"
                        role="combobox"
                        :aria-expanded="isOpen"
                        :disabled="disabled"
                        class="h-10 w-full justify-between font-normal"
                    >
                        <span class="flex min-w-0 items-center gap-2 truncate">
                            <template v-if="selectedModels.length === 1">
                                <img
                                    v-if="selectedModels[0].icon"
                                    :src="`/images/llms/${selectedModels[0].icon}`"
                                    :alt="selectedModels[0].name"
                                    class="h-4 w-4 flex-shrink-0"
                                />
                                <Brain v-else class="text-muted-foreground h-4 w-4 flex-shrink-0" />
                            </template>
                            <span class="truncate">{{ triggerLabel }}</span>
                        </span>
                        <ChevronDown class="ml-2 h-4 w-4 shrink-0 opacity-50" />
                    </Button>
                </PopoverTrigger>
                <PopoverContent class="w-[var(--radix-popover-trigger-width)] p-0" align="start">
                    <div class="border-b p-2">
                        <div class="relative">
                            <Search class="text-muted-foreground absolute top-1/2 left-2 h-4 w-4 -translate-y-1/2" />
                            <Input v-model="searchQuery" :placeholder="t('agents.setup.sections.models_search')" class="h-8 ps-8" />
                        </div>
                    </div>
                    <!-- Each row opens a side tooltip on hover with the model's
                         credits, hosting location, description, features, and
                         reasoning badge — saves a round-trip to docs to compare. -->
                    <TooltipProvider :delay-duration="300">
                        <div class="max-h-[300px] overflow-y-auto p-1">
                            <Tooltip v-for="model in filtered" :key="model.id">
                                <TooltipTrigger as-child>
                                    <button
                                        type="button"
                                        class="hover:bg-accent flex w-full items-center gap-3 rounded-md px-2 py-2 text-left transition-colors"
                                        :class="{ 'bg-accent': isSelected(model.id) }"
                                        @click="toggle(model.id)"
                                    >
                                        <div class="flex h-5 w-5 items-center justify-center">
                                            <Check v-if="isSelected(model.id)" class="text-primary h-4 w-4" />
                                        </div>
                                        <img v-if="model.icon" :src="`/images/llms/${model.icon}`" :alt="model.name" class="h-5 w-5 flex-shrink-0" />
                                        <Brain v-else class="text-muted-foreground h-5 w-5 flex-shrink-0" />
                                        <span class="text-sm">{{ model.name }}</span>
                                    </button>
                                </TooltipTrigger>
                                <TooltipContent
                                    side="right"
                                    :side-offset="10"
                                    :hide-arrow="true"
                                    class="bg-popover text-popover-foreground w-72 border p-4 shadow-md"
                                >
                                    <div class="space-y-3">
                                        <div class="flex items-center gap-2">
                                            <img v-if="model.icon" :src="`/images/llms/${model.icon}`" :alt="model.name" class="h-5 w-5" />
                                            <Brain v-else class="text-muted-foreground h-5 w-5" />
                                            <span class="text-sm font-medium">{{ model.name }}</span>
                                        </div>

                                        <div class="text-sm">
                                            <span class="font-medium">{{ t('agents.setup.sections.models_credits') }}</span>
                                            <span class="ml-1">{{ model.credits }}</span>
                                        </div>

                                        <div class="text-sm">
                                            <span class="font-medium">{{ t('agents.setup.sections.models_hosted') }}</span>
                                            <span class="ms-1">
                                                <span v-if="model.location === 'us'">{{ t('agents.setup.sections.models_location_us') }}</span>
                                                <span v-else-if="model.location === 'eu'">{{ t('agents.setup.sections.models_location_eu') }}</span>
                                                <span v-else-if="model.location === 'cn'">{{ t('agents.setup.sections.models_location_cn') }}</span>
                                                <span v-else>{{ model.location.toUpperCase() }}</span>
                                            </span>
                                        </div>

                                        <div class="text-sm">
                                            <span class="font-medium">{{ t('agents.setup.sections.models_context') }}</span>
                                            <span class="ms-1">{{ model.context_window.toLocaleString() }} tokens</span>
                                        </div>

                                        <p v-if="model.description" class="text-muted-foreground text-sm leading-relaxed">
                                            {{ model.description }}
                                        </p>

                                        <div v-if="model.features && model.features.length > 0" class="flex flex-wrap gap-1">
                                            <span
                                                v-for="feature in model.features"
                                                :key="String(feature)"
                                                class="bg-muted rounded px-2 py-0.5 text-xs"
                                            >
                                                {{ feature }}
                                            </span>
                                        </div>
                                    </div>
                                </TooltipContent>
                            </Tooltip>
                            <div v-if="filtered.length === 0" class="text-muted-foreground px-2 py-4 text-center text-sm">{{ t('agents.setup.sections.models_not_found') }}</div>
                        </div>
                    </TooltipProvider>
                </PopoverContent>
            </Popover>

            <!-- Multi-select chip strip — single-mode hides this since the trigger
                 already shows the selected model with its icon. -->
            <div v-if="!single && selectedModels.length > 0" class="flex flex-wrap gap-2">
                <span
                    v-for="model in selectedModels"
                    :key="model.id"
                    class="bg-muted inline-flex items-center gap-1.5 rounded-full py-1 pr-2 pl-3 text-sm"
                >
                    <img v-if="model.icon" :src="`/images/llms/${model.icon}`" :alt="model.name" class="h-4 w-4" />
                    {{ model.name }}
                    <button v-if="!disabled" type="button" class="hover:text-destructive" @click="remove(model.id)">
                        <X class="h-3.5 w-3.5" />
                    </button>
                </span>
            </div>
            <p v-if="errors?.models" class="text-destructive text-sm">{{ errors.models }}</p>
        </div>

        <ResponseSettings
            v-if="showResponseSettings && llmSettings"
            :model-value="llmSettings"
            :errors="errors as Record<string, string>"
            @update:model-value="$emit('update:llmSettings', $event)"
        />
    </div>
</template>
