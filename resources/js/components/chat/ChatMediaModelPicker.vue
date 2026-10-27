<script setup lang="ts">
/**
 * The header's image or video model picker: the model the agent draws or
 * films with next, from the ones it offers. Hidden while it offers fewer
 * than two.
 */
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuLabel,
    DropdownMenuRadioGroup,
    DropdownMenuRadioItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { type MediaModelKind, useChatMediaModelChoice } from '@/composables/chat/useChatMediaModelChoice';
import { ChevronDown, Clapperboard, ImageIcon } from 'lucide-vue-next';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps<{ kind: MediaModelKind }>();

const { t } = useI18n();
const choice = useChatMediaModelChoice();
const pick = computed(() => choice?.[props.kind] ?? null);

const shown = computed(() => (pick.value?.models.value.length ?? 0) > 1);
const title = computed(() => (props.kind === 'video' ? t('header.video_model') : t('header.image_model')));

const pickedId = computed({
    get: () => pick.value?.modelId.value ?? '',
    set: (id: string) => {
        if (pick.value) pick.value.modelId.value = id;
    },
});
</script>

<template>
    <DropdownMenu v-if="pick && shown">
        <DropdownMenuTrigger as-child>
            <Button
                type="button"
                variant="ghost"
                class="hover:bg-muted/80 text-muted-foreground hover:text-foreground h-9 max-w-48 shrink-0 gap-1.5 rounded-lg px-2.5 text-sm font-normal"
                :aria-label="`${title}: ${pick.picked.value?.name ?? ''}`"
            >
                <Clapperboard v-if="kind === 'video'" class="size-4 shrink-0" />
                <ImageIcon v-else class="size-4 shrink-0" />
                <span class="hidden truncate sm:inline">{{ pick.picked.value?.name }}</span>
                <ChevronDown class="size-3.5 shrink-0 opacity-60" />
            </Button>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="start" side="bottom" class="w-64">
            <DropdownMenuLabel class="text-muted-foreground text-xs font-normal">{{ title }}</DropdownMenuLabel>
            <DropdownMenuRadioGroup v-model="pickedId">
                <DropdownMenuRadioItem v-for="model in pick.models.value" :key="model.id" :value="model.id" class="gap-2">
                    <span class="truncate">{{ model.name }}</span>
                    <span v-if="model.is_default" class="text-muted-foreground ms-auto text-xs">{{ t('composer.model_default') }}</span>
                </DropdownMenuRadioItem>
            </DropdownMenuRadioGroup>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
