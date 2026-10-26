<script setup lang="ts">
// The guardrail trigger log: a paginated table of every time a guard fired
// for this agent, filtered by guard, outcome and visitor, and a detail dialog
// with the flagged snippet, what the guard found, a link to the conversation
// and, for an abusive trigger, who sent it and a Block button.
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { useDateFormat } from '@/composables/useDateFormat';
import { skipReason } from '@/composables/notifications/useNotificationText';
import { getInitials } from '@/composables/useInitials';
import { useTeamPermissions } from '@/composables/useTeamPermissions';
import { guardrails, logs } from '@/routes/agents';
import { Link, router } from '@inertiajs/vue3';
import { Ban, ExternalLink, User, X } from 'lucide-vue-next';
import { ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import BlockOffenderDialog from './BlockOffenderDialog.vue';
import { FILTER_LABELS, GUARD_LABELS, labelFor, OUTCOME_LABELS, SEVERITY_LABELS, STAGE_LABELS } from './labels';
import type { GuardrailLogFilters, GuardrailTrigger, GuardrailTriggerPage } from './types';

const props = defineProps<{
    agent: { external_id: string };
    triggers: GuardrailTriggerPage;
    guardTypes: string[];
    outcomes: string[];
    filters: GuardrailLogFilters;
    canViewChats: boolean;
}>();

const { t } = useI18n();
const { formatDateTime } = useDateFormat();
const { hasPermission } = useTeamPermissions();

const guardLabel = (type: string) => labelFor(t, GUARD_LABELS, type);
const outcomeLabel = (outcome: string) => labelFor(t, OUTCOME_LABELS, outcome);
const stageLabel = (stage: string) => labelFor(t, STAGE_LABELS, stage);
const severityLabel = (severity: string | null) => labelFor(t, SEVERITY_LABELS, severity);

// 'all' stands for "no filter" (a Select item cannot have an empty value).
const guardType = ref(props.filters.guard_type ?? 'all');
const outcome = ref(props.filters.outcome ?? 'all');
const visitor = ref<string | null>(props.filters.visitor);

const applyFilters = () => {
    router.get(
        guardrails.url(props.agent.external_id),
        {
            guard_type: guardType.value === 'all' ? undefined : guardType.value,
            outcome: outcome.value === 'all' ? undefined : outcome.value,
            visitor: visitor.value ?? undefined,
        },
        { preserveState: true, preserveScroll: true, replace: true },
    );
};

/** Only this visitor's triggers (null shows everyone again). */
const filterByVisitor = (key: string | null) => {
    visitor.value = key;
    detailOpen.value = false;
    applyFilters();
};

// A visitor has no name: show the start of their fingerprint, so repeat
// attacks from one visitor are easy to spot.
const visitorLabel = (key: string) => t('agents.guardrails.block.visitor_short', { id: key.slice(0, 6) });
const personLabel = (row: GuardrailTrigger) => row.user_name ?? (row.visitor_key ? visitorLabel(row.visitor_key) : t('agents.guardrails.visitor'));

// Blocking a chatter needs the People permission; blocking a visitor, the
// Security one (the Security page lists the blocks).
const canBlock = (row: GuardrailTrigger) =>
    row.offender !== null && hasPermission(row.offender.kind === 'chatter' ? 'account.chatters.manage' : 'account.security');
const blockOpen = ref(false);

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

// After a block the page reloads its rows: keep the open one current.
watch(
    () => props.triggers,
    (page) => {
        if (selected.value) selected.value = page.data.find((row) => row.id === selected.value?.id) ?? selected.value;
    },
);

const go = (url: string | null) => {
    if (url) router.get(url, {}, { preserveScroll: true, preserveState: true });
};

/** What the guard found, as label → value rows; unknown keys are left out. */
const detailRows = (detail: unknown): { label: string; value: string }[] => {
    if (!detail || typeof detail !== 'object') return [];

    const d = detail as Record<string, unknown>;
    const rows: { label: string; value: string }[] = [];

    if (typeof d.topic === 'string') rows.push({ label: t('agents.guardrails.detail_labels.topic'), value: d.topic });
    if (Array.isArray(d.types)) {
        rows.push({
            label: t('agents.guardrails.detail_labels.types'),
            value: d.types.map((type) => labelFor(t, FILTER_LABELS, String(type))).join(', '),
        });
    }
    if (typeof d.count === 'number') rows.push({ label: t('agents.guardrails.detail_labels.count'), value: String(d.count) });
    if (Array.isArray(d.categories) && d.categories.length) {
        rows.push({ label: t('agents.guardrails.detail_labels.categories'), value: d.categories.join(', ') });
    }
    if (typeof d.tool === 'string') rows.push({ label: t('agents.guardrails.detail_labels.tool'), value: d.tool });
    if (typeof d.pattern === 'string') rows.push({ label: t('agents.guardrails.detail_labels.pattern'), value: d.pattern });
    if (d.reason === 'classifier_unavailable') {
        const why = typeof d.failure === 'string' ? skipReason(t, d.failure) : null;
        rows.push({
            label: t('agents.guardrails.detail_labels.reason'),
            value: why ? `${t('agents.guardrails.classifier_unavailable')} ${why}` : t('agents.guardrails.classifier_unavailable'),
        });
    } else if (typeof d.reason === 'string' && d.reason !== '') {
        rows.push({ label: t('agents.guardrails.detail_labels.reason'), value: d.reason });
    }

    return rows;
};
</script>

<template>
    <section class="space-y-3">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <h2 class="text-base font-semibold">{{ t('agents.guardrails.recent_triggers') }}</h2>
                <p class="text-muted-foreground text-sm">
                    {{ t('agents.guardrails.triggers_logged', { total: triggers.total }, triggers.total) }}
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <Button v-if="visitor" variant="secondary" size="sm" class="h-9" @click="filterByVisitor(null)">
                    {{ visitorLabel(visitor) }}
                    <X class="ms-1.5 h-3.5 w-3.5" />
                    <span class="sr-only">{{ t('agents.guardrails.block.clear_visitor') }}</span>
                </Button>
                <Select v-model="guardType" @update:model-value="applyFilters">
                    <SelectTrigger class="w-44">
                        <SelectValue :placeholder="t('agents.guardrails.all_guards')" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">{{ t('agents.guardrails.all_guards') }}</SelectItem>
                        <SelectItem v-for="type in guardTypes" :key="type" :value="type">{{ guardLabel(type) }}</SelectItem>
                    </SelectContent>
                </Select>
                <Select v-model="outcome" @update:model-value="applyFilters">
                    <SelectTrigger class="w-40">
                        <SelectValue :placeholder="t('agents.guardrails.all_outcomes')" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">{{ t('agents.guardrails.all_outcomes') }}</SelectItem>
                        <SelectItem v-for="value in outcomes" :key="value" :value="value">{{ outcomeLabel(value) }}</SelectItem>
                    </SelectContent>
                </Select>
            </div>
        </div>

        <Table>
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
                            <span :class="row.user_name ? 'text-sm' : 'text-muted-foreground text-sm'">{{ personLabel(row) }}</span>
                            <Badge v-if="row.offender?.blocked" variant="destructive" class="font-normal">{{
                                t('agents.guardrails.block.blocked')
                            }}</Badge>
                        </div>
                    </TableCell>
                    <TableCell
                        ><Badge variant="outline">{{ guardLabel(row.guard_type) }}</Badge></TableCell
                    >
                    <TableCell class="text-muted-foreground text-sm">{{ stageLabel(row.stage) }}</TableCell>
                    <TableCell
                        ><Badge :variant="outcomeVariant(row.outcome)">{{ outcomeLabel(row.outcome) }}</Badge></TableCell
                    >
                    <TableCell class="text-muted-foreground text-sm">{{ severityLabel(row.severity) }}</TableCell>
                    <TableCell class="text-muted-foreground text-end whitespace-nowrap">{{ formatDateTime(row.created_at) }}</TableCell>
                </TableRow>
                <TableRow v-if="triggers.data.length === 0">
                    <TableCell colspan="6" class="text-muted-foreground py-10 text-center text-sm">
                        {{ t('agents.guardrails.no_triggers') }}
                    </TableCell>
                </TableRow>
            </TableBody>
        </Table>

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
                    <Badge v-if="selected" :variant="outcomeVariant(selected.outcome)">{{ outcomeLabel(selected.outcome) }}</Badge>
                </DialogTitle>
                <DialogDescription>
                    {{ selected ? stageLabel(selected.stage) : '' }} · {{ selected ? formatDateTime(selected.created_at) : '' }} ·
                    {{ t('agents.guardrails.by_user', { name: selected ? personLabel(selected) : '' }) }}
                </DialogDescription>
            </DialogHeader>

            <div v-if="selected" class="space-y-4 text-sm">
                <!-- Who sent it, how often lately, and the Block button -->
                <div v-if="selected.offender" class="flex flex-wrap items-center justify-between gap-3 rounded-md border p-3">
                    <div>
                        <p class="font-medium">{{ personLabel(selected) }}</p>
                        <p class="text-muted-foreground text-xs">
                            {{
                                t(
                                    'agents.guardrails.block.recent_attempts',
                                    { count: selected.offender.recent_attempts },
                                    selected.offender.recent_attempts,
                                )
                            }}
                        </p>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <Button
                            v-if="selected.visitor_key && visitor !== selected.visitor_key"
                            variant="ghost"
                            size="sm"
                            @click="filterByVisitor(selected.visitor_key)"
                        >
                            {{ t('agents.guardrails.block.show_all') }}
                        </Button>
                        <Badge v-if="selected.offender.blocked" variant="destructive">{{ t('agents.guardrails.block.blocked') }}</Badge>
                        <Button v-else-if="canBlock(selected)" variant="destructive" size="sm" @click="blockOpen = true">
                            <Ban class="me-1.5 h-4 w-4" />
                            {{
                                selected.offender.kind === 'chatter'
                                    ? t('agents.guardrails.block.block_chatter')
                                    : t('agents.guardrails.block.block_visitor')
                            }}
                        </Button>
                    </div>
                </div>

                <div>
                    <p class="text-muted-foreground mb-1 text-xs font-medium uppercase">{{ t('agents.guardrails.snippet') }}</p>
                    <p class="bg-muted/40 max-h-48 overflow-y-auto rounded-md p-3 break-words whitespace-pre-wrap">
                        {{ selected.snippet || '—' }}
                    </p>
                </div>
                <div v-if="detailRows(selected.detail).length">
                    <p class="text-muted-foreground mb-1 text-xs font-medium uppercase">{{ t('agents.guardrails.detail') }}</p>
                    <dl class="bg-muted/40 grid grid-cols-[auto_1fr] gap-x-4 gap-y-1 rounded-md p-3 text-xs">
                        <template v-for="row in detailRows(selected.detail)" :key="row.label">
                            <dt class="text-muted-foreground">{{ row.label }}</dt>
                            <dd class="break-words">{{ row.value }}</dd>
                        </template>
                    </dl>
                </div>
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <span class="text-muted-foreground text-xs">{{
                        t('agents.guardrails.conversation_trace', {
                            conversation: selected.conversation_external_id ?? '—',
                            trace: selected.trace_id ?? '—',
                        })
                    }}</span>
                    <Button v-if="canViewChats && selected.conversation_external_id" as-child variant="outline" size="sm">
                        <Link :href="logs.url(agent.external_id, { query: { conversation_id: selected.conversation_external_id } })">
                            <ExternalLink class="me-2 h-4 w-4" />
                            {{ t('agents.guardrails.open_conversation') }}
                        </Link>
                    </Button>
                </div>
            </div>
        </DialogContent>
    </Dialog>

    <BlockOffenderDialog v-model:open="blockOpen" :agent-id="agent.external_id" :trigger="selected" />
</template>
