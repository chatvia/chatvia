<script setup lang="ts">
// The "Cases" section of the Evals page: a summary line, the add buttons and
// the table of eval cases. It only displays the cases; adding, editing and
// deleting are handled by the page through the emitted events.
import { Badge } from '@/shared/ui/badge';
import { Button } from '@/shared/ui/button';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/shared/ui/table';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/shared/ui/tooltip';
import { Info, Pencil, Plus, Sparkles, Trash2 } from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';
import type { EvalCase } from './types';
import { useScorers } from './useScorers';

defineProps<{
    cases: EvalCase[];
    activeCount: number;
    canManage: boolean;
}>();

const emit = defineEmits<{
    add: [];
    fromKnowledge: [];
    edit: [item: EvalCase];
    delete: [item: EvalCase];
}>();

const { t } = useI18n();
const { scorerIcon, scorerLabel } = useScorers();
</script>

<template>
    <section class="space-y-3">
        <div class="flex items-center justify-between gap-4">
            <div>
                <h2 class="text-base font-semibold">{{ t('agents.evals.index.cases_heading') }}</h2>
                <p class="text-muted-foreground text-sm">
                    {{ t('agents.evals.index.cases_summary', { total: cases.length, active: activeCount }) }}
                </p>
                <p v-if="activeCount > 0" class="text-muted-foreground mt-1 flex items-center gap-1.5 text-sm">
                    <Info class="h-3.5 w-3.5 shrink-0" />
                    {{ t('agents.evals.index.cost_note', { count: activeCount }, activeCount) }}
                </p>
            </div>
            <div v-if="canManage" class="flex items-center gap-2">
                <Button size="sm" variant="outline" @click="emit('fromKnowledge')">
                    <Sparkles class="me-2 h-4 w-4" />
                    {{ t('agents.evals.index.from_knowledge') }}
                </Button>
                <Button size="sm" variant="outline" @click="emit('add')">
                    <Plus class="me-2 h-4 w-4" />
                    {{ t('agents.evals.index.add_case') }}
                </Button>
            </div>
        </div>

        <Table>
            <TableHeader>
                <TableRow>
                    <TableHead>{{ t('agents.evals.index.table.case') }}</TableHead>
                    <TableHead>{{ t('agents.evals.index.table.scorer') }}</TableHead>
                    <TableHead>{{ t('agents.evals.index.table.status') }}</TableHead>
                    <TableHead class="w-0 text-end">{{ t('agents.evals.index.table.actions') }}</TableHead>
                </TableRow>
            </TableHeader>
            <TableBody>
                <TableRow v-for="item in cases" :key="item.id" class="hover:bg-muted/50">
                    <TableCell class="max-w-md">
                        <Tooltip>
                            <TooltipTrigger as-child>
                                <div class="truncate font-medium">{{ item.name }}</div>
                            </TooltipTrigger>
                            <TooltipContent class="max-w-sm">
                                <p class="whitespace-normal">{{ item.name }}</p>
                            </TooltipContent>
                        </Tooltip>
                        <!-- Knowledge-created cases reuse the question as the name — don't print it twice. -->
                        <Tooltip v-if="item.input !== item.name">
                            <TooltipTrigger as-child>
                                <div class="text-muted-foreground truncate text-sm">{{ item.input }}</div>
                            </TooltipTrigger>
                            <TooltipContent class="max-w-sm">
                                <p class="whitespace-normal">{{ item.input }}</p>
                            </TooltipContent>
                        </Tooltip>
                    </TableCell>
                    <TableCell>
                        <Badge variant="outline" class="gap-1.5">
                            <component :is="scorerIcon(item.scorer_type)" class="h-3 w-3" />
                            {{ scorerLabel(item.scorer_type) }}
                        </Badge>
                    </TableCell>
                    <TableCell>
                        <Badge :variant="item.active ? 'secondary' : 'outline'" class="gap-1.5" :class="item.active ? '' : 'text-muted-foreground'">
                            <span class="h-1.5 w-1.5 rounded-full" :class="item.active ? 'bg-emerald-500' : 'bg-muted-foreground/40'" />
                            {{ item.active ? t('agents.evals.index.status_active') : t('agents.evals.index.status_inactive') }}
                        </Badge>
                    </TableCell>
                    <TableCell class="text-end">
                        <div v-if="canManage" class="flex items-center justify-end gap-1">
                            <Button
                                variant="ghost"
                                size="icon"
                                :aria-label="t('agents.evals.index.case_dialog.edit_title')"
                                @click="emit('edit', item)"
                            >
                                <Pencil class="h-4 w-4" />
                            </Button>
                            <Button variant="ghost" size="icon" :aria-label="t('agents.evals.index.delete_case_title')" @click="emit('delete', item)">
                                <Trash2 class="text-destructive h-4 w-4" />
                            </Button>
                        </div>
                    </TableCell>
                </TableRow>
                <TableRow v-if="cases.length === 0">
                    <TableCell colspan="4" class="text-muted-foreground py-10 text-center text-sm">
                        {{ t('agents.evals.index.empty_cases') }}
                    </TableCell>
                </TableRow>
            </TableBody>
        </Table>
    </section>
</template>
