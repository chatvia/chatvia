<script setup lang="ts">
// Detail view of one scored case in a run: pass/fail, score, the judge's
// rationale, the agent's answer and which tools and sources it used.
import { Badge } from '@/shared/ui/badge';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/shared/ui/dialog';
import { CheckCircle2, XCircle } from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';
import type { EvalResult } from './types';
import { useScorers } from './useScorers';

defineProps<{
    selected: EvalResult | null;
}>();

const open = defineModel<boolean>('open', { required: true });

const { t } = useI18n();
const { scorerIcon, scorerShortLabel } = useScorers();
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="max-h-[90vh] overflow-y-auto sm:max-w-2xl">
            <DialogHeader class="space-y-3 pe-8 text-start">
                <DialogTitle class="leading-snug">{{ selected?.case_name }}</DialogTitle>
                <DialogDescription class="sr-only">{{ t('agents.evals.run.detail_description') }}</DialogDescription>
                <div v-if="selected" class="flex flex-wrap items-center gap-2">
                    <Badge :variant="selected.pass ? 'default' : 'destructive'" class="gap-1" :class="selected.pass ? 'bg-emerald-600' : ''">
                        <CheckCircle2 v-if="selected.pass" class="h-3 w-3" />
                        <XCircle v-else class="h-3 w-3" />
                        {{ selected.pass ? t('agents.evals.run.pass') : t('agents.evals.run.fail') }}
                    </Badge>
                    <Badge variant="outline" class="gap-1.5">
                        <component :is="scorerIcon(selected.scorer_type)" class="h-3 w-3" />
                        {{ scorerShortLabel(selected.scorer_type) }}
                    </Badge>
                    <span v-if="selected.score !== null" class="text-muted-foreground text-sm tabular-nums">
                        {{ t('agents.evals.run.score_label', { score: Math.round(selected.score * 100) }) }}
                    </span>
                </div>
            </DialogHeader>

            <div v-if="selected" class="space-y-4 text-sm">
                <div v-if="selected.judge_rationale">
                    <p class="text-muted-foreground mb-1.5 text-xs font-medium tracking-wide uppercase">{{ t('agents.evals.run.rationale') }}</p>
                    <p class="bg-muted/40 rounded-md border p-3 leading-relaxed">{{ selected.judge_rationale }}</p>
                </div>

                <div>
                    <p class="text-muted-foreground mb-1.5 text-xs font-medium tracking-wide uppercase">
                        {{ t('agents.evals.run.agent_answer') }}
                    </p>
                    <p class="bg-muted/40 max-h-64 overflow-y-auto rounded-md border p-3 leading-relaxed break-words whitespace-pre-wrap">
                        {{ selected.actual_output?.trim() || t('agents.evals.run.empty_output') }}
                    </p>
                    <p v-if="selected.actual_output_truncated" class="text-muted-foreground mt-1 text-xs">
                        {{ t('agents.evals.run.truncated') }}
                    </p>
                </div>

                <div v-if="selected.tools_called.length">
                    <p class="text-muted-foreground mb-1.5 text-xs font-medium tracking-wide uppercase">{{ t('agents.evals.run.tools_used') }}</p>
                    <div class="flex flex-wrap gap-1.5">
                        <Badge v-for="tool in selected.tools_called" :key="tool" variant="secondary">{{ tool }}</Badge>
                    </div>
                </div>

                <div v-if="selected.sources_used.length">
                    <p class="text-muted-foreground mb-1.5 text-xs font-medium tracking-wide uppercase">
                        {{ t('agents.evals.run.sources_retrieved') }}
                    </p>
                    <div class="flex flex-wrap gap-1.5">
                        <Badge v-for="(source, i) in selected.sources_used" :key="i" variant="outline" class="font-normal">{{ source }}</Badge>
                    </div>
                </div>

                <div class="text-muted-foreground flex flex-wrap items-center gap-x-4 gap-y-1 border-t pt-3 text-xs">
                    <span v-if="selected.latency_ms !== null" class="tabular-nums">{{
                        t('agents.evals.run.latency_value', { ms: selected.latency_ms.toLocaleString() })
                    }}</span>
                    <span v-if="selected.tokens !== null" class="tabular-nums">{{
                        t('agents.evals.run.tokens_count', { count: selected.tokens.toLocaleString() })
                    }}</span>
                    <span v-if="selected.trace_id" class="truncate font-mono">{{ selected.trace_id }}</span>
                </div>
            </div>
        </DialogContent>
    </Dialog>
</template>
