<script setup lang="ts">
// The guardrail trigger log: a paginated table of every time a guard fired
// for this agent, and a detail dialog with the flagged snippet and raw detail.
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { useDateFormat } from '@/composables/useDateFormat';
import { getInitials } from '@/composables/useInitials';
import { router } from '@inertiajs/vue3';
import { User } from 'lucide-vue-next';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import type { GuardrailTrigger, GuardrailTriggerPage } from './types';

defineProps<{
    triggers: GuardrailTriggerPage;
}>();

const { t } = useI18n();
const { formatDateTime } = useDateFormat();

const GUARD_LABELS: Record<string, string> = {
    prompt_injection: 'agents.guardrails.guard_labels.prompt_injection',
    pii: 'agents.guardrails.guard_labels.pii',
    blocked_topic: 'agents.guardrails.guard_labels.blocked_topic',
    toxicity: 'agents.guardrails.guard_labels.toxicity',
};
const guardLabel = (type: string) => (GUARD_LABELS[type] ? t(GUARD_LABELS[type]) : type);

const outcomeVariant = (o: string): 'default' | 'secondary' | 'destructive' | 'outline' => {
    if (o === 'blocked') return 'destructive';
    if (o === 'redacted') return 'secondary';
    return 'outline';
};

const selected = ref<GuardrailTrigger | null>(null);
const detailOpen = ref(false);
const openDetail = (row: GuardrailTrigger) => {
    selected.value = row;
    detailOpen.value = true;
};

const go = (url: string | null) => {
    if (url) router.get(url, {}, { preserveScroll: true, preserveState: true });
};

const prettyDetail = (detail: unknown): string => {
    if (!detail) return '—';
    try {
        return JSON.stringify(detail, null, 2);
    } catch {
        return String(detail);
    }
};
</script>

<template>
    <section class="space-y-3">
        <div>
            <h2 class="text-base font-semibold">{{ t('agents.guardrails.recent_triggers') }}</h2>
            <p class="text-muted-foreground text-sm">
                {{ t('agents.guardrails.triggers_logged', { total: triggers.total }) }}
            </p>
        </div>

        <div class="bg-card rounded-lg border">
            <Table class="[&_td]:px-4 [&_td]:py-3 [&_th]:px-4">
                <TableHeader>
                    <TableRow>
                        <TableHead>{{ t('agents.guardrails.table.by') }}</TableHead>
                        <TableHead>{{ t('agents.guardrails.table.guard') }}</TableHead>
                        <TableHead>{{ t('agents.guardrails.table.stage') }}</TableHead>
                        <TableHead>{{ t('agents.guardrails.table.outcome') }}</TableHead>
                        <TableHead>{{ t('agents.guardrails.table.severity') }}</TableHead>
                        <TableHead class="text-end">{{ t('agents.guardrails.table.when') }}</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow v-for="row in triggers.data" :key="row.id" class="hover:bg-muted/50 cursor-pointer" @click="openDetail(row)">
                        <TableCell>
                            <div class="flex items-center gap-2">
                                <Avatar class="size-7">
                                    <AvatarImage v-if="row.user_name && row.avatar" :src="row.avatar" :alt="row.user_name" />
                                    <AvatarFallback class="text-xs">
                                        <template v-if="row.user_name">{{ getInitials(row.user_name) }}</template>
                                        <User v-else class="text-muted-foreground size-3.5" />
                                    </AvatarFallback>
                                </Avatar>
                                <span :class="row.user_name ? 'text-sm' : 'text-muted-foreground text-sm'">{{
                                    row.user_name ?? t('agents.guardrails.visitor')
                                }}</span>
                            </div>
                        </TableCell>
                        <TableCell
                            ><Badge variant="outline">{{ guardLabel(row.guard_type) }}</Badge></TableCell
                        >
                        <TableCell class="text-muted-foreground text-sm capitalize">{{ row.stage }}</TableCell>
                        <TableCell
                            ><Badge :variant="outcomeVariant(row.outcome)" class="capitalize">{{ row.outcome }}</Badge></TableCell
                        >
                        <TableCell class="text-muted-foreground text-sm capitalize">{{ row.severity ?? '—' }}</TableCell>
                        <TableCell class="text-muted-foreground text-end whitespace-nowrap">{{ formatDateTime(row.created_at) }}</TableCell>
                    </TableRow>
                    <TableRow v-if="triggers.data.length === 0">
                        <TableCell colspan="6" class="text-muted-foreground py-10 text-center text-sm">
                            {{ t('agents.guardrails.no_triggers') }}
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </div>

        <div v-if="triggers.last_page > 1" class="flex items-center justify-between">
            <span class="text-muted-foreground text-sm">{{
                t('agents.guardrails.page_of', { current: triggers.current_page, last: triggers.last_page })
            }}</span>
            <div class="flex gap-2">
                <Button variant="outline" size="sm" :disabled="!triggers.prev_page_url" @click="go(triggers.prev_page_url)">{{
                    t('agents.guardrails.previous')
                }}</Button>
                <Button variant="outline" size="sm" :disabled="!triggers.next_page_url" @click="go(triggers.next_page_url)">{{
                    t('agents.guardrails.next')
                }}</Button>
            </div>
        </div>
    </section>

    <!-- Trigger detail -->
    <Dialog v-model:open="detailOpen">
        <DialogContent class="max-h-[85vh] overflow-y-auto sm:max-w-2xl">
            <DialogHeader>
                <DialogTitle class="flex items-center gap-2">
                    {{ selected ? guardLabel(selected.guard_type) : '' }}
                    <Badge v-if="selected" :variant="outcomeVariant(selected.outcome)" class="capitalize">{{ selected?.outcome }}</Badge>
                </DialogTitle>
                <DialogDescription>
                    {{ selected?.stage }} · {{ selected ? formatDateTime(selected.created_at) : '' }} ·
                    {{ t('agents.guardrails.by_user', { name: selected?.user_name ?? t('agents.guardrails.visitor') }) }}
                </DialogDescription>
            </DialogHeader>

            <div v-if="selected" class="space-y-4 text-sm">
                <div>
                    <p class="text-muted-foreground mb-1 text-xs font-medium uppercase">{{ t('agents.guardrails.snippet') }}</p>
                    <p class="bg-muted/40 max-h-48 overflow-y-auto rounded-md p-3 break-words whitespace-pre-wrap">
                        {{ selected.snippet || '—' }}
                    </p>
                </div>
                <div v-if="selected.detail">
                    <p class="text-muted-foreground mb-1 text-xs font-medium uppercase">{{ t('agents.guardrails.detail') }}</p>
                    <pre class="bg-muted/40 max-h-48 overflow-y-auto rounded-md p-3 text-xs break-words whitespace-pre-wrap">{{
                        prettyDetail(selected.detail)
                    }}</pre>
                </div>
                <div class="text-muted-foreground text-xs">
                    {{
                        t('agents.guardrails.conversation_trace', {
                            conversation: selected.conversation_external_id ?? '—',
                            trace: selected.trace_id ?? '—',
                        })
                    }}
                </div>
            </div>
        </DialogContent>
    </Dialog>
</template>
