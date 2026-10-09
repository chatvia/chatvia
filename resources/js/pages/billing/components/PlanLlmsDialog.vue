<script lang="ts" setup>
// Lists the language models a plan can use, opened from the plan's first feature.
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Bot } from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';
import type { PlanLlm } from '../types';

defineProps<{
    llms: PlanLlm[];
}>();

const open = defineModel<boolean>('open', { required: true });

const { t } = useI18n();
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="max-w-2xl">
            <DialogHeader>
                <DialogTitle class="flex items-center gap-2">
                    <Bot class="text-primary h-5 w-5" />
                    {{ t('billing.plans.llms.title') }}
                </DialogTitle>
                <DialogDescription>{{ t('billing.plans.llms.description') }}</DialogDescription>
            </DialogHeader>

            <div class="mt-4 grid max-h-96 gap-3 overflow-y-auto">
                <div v-for="llm in llms" :key="llm.name" class="hover:bg-muted/50 flex items-center gap-3 rounded-lg border p-3 transition-colors">
                    <div class="h-8 w-8 flex-shrink-0">
                        <img :src="`/images/llms/${llm.icon}`" :alt="llm.name" class="h-full w-full object-contain" loading="lazy" />
                    </div>
                    <div class="flex-1">
                        <div class="text-sm font-medium">{{ llm.name }}</div>
                    </div>
                </div>

                <div v-if="llms.length === 0" class="text-muted-foreground py-8 text-center">
                    <Bot class="mx-auto mb-2 h-12 w-12 opacity-50" />
                    <p>{{ t('billing.plans.llms.empty') }}</p>
                </div>
            </div>
        </DialogContent>
    </Dialog>
</template>
