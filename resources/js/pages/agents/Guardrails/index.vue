<script setup lang="ts">
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Textarea } from '@/components/ui/textarea';
import { useDateFormat } from '@/composables/useDateFormat';
import { getInitials } from '@/composables/useInitials';
import { useTeamPermissions } from '@/composables/useTeamPermissions';
import AgentLayout from '@/layouts/AgentLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { Ban, Check, History, LoaderCircle, MessageSquareOff, Plus, Save, ShieldAlert, Target, Trash2, User, X } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';

interface GuardSettings {
    enabled: boolean;
    filters: string[];
    injection: boolean;
    toxicity: boolean;
    blocked_topics: string[];
    allowed_scope: string;
    refusal_message: string;
}

interface Props {
    agent: { external_id: string; name: string };
    settings: GuardSettings;
    availableFilters: { key: string; label: string }[];
    defaultFilters: string[];
    triggers: { data: any[]; current_page: number; last_page: number; total: number; prev_page_url: string | null; next_page_url: string | null };
    guardTypes: string[];
    outcomes: string[];
}

const props = defineProps<Props>();

const { hasPermission } = useTeamPermissions();
const canManage = computed(() => hasPermission('agent.guardrails.manage'));

const activeTab = ref<'config' | 'activity'>('config');

const { formatDateTime } = useDateFormat();

const GUARD_LABELS: Record<string, string> = {
    prompt_injection: 'Prompt injection',
    pii: 'PII',
    blocked_topic: 'Blocked topic',
    toxicity: 'Toxicity',
};
const guardLabel = (t: string) => GUARD_LABELS[t] ?? t;

const outcomeVariant = (o: string): 'default' | 'secondary' | 'destructive' | 'outline' => {
    if (o === 'blocked') return 'destructive';
    if (o === 'redacted') return 'secondary';
    return 'outline';
};

// ---- Settings form ---------------------------------------------------------

const form = useForm({
    enabled: props.settings.enabled,
    filters: [...props.settings.filters],
    injection: props.settings.injection,
    toxicity: props.settings.toxicity,
    blocked_topics: [...props.settings.blocked_topics],
    allowed_scope: props.settings.allowed_scope,
    refusal_message: props.settings.refusal_message,
});

form.transform((d) => ({
    enabled: d.enabled,
    filters: [...d.filters],
    injection: d.injection,
    toxicity: d.toxicity,
    blocked_topics: d.blocked_topics.map((t) => t.trim()).filter(Boolean),
    allowed_scope: d.allowed_scope,
    refusal_message: d.refusal_message,
}));

const save = () => {
    form.post(route('agents.guardrails.update', props.agent.external_id), {
        preserveScroll: true,
        onSuccess: () => toast.success('Guardrails saved'),
        onError: () => toast.error('Could not save guardrails'),
    });
};

// Toggle a boolean field — bound to the whole row's click so the label and
// description area are part of the hit target, not just the switch.
const toggle = (key: 'enabled' | 'injection' | 'toxicity') => {
    if (canManage.value) form[key] = !form[key];
};

// ---- PII filters editor ----------------------------------------------------
// Mirrors the blocked-topics pattern: the card shows the enabled filters as
// badges; the toggles live in a dialog editing a draft that only commits to the
// form on "Done" (escape/overlay discards). An empty list = PII redaction off.
const filtersOpen = ref(false);
const filtersDraft = ref<string[]>([]);

// Master toggle for the PII guard is derived from the list: a non-empty
// selection means it's on. Remember the last selection so toggling off then on
// restores it (falling back to the server defaults on first enable).
const lastFilters = ref<string[]>(props.settings.filters.length ? [...props.settings.filters] : [...props.defaultFilters]);
const piiEnabled = computed(() => form.filters.length > 0);

const togglePii = () => {
    if (!canManage.value) return;
    if (form.filters.length) {
        lastFilters.value = [...form.filters];
        form.filters = [];
    } else {
        form.filters = lastFilters.value.length ? [...lastFilters.value] : [...props.defaultFilters];
    }
};

const selectedFilters = computed(() => props.availableFilters.filter((f) => form.filters.includes(f.key)));

const openFilters = () => {
    filtersDraft.value = [...form.filters];
    filtersOpen.value = true;
};
const toggleDraftFilter = (key: string) => {
    const i = filtersDraft.value.indexOf(key);
    if (i === -1) filtersDraft.value.push(key);
    else filtersDraft.value.splice(i, 1);
};
const applyFilters = () => {
    form.filters = [...filtersDraft.value];
    filtersOpen.value = false;
};

// ---- Blocked topics editor -------------------------------------------------
// The card shows the saved topics as badges; the full add/remove list lives in
// a dialog (mirrors the StartersSection pattern). The dialog edits a draft and
// only commits to the form on "Done" — closing via escape/overlay discards it.
const topicsOpen = ref(false);
const topicsDraft = ref<string[]>([]);

const openTopics = () => {
    topicsDraft.value = form.blocked_topics.length ? [...form.blocked_topics] : [''];
    topicsOpen.value = true;
};
const addTopic = () => topicsDraft.value.push('');
const removeTopic = (index: number) => topicsDraft.value.splice(index, 1);
const applyTopics = () => {
    const cleaned = topicsDraft.value.map((t) => t.trim()).filter(Boolean);
    form.blocked_topics = [...new Set(cleaned)];
    topicsOpen.value = false;
};

const GUARDS = [
    {
        key: 'injection' as const,
        label: 'Prompt-injection defense',
        help: 'Block jailbreak/override attempts, and strip injected instructions from retrieved sources.',
    },
    { key: 'toxicity' as const, label: 'Toxicity / moderation', help: 'Block abusive input and replace an unsafe answer.' },
];

// ---- Trigger detail --------------------------------------------------------

const selected = ref<any | null>(null);
const detailOpen = ref(false);
const openDetail = (row: any) => {
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
    <AgentLayout
        :agent="props.agent"
        title="Guardrails"
        description="AI-safety guards on live chat: PII redaction, prompt-injection defense, blocked topics, and toxicity — applied to the visitor's message and the agent's answer."
    >
        <Head :title="`Guardrails — ${props.agent.name}`" />

        <div class="flex justify-center">
            <div class="min-h-[calc(100vh-220px)] w-full max-w-5xl">
                <Tabs v-model="activeTab">
                    <TabsList>
                        <TabsTrigger value="config" class="cursor-pointer">
                            <ShieldAlert class="h-4 w-4" />
                            Configuration
                        </TabsTrigger>
                        <TabsTrigger value="activity" class="cursor-pointer">
                            <History class="h-4 w-4" />
                            Activity
                        </TabsTrigger>
                    </TabsList>

                    <!-- Settings -->
                    <TabsContent value="config" class="mt-6">
                        <Card>
                            <CardContent class="space-y-6">
                                <!-- Master switch -->
                                <div
                                    class="flex items-center justify-between gap-4 rounded-md border p-3"
                                    :class="{ 'cursor-pointer': canManage }"
                                    @click="toggle('enabled')"
                                >
                                    <div>
                                        <Label class="pointer-events-none text-sm font-medium">Enable guardrails</Label>
                                        <p class="text-muted-foreground text-sm">Master switch for this agent.</p>
                                    </div>
                                    <Switch v-model="form.enabled" :disabled="!canManage" class="pointer-events-none" />
                                </div>

                                <!-- Per-guard toggles — only shown once the master is on.
                                     px-[13px] mirrors the master card's content inset
                                     (1px border + p-3) so every label, switch, and input
                                     lines up with the Enable-guardrails row above. -->
                                <div v-if="form.enabled" class="space-y-3 px-[13px]">
                                    <!-- PII detection & redaction — master toggle + managed filter list -->
                                    <div class="space-y-2">
                                        <div
                                            class="flex items-start justify-between gap-4"
                                            :class="{ 'cursor-pointer': canManage }"
                                            @click="togglePii"
                                        >
                                            <div>
                                                <Label class="pointer-events-none text-sm font-medium">PII detection &amp; redaction</Label>
                                                <p class="text-muted-foreground text-sm">
                                                    Replace the selected identifiers with placeholders like [EMAIL_1] in the message and the answer.
                                                </p>
                                            </div>
                                            <Switch :model-value="piiEnabled" :disabled="!canManage" class="pointer-events-none" />
                                        </div>

                                        <div v-if="piiEnabled" class="flex flex-wrap items-center gap-2">
                                            <Badge v-for="filter in selectedFilters" :key="filter.key" variant="secondary" class="font-normal">{{
                                                filter.label
                                            }}</Badge>
                                            <Button v-if="canManage" type="button" variant="link" class="h-auto p-0 text-sm" @click="openFilters">
                                                <ShieldAlert class="mr-1.5 h-4 w-4" />
                                                Manage filters
                                            </Button>
                                        </div>
                                    </div>

                                    <div
                                        v-for="guard in GUARDS"
                                        :key="guard.key"
                                        class="flex items-start justify-between gap-4"
                                        :class="{ 'cursor-pointer': canManage }"
                                        @click="toggle(guard.key)"
                                    >
                                        <div>
                                            <Label class="pointer-events-none text-sm font-medium">{{ guard.label }}</Label>
                                            <p class="text-muted-foreground text-sm">{{ guard.help }}</p>
                                        </div>
                                        <Switch v-model="form[guard.key]" :disabled="!canManage" class="pointer-events-none" />
                                    </div>

                                    <!-- Blocked topics -->
                                    <div class="space-y-1.5 pt-2">
                                        <Label>Blocked topics</Label>
                                        <div class="flex flex-wrap items-center gap-2">
                                            <Badge v-for="topic in form.blocked_topics" :key="topic" variant="secondary" class="font-normal">{{
                                                topic
                                            }}</Badge>
                                            <span v-if="!form.blocked_topics.length" class="text-muted-foreground text-sm"
                                                >None yet — all topics are allowed.</span
                                            >
                                            <Button v-if="canManage" type="button" variant="link" class="h-auto p-0 text-sm" @click="openTopics">
                                                <Ban class="mr-1.5 h-4 w-4" />
                                                Manage blocked topics
                                            </Button>
                                        </div>
                                        <p class="text-muted-foreground text-xs">
                                            The agent refuses questions matching these. Leave empty to allow all topics.
                                        </p>
                                    </div>

                                    <!-- Allowed scope -->
                                    <div class="space-y-1.5">
                                        <Label for="scope">Allowed scope (optional)</Label>
                                        <div class="relative">
                                            <Target class="text-muted-foreground absolute top-3 left-3 h-4 w-4" />
                                            <Textarea
                                                id="scope"
                                                v-model="form.allowed_scope"
                                                :disabled="!canManage"
                                                rows="2"
                                                class="pl-9"
                                                placeholder="Only answer questions about our product, billing, and support."
                                            />
                                        </div>
                                        <p class="text-muted-foreground text-xs">If set, the agent refuses anything outside this scope.</p>
                                    </div>

                                    <!-- Refusal message -->
                                    <div class="space-y-1.5">
                                        <Label for="refusal">Refusal message</Label>
                                        <div class="relative">
                                            <MessageSquareOff class="text-muted-foreground absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2" />
                                            <Input
                                                id="refusal"
                                                v-model="form.refusal_message"
                                                :disabled="!canManage"
                                                class="pl-9"
                                                placeholder="Sorry, I can’t help with that request."
                                            />
                                        </div>
                                    </div>
                                </div>

                                <div v-if="canManage" class="flex justify-end">
                                    <Button :disabled="form.processing" @click="save">
                                        <LoaderCircle v-if="form.processing" class="mr-2 h-4 w-4 animate-spin" />
                                        <Save v-else class="mr-2 h-4 w-4" />
                                        Save
                                    </Button>
                                </div>
                            </CardContent>
                        </Card>
                    </TabsContent>

                    <!-- Trigger log -->
                    <TabsContent value="activity" class="mt-6">
                        <section class="space-y-3">
                            <div>
                                <h2 class="text-base font-semibold">Recent triggers</h2>
                                <p class="text-muted-foreground text-sm">
                                    {{ props.triggers.total }} logged · only blocks, redactions, and flags are recorded.
                                </p>
                            </div>

                            <div class="bg-card rounded-lg border">
                                <Table class="[&_td]:px-4 [&_td]:py-3 [&_th]:px-4">
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>By</TableHead>
                                            <TableHead>Guard</TableHead>
                                            <TableHead>Stage</TableHead>
                                            <TableHead>Outcome</TableHead>
                                            <TableHead>Severity</TableHead>
                                            <TableHead class="text-right">When</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        <TableRow
                                            v-for="row in props.triggers.data"
                                            :key="row.id"
                                            class="hover:bg-muted/50 cursor-pointer"
                                            @click="openDetail(row)"
                                        >
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
                                                        row.user_name ?? 'visitor'
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
                                            <TableCell class="text-muted-foreground text-right whitespace-nowrap">{{
                                                formatDateTime(row.created_at)
                                            }}</TableCell>
                                        </TableRow>
                                        <TableRow v-if="props.triggers.data.length === 0">
                                            <TableCell colspan="6" class="text-muted-foreground py-10 text-center text-sm">
                                                No triggers yet — every message has passed the guards (or guards are off).
                                            </TableCell>
                                        </TableRow>
                                    </TableBody>
                                </Table>
                            </div>

                            <div v-if="props.triggers.last_page > 1" class="flex items-center justify-between">
                                <span class="text-muted-foreground text-sm"
                                    >Page {{ props.triggers.current_page }} of {{ props.triggers.last_page }}</span
                                >
                                <div class="flex gap-2">
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        :disabled="!props.triggers.prev_page_url"
                                        @click="go(props.triggers.prev_page_url)"
                                        >Previous</Button
                                    >
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        :disabled="!props.triggers.next_page_url"
                                        @click="go(props.triggers.next_page_url)"
                                        >Next</Button
                                    >
                                </div>
                            </div>
                        </section>
                    </TabsContent>
                </Tabs>
            </div>
        </div>

        <!-- Manage PII filters -->
        <Dialog v-model:open="filtersOpen">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>PII filters</DialogTitle>
                    <DialogDescription>
                        Choose which identifiers to detect and replace with placeholders. Leave all off to disable PII redaction.
                    </DialogDescription>
                </DialogHeader>

                <div class="space-y-2">
                    <div
                        v-for="filter in props.availableFilters"
                        :key="filter.key"
                        class="flex items-center justify-between gap-3 rounded-md border p-3"
                        :class="{ 'cursor-pointer': canManage }"
                        @click="canManage && toggleDraftFilter(filter.key)"
                    >
                        <Label class="pointer-events-none text-sm font-medium">{{ filter.label }}</Label>
                        <Switch :model-value="filtersDraft.includes(filter.key)" :disabled="!canManage" class="pointer-events-none" />
                    </div>
                </div>

                <DialogFooter>
                    <Button type="button" variant="ghost" @click="filtersOpen = false">
                        <X class="mr-2 h-4 w-4" />
                        Cancel
                    </Button>
                    <Button type="button" @click="applyFilters">
                        <Check class="mr-2 h-4 w-4" />
                        Done
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Manage blocked topics -->
        <Dialog v-model:open="topicsOpen">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Blocked topics</DialogTitle>
                    <DialogDescription
                        >The agent refuses any question matching one of these topics. Leave the list empty to allow all topics.</DialogDescription
                    >
                </DialogHeader>

                <div class="space-y-2">
                    <div v-for="(topic, index) in topicsDraft" :key="index" class="flex items-center gap-2">
                        <div class="relative flex-1">
                            <Ban class="text-muted-foreground absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2" />
                            <Input
                                v-model="topicsDraft[index]"
                                :placeholder="index === 0 ? 'e.g. medical advice' : 'Add another topic'"
                                class="pl-9"
                                @keydown.enter.prevent="addTopic"
                            />
                        </div>
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            class="text-muted-foreground hover:text-destructive shrink-0"
                            aria-label="Remove topic"
                            @click="removeTopic(index)"
                        >
                            <Trash2 class="h-4 w-4" />
                        </Button>
                    </div>

                    <p v-if="!topicsDraft.length" class="text-muted-foreground text-sm">No topics — the agent answers about anything.</p>
                </div>

                <Button type="button" variant="outline" size="sm" class="w-fit" @click="addTopic">
                    <Plus class="mr-2 h-4 w-4" />
                    Add topic
                </Button>

                <DialogFooter>
                    <Button type="button" variant="ghost" @click="topicsOpen = false">
                        <X class="mr-2 h-4 w-4" />
                        Cancel
                    </Button>
                    <Button type="button" @click="applyTopics">
                        <Check class="mr-2 h-4 w-4" />
                        Done
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Trigger detail -->
        <Dialog v-model:open="detailOpen">
            <DialogContent class="max-h-[85vh] overflow-y-auto sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle class="flex items-center gap-2">
                        {{ selected ? guardLabel(selected.guard_type) : '' }}
                        <Badge v-if="selected" :variant="outcomeVariant(selected.outcome)" class="capitalize">{{ selected?.outcome }}</Badge>
                    </DialogTitle>
                    <DialogDescription>
                        {{ selected?.stage }} · {{ selected ? formatDateTime(selected.created_at) : '' }} · by {{ selected?.user_name ?? 'visitor' }}
                    </DialogDescription>
                </DialogHeader>

                <div v-if="selected" class="space-y-4 text-sm">
                    <div>
                        <p class="text-muted-foreground mb-1 text-xs font-medium uppercase">Snippet (redacted, capped)</p>
                        <p class="bg-muted/40 max-h-48 overflow-y-auto rounded-md p-3 break-words whitespace-pre-wrap">
                            {{ selected.snippet || '—' }}
                        </p>
                    </div>
                    <div v-if="selected.detail">
                        <p class="text-muted-foreground mb-1 text-xs font-medium uppercase">Detail</p>
                        <pre class="bg-muted/40 max-h-48 overflow-y-auto rounded-md p-3 text-xs break-words whitespace-pre-wrap">{{
                            prettyDetail(selected.detail)
                        }}</pre>
                    </div>
                    <div class="text-muted-foreground text-xs">
                        Conversation: {{ selected.conversation_external_id ?? '—' }} · Trace: {{ selected.trace_id ?? '—' }}
                    </div>
                </div>
            </DialogContent>
        </Dialog>
    </AgentLayout>
</template>
