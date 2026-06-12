<script setup lang="ts">
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { useDateFormat } from '@/composables/useDateFormat';
import { getInitials } from '@/composables/useInitials';
import { Link, router } from '@inertiajs/vue3';
import DOMPurify from 'dompurify';
import hljs from 'highlight.js';
import { User } from 'lucide-vue-next';
import { Marked } from 'marked';
import { markedHighlight } from 'marked-highlight';
import { computed, ref } from 'vue';

interface AuditRow {
    id: number;
    created_at: string | null;
    outcome: string;
    agent_id: number | null;
    agent_name: string;
    agent_external_id: string | null;
    user_id: number | null;
    user_name: string | null;
    avatar: string | null;
    trace_id: string | null;
    tool_name: string | null;
    tool_kind: string | null;
    connector_slug: string | null;
    connector_name: string | null;
    is_error: boolean;
    gated: boolean;
    latency_ms: number | null;
    arguments: unknown;
    result_text: string | null;
    conversation_external_id: string | null;
}

// Cursor paginator: the audit log is unbounded, so the backend never
// computes a total — prev/next URLs are the whole navigation contract.
interface Paginator<T> {
    data: T[];
    prev_page_url: string | null;
    next_page_url: string | null;
}

interface Props {
    rows: Paginator<AuditRow>;
    outcomes: string[];
    connectors: { slug: string; name: string }[];
    agents?: { id: number; name: string }[];
    showAgent?: boolean;
    filters: { outcome: string | null; agent_id: number | null; connector: string | null };
    routeName: string;
    routeParams?: Record<string, unknown>;
}

const props = withDefaults(defineProps<Props>(), { showAgent: false, agents: () => [], routeParams: () => ({}) });

const { formatDate } = useDateFormat();

const outcome = ref<string>(props.filters.outcome ?? '');
const connector = ref<string>(props.filters.connector ?? '');
const agentId = ref<string>(props.filters.agent_id ? String(props.filters.agent_id) : '');

const outcomeVariant = (value: string): 'default' | 'secondary' | 'destructive' | 'outline' => {
    switch (value) {
        case 'approved':
            return 'default';
        case 'denied':
        case 'rate_limited':
            return 'destructive';
        default:
            return 'secondary';
    }
};

const outcomeLabel = (value: string): string => value.replace('_', ' ');

const applyFilters = (): void => {
    router.get(
        route(props.routeName, props.routeParams),
        {
            outcome: outcome.value || undefined,
            connector: connector.value || undefined,
            agent_id: agentId.value || undefined,
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
            <div class="flex flex-col gap-1">
                <label class="text-muted-foreground text-xs font-medium" for="filter-outcome">Outcome</label>
                <select
                    id="filter-outcome"
                    v-model="outcome"
                    class="border-input bg-background h-9 rounded-md border px-3 text-sm"
                    @change="applyFilters"
                >
                    <option value="">All outcomes</option>
                    <option v-for="o in outcomes" :key="o" :value="o">{{ outcomeLabel(o) }}</option>
                </select>
            </div>

            <div class="flex flex-col gap-1">
                <label class="text-muted-foreground text-xs font-medium" for="filter-connector">Connector</label>
                <select
                    id="filter-connector"
                    v-model="connector"
                    class="border-input bg-background h-9 rounded-md border px-3 text-sm"
                    @change="applyFilters"
                >
                    <option value="">All connectors</option>
                    <option v-for="c in connectors" :key="c.slug" :value="c.slug">{{ c.name }}</option>
                </select>
            </div>

            <div v-if="showAgent" class="flex flex-col gap-1">
                <label class="text-muted-foreground text-xs font-medium" for="filter-agent">Agent</label>
                <select
                    id="filter-agent"
                    v-model="agentId"
                    class="border-input bg-background h-9 rounded-md border px-3 text-sm"
                    @change="applyFilters"
                >
                    <option value="">All agents</option>
                    <option v-for="a in agents" :key="a.id" :value="String(a.id)">{{ a.name }}</option>
                </select>
            </div>
        </div>

        <!-- Table -->
        <div class="rounded-md border">
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead>By</TableHead>
                        <TableHead v-if="showAgent">Agent</TableHead>
                        <TableHead>Tool</TableHead>
                        <TableHead>Connector</TableHead>
                        <TableHead>Outcome</TableHead>
                        <TableHead class="text-right">Latency</TableHead>
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
                                <span :class="row.user_name ? 'text-sm' : 'text-muted-foreground text-sm'">{{ row.user_name ?? 'visitor' }}</span>
                            </div>
                        </TableCell>
                        <TableCell v-if="showAgent" class="text-sm">{{ row.agent_name }}</TableCell>
                        <TableCell class="font-mono text-xs">
                            {{ row.tool_name }}
                            <span v-if="row.is_error" class="text-destructive ml-1">· error</span>
                        </TableCell>
                        <TableCell class="text-sm">{{ row.connector_name ?? '—' }}</TableCell>
                        <TableCell>
                            <Badge :variant="outcomeVariant(row.outcome)">{{ outcomeLabel(row.outcome) }}</Badge>
                            <Badge v-if="row.gated" variant="outline" class="ml-1">gated</Badge>
                        </TableCell>
                        <TableCell class="text-muted-foreground text-right text-sm">{{
                            row.latency_ms !== null ? `${row.latency_ms} ms` : '—'
                        }}</TableCell>
                    </TableRow>
                    <TableRow v-if="rows.data.length === 0">
                        <TableCell :colspan="colspan" class="text-muted-foreground py-10 text-center text-sm">No tool calls recorded yet.</TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </div>

        <!-- Pagination (cursor: only prev/next, no page count) -->
        <div v-if="rows.prev_page_url || rows.next_page_url" class="flex items-center justify-end">
            <div class="flex gap-2">
                <Button variant="outline" size="sm" :disabled="!rows.prev_page_url" @click="go(rows.prev_page_url)">Previous</Button>
                <Button variant="outline" size="sm" :disabled="!rows.next_page_url" @click="go(rows.next_page_url)">Next</Button>
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
                            <div class="text-muted-foreground text-xs">Agent</div>
                            <div>{{ selected.agent_name }}</div>
                        </div>
                        <div>
                            <div class="text-muted-foreground text-xs">Connector</div>
                            <div>{{ selected.connector_name ?? '—' }}</div>
                        </div>
                        <div>
                            <div class="text-muted-foreground text-xs">By</div>
                            <div>{{ selected.user_name ?? 'visitor' }}</div>
                        </div>
                        <div>
                            <div class="text-muted-foreground text-xs">Latency</div>
                            <div>{{ selected.latency_ms !== null ? `${selected.latency_ms} ms` : '—' }}</div>
                        </div>
                    </div>

                    <div>
                        <div class="text-muted-foreground text-xs">Arguments</div>
                        <pre
                            class="bg-muted mt-1 rounded-md p-3 text-xs break-words whitespace-pre-wrap"
                        ><code class="hljs language-json" v-html="highlightedArguments"></code></pre>
                    </div>

                    <div>
                        <div class="text-muted-foreground text-xs">Result</div>
                        <Tabs v-model="resultTab" class="mt-1">
                            <TabsList class="grid w-full grid-cols-2">
                                <TabsTrigger value="preview">Preview</TabsTrigger>
                                <TabsTrigger value="markdown">Markdown</TabsTrigger>
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
                        Conversation:
                        <Link
                            v-if="selected.agent_external_id && selected.conversation_external_id"
                            :href="route('agents.logs', { agent: selected.agent_external_id, conversation_id: selected.conversation_external_id })"
                            class="hover:text-foreground underline"
                        >
                            {{ selected.conversation_external_id }}
                        </Link>
                        <span v-else>{{ selected.conversation_external_id ?? '—' }}</span>
                        · Trace: {{ selected.trace_id ?? '—' }}
                    </div>
                </div>
            </DialogContent>
        </Dialog>
    </div>
</template>
