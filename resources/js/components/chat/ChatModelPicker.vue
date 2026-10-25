<script setup lang="ts">
/**
 * The composer's model picker: the model the chatter's next message is
 * answered with, from the ones the agent offers. Hidden while the agent
 * offers no choice.
 */
import ChatModelLogo from '@/components/chat/ChatModelLogo.vue';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuLabel,
    DropdownMenuRadioGroup,
    DropdownMenuRadioItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useChatModelChoice } from '@/composables/chat/useChatModelChoice';
import { ChevronDown } from 'lucide-vue-next';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

defineProps<{ disabled?: boolean }>();

const { t } = useI18n();
const choice = useChatModelChoice();

const shown = computed(() => (choice?.models.value.length ?? 0) > 1);

const pickedId = computed({
    get: () => String(choice?.modelId.value ?? ''),
    set: (id: string) => choice?.pick(Number(id)),
});
</script>

<template>
    <DropdownMenu v-if="choice && shown">
        <DropdownMenuTrigger as-child>
            <Button
                type="button"
                variant="ghost"
                :disabled="disabled"
                class="hover:bg-muted text-muted-foreground hover:text-foreground h-9 max-w-44 shrink-0 gap-1.5 rounded-full px-2.5 text-sm font-normal"
                :aria-label="t('composer.model_picker', { model: choice.picked.value?.name ?? '' })"
            >
                <ChatModelLogo :logo="choice.picked.value?.logo ?? null" />
                <span class="hidden truncate sm:inline">{{ choice.picked.value?.name }}</span>
                <ChevronDown class="size-3.5 shrink-0 opacity-60" />
            </Button>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="end" side="top" class="w-64">
            <DropdownMenuLabel class="text-muted-foreground text-xs font-normal">{{ t('composer.model_picker_title') }}</DropdownMenuLabel>
            <DropdownMenuRadioGroup v-model="pickedId">
                <DropdownMenuRadioItem v-for="model in choice.models.value" :key="model.id" :value="String(model.id)" class="gap-2">
                    <ChatModelLogo :logo="model.logo" />
                    <span class="truncate">{{ model.name }}</span>
                    <span v-if="model.is_default" class="text-muted-foreground ms-auto text-xs">{{ t('composer.model_default') }}</span>
                </DropdownMenuRadioItem>
            </DropdownMenuRadioGroup>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
