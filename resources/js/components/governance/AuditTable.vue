<script setup lang="ts">
// The tool-call audit log table shared by the per-agent Audit page and the
// workspace Governance page: outcome / connector (/ agent) filters, cursor
// pagination, and a detail dialog with the call's arguments and result.
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { useDateFormat } from '@/composables/useDateFormat';
import { getInitials } from '@/composables/useInitials';
import { logs } from '@/routes/agents';
import { Link, router } from '@inertiajs/vue3';
import DOMPurify from 'dompurify';
import hljs from 'highlight.js';
import { User } from 'lucide-vue-next';
import { Marked } from 'marked';
import { markedHighlight } from 'marked-highlight';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import type { AuditPaginator, AuditRow } from './types';

const { t } = useI18n();

interface Props {
    rows: AuditPaginator;
    outcomes: string[];
    connectors: { slug: string; name: string }[];
    agents?: { id: number; name: string }[];
    showAgent?: boolean;
    filters: { outcome: string | null; agent_id: number | null; connector: string | null };
    /** The page the filters reload (without a query string). */
    url: string;
}

const props = withDefaults(defineProps<Props>(), { showAgent: false, agents: () => [] });

const { formatDate } = useDateFormat();

// A select item can't hold an empty value, so "no filter" is this sentinel.
const ALL = 'all';

const outcome = ref<string>(props.filters.outcome ?? ALL);
const connector = ref<string>(props.filters.connector ?? ALL);
const agentId = ref<string>(props.filters.agent_id ? String(props.filters.agent_id) : ALL);

const outcomeVariant = (value: string): 'default' | 'secondary' | 'destructive' | 'outline' => {
    switch (value) {
        case 'approved':
            return 'default';
        case 'denied':
        case 'rate_limited':
        case 'not_offered':
            return 'destructive';
        default:
            return 'secondary';
    }
};

const outcomeLabel = (value: string): string => {
    const key = `shared.audit_table.outcome_${value}`;
    const translated = t(key);
    return translated !== key ? translated : value.replace('_', ' ');
};

const formatLatency = (ms: number | null): string => (ms !== null ? t('shared.audit_table.latency_ms', { ms }) : '—');

const applyFilters = (): void => {
    router.get(
        props.url,
        {
            outcome: outcome.value === ALL ? undefined : outcome.value,
            connector: connector.value === ALL ? undefined : connector.value,
            agent_id: agentId.value === ALL ? undefined : agentId.value,
        },
        { preserveScroll: true, preserveState: true, replace: true },
    );
};

const go = (url: string | null): void => {
    if (url) router.get(url, {}, { preserveScroll: true, preserveState: true });
};

const selected = ref<AuditRow | null>(null);
const detailOpen = ref(false);
const openDetail = (row: AuditRow): void => {
    selected.value = row;
    detailOpen.value = true;
};

const prettyArguments = computed<string>(() => {
    const args = selected.value?.arguments;
    if (args === null || args === undefined) return '—';
    if (typeof args === 'string') return args;
    try {
        return JSON.stringify(args, null, 2);
    } catch {
        return String(args);
    }
});

// Syntax-highlight the arguments as JSON (or plaintext when they were truncated
// to a raw string). Colors come from the shared highlight.css theme.
const highlightedArguments = computed<string>(() => {
    const args = selected.value?.arguments;
    const language = args !== null && args !== undefined && typeof args === 'object' ? 'json' : 'plaintext';
    return hljs.highlight(prettyArguments.value, { language }).value;
});

// Tool results are usually markdown (the model emits headings, lists, bold).
// Offer a rendered Preview (default) alongside the raw Markdown source.
const resultTab = ref<'preview' | 'markdown'>('preview');

const markedResult = new Marked(
    markedHighlight({
        emptyLangClass: 'hljs',
        langPrefix: 'hljs language-',
        highlight(code, lang) {
            const language = hljs.getLanguage(lang) ? lang : 'plaintext';
            return hljs.highlight(code, { language }).value;
        },
    }),
).use({ gfm: true });

// The result is attacker-influenceable tool output rendered in a privileged
// admin view, so sanitize and forbid <img> — that closes the zero-click
// image-exfiltration channel without depending on the global DOMPurify hook
// registered by AssistantMessageBody (which may not be loaded on this page).
const renderedResult = computed<string>(() => {
    const text = selected.value?.result_text ?? '';
    if (!text) return '';
    const html = markedResult.parse(text);
    return DOMPurify.sanitize(typeof html === 'string' ? html : '', { ADD_ATTR: ['target'], FORBID_TAGS: ['img'] });
});

// account viewer column span (Cause + Agent + Tool + Connector + Outcome + Latency = 6),
// agent viewer omits the Agent column (5).
const colspan = computed(() => (props.showAgent ? 6 : 5));
</script>

<template>
    <div class="space-y-4">
        <!-- Filters -->
        <div class="flex flex-wrap items-end gap-3">
            <div class="flex flex-col gap-1.5">
                <Label class="text-muted-foreground text-xs" for="filter-outcome">{{ t('shared.audit_table.filter_outcome') }}</Label>
                <Select v-model="outcome" @update:model-value="applyFilters">
                    <SelectTrigger id="filter-outcome" class="min-w-40">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem :value="ALL">{{ t('shared.audit_table.all_outcomes') }}</SelectItem>
                        <SelectItem v-for="o in outcomes" :key="o" :value="o">{{ outcomeLabel(o) }}</SelectItem>
                    </SelectContent>
                </Select>
            </div>

            <div class="flex flex-col gap-1.5">
                <Label class="text-muted-foreground text-xs" for="filter-connector">{{ t('shared.audit_table.filter_connector') }}</Label>
                <Select v-model="connector" @update:model-value="applyFilters">
                    <SelectTrigger id="filter-connector" class="min-w-40">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem :value="ALL">{{ t('shared.audit_table.all_connectors') }}</SelectItem>
                        <SelectItem v-for="c in connectors" :key="c.slug" :value="c.slug">{{ c.name }}</SelectItem>
                    </SelectContent>
                </Select>
            </div>

            <div v-if="showAgent" class="flex flex-col gap-1.5">
                <Label class="text-muted-foreground text-xs" for="filter-agent">{{ t('shared.audit_table.filter_agent') }}</Label>
                <Select v-model="agentId" @update:model-value="applyFilters">
                    <SelectTrigger id="filter-agent" class="min-w-40">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem :value="ALL">{{ t('shared.audit_table.all_agents') }}</SelectItem>
                        <SelectItem v-for="a in agents" :key="a.id" :value="String(a.id)">{{ a.name }}</SelectItem>
                    </SelectContent>
                </Select>
            </div>
        </div>

        <!-- Table -->
        <div class="rounded-md border">
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead>{{ t('shared.audit_table.col_by') }}</TableHead>
                        <TableHead v-if="showAgent">{{ t('shared.audit_table.col_agent') }}</TableHead>
                        <TableHead>{{ t('shared.audit_table.col_tool') }}</TableHead>
                        <TableHead>{{ t('shared.audit_table.col_connector') }}</TableHead>
                        <TableHead>{{ t('shared.audit_table.col_outcome') }}</TableHead>
                        <TableHead class="text-end">{{ t('shared.audit_table.col_latency') }}</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow v-for="row in rows.data" :key="row.id" class="hover:bg-muted/50 cursor-pointer" @click="openDetail(row)">
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
                                    row.user_name ?? t('shared.audit_table.visitor')
                                }}</span>
                            </div>
                        </TableCell>
                        <TableCell v-if="showAgent" class="text-sm">{{ row.agent_name }}</TableCell>
                        <TableCell class="font-mono text-xs">
                            {{ row.tool_name }}
                            <span v-if="row.is_error" class="text-destructive ms-1">· {{ t('shared.audit_table.error_suffix') }}</span>
                        </TableCell>
                        <TableCell class="text-sm">{{ row.connector_name ?? '—' }}</TableCell>
                        <TableCell>
                            <Badge :variant="outcomeVariant(row.outcome)">{{ outcomeLabel(row.outcome) }}</Badge>
                            <Badge v-if="row.gated" variant="outline" class="ms-1">{{ t('shared.audit_table.gated') }}</Badge>
                        </TableCell>
                        <TableCell class="text-muted-foreground text-end text-sm">{{ formatLatency(row.latency_ms) }}</TableCell>
                    </TableRow>
                    <TableRow v-if="rows.data.length === 0">
                        <TableCell :colspan="colspan" class="text-muted-foreground py-10 text-center text-sm">{{
                            t('shared.audit_table.empty')
                        }}</TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </div>

        <!-- Pagination (cursor: only prev/next, no page count) -->
        <div v-if="rows.prev_page_url || rows.next_page_url" class="flex items-center justify-end">
            <div class="flex gap-2">
                <Button variant="outline" size="sm" :disabled="!rows.prev_page_url" @click="go(rows.prev_page_url)">{{
                    t('shared.audit_table.previous')
                }}</Button>
                <Button variant="outline" size="sm" :disabled="!rows.next_page_url" @click="go(rows.next_page_url)">{{
                    t('shared.audit_table.next')
                }}</Button>
            </div>
        </div>

        <!-- Detail -->
        <Dialog v-model:open="detailOpen">
            <DialogContent class="max-h-[85vh] overflow-y-auto sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle class="font-mono text-sm">{{ selected?.tool_name }}</DialogTitle>
                    <DialogDescription>
                        {{ selected ? outcomeLabel(selected.outcome) : '' }} · {{ formatDate(selected?.created_at ?? null) }}
                    </DialogDescription>
                </DialogHeader>

                <div v-if="selected" class="space-y-4 text-sm">
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <div class="text-muted-foreground text-xs">{{ t('shared.audit_table.col_agent') }}</div>
                            <div>{{ selected.agent_name }}</div>
                        </div>
                        <div>
                            <div class="text-muted-foreground text-xs">{{ t('shared.audit_table.col_connector') }}</div>
                            <div>{{ selected.connector_name ?? '—' }}</div>
                        </div>
                        <div>
                            <div class="text-muted-foreground text-xs">{{ t('shared.audit_table.col_by') }}</div>
                            <div>{{ selected.user_name ?? t('shared.audit_table.visitor') }}</div>
                        </div>
                        <div>
                            <div class="text-muted-foreground text-xs">{{ t('shared.audit_table.col_latency') }}</div>
                            <div>{{ formatLatency(selected.latency_ms) }}</div>
                        </div>
                    </div>

                    <div v-if="selected.decision_note">
                        <div class="text-muted-foreground text-xs">{{ t('shared.audit_table.detail_spoken_approval') }}</div>
                        <p class="bg-muted mt-1 rounded-md p-3 text-sm italic">{{ selected.decision_note }}</p>
                    </div>

                    <div>
                        <div class="text-muted-foreground text-xs">{{ t('shared.audit_table.detail_arguments') }}</div>
                        <pre
                            class="bg-muted mt-1 rounded-md p-3 text-xs break-words whitespace-pre-wrap"
                        ><code class="hljs language-json" v-html="highlightedArguments"></code></pre>
                    </div>

                    <div>
                        <div class="text-muted-foreground text-xs">{{ t('shared.audit_table.detail_result') }}</div>
                        <Tabs v-model="resultTab" class="mt-1">
                            <TabsList class="grid w-full grid-cols-2">
                                <TabsTrigger value="preview">{{ t('shared.audit_table.tab_preview') }}</TabsTrigger>
                                <TabsTrigger value="markdown">{{ t('shared.audit_table.tab_markdown') }}</TabsTrigger>
                            </TabsList>
                            <TabsContent value="preview" class="mt-2">
                                <div
                                    v-if="renderedResult"
                                    class="prose prose-sm dark:prose-invert bg-muted max-h-80 max-w-none overflow-y-auto rounded-md p-3"
                                    v-html="renderedResult"
                                />
                                <div v-else class="bg-muted text-muted-foreground rounded-md p-3 text-xs">—</div>
                            </TabsContent>
                            <TabsContent value="markdown" class="mt-2">
                                <pre class="bg-muted max-h-80 overflow-y-auto rounded-md p-3 text-xs break-words whitespace-pre-wrap">{{
                                    selected.result_text ?? '—'
                                }}</pre>
                            </TabsContent>
                        </Tabs>
                    </div>

                    <div class="text-muted-foreground text-xs">
                        {{ t('shared.audit_table.detail_conversation') }}
                        <Link
                            v-if="selected.agent_external_id && selected.conversation_external_id"
                            :href="logs.url(selected.agent_external_id, { query: { conversation_id: selected.conversation_external_id } })"
                            class="hover:text-foreground underline"
                        >
                            {{ selected.conversation_external_id }}
                        </Link>
                        <span v-else>{{ selected.conversation_external_id ?? '—' }}</span>
                        · {{ t('shared.audit_table.detail_trace') }} {{ selected.trace_id ?? '—' }}
                    </div>
                </div>
            </DialogContent>
        </Dialog>
    </div>
</template>
