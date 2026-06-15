<script setup lang="ts">
import LockedFeature from '@/components/LockedFeature.vue';
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
import { useCapabilityGuard } from '@/composables/useCapabilityGuard';
import { useDateFormat } from '@/composables/useDateFormat';
import { getInitials } from '@/composables/useInitials';
import { useTeamPermissions } from '@/composables/useTeamPermissions';
import AgentLayout from '@/layouts/AgentLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { Ban, Check, History, LoaderCircle, MessageSquareOff, Plus, Save, ShieldAlert, Target, Trash2, User, X } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { toast } from 'vue-sonner';

const { t } = useI18n();

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

const { hasCapability } = useCapabilityGuard();
const hasAccess = computed(() => hasCapability('guardrails'));

const { hasPermission } = useTeamPermissions();
const canManage = computed(() => hasPermission('agent.guardrails.manage'));

const activeTab = ref<'config' | 'activity'>('config');

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
    blocked_topics: d.blocked_topics.map((topic) => topic.trim()).filter(Boolean),
    allowed_scope: d.allowed_scope,
    refusal_message: d.refusal_message,
}));

const save = () => {
    form.post(route('agents.guardrails.update', props.agent.external_id), {
        preserveScroll: true,
        onSuccess: () => toast.success(t('agents.guardrails.saved')),
        onError: () => toast.error(t('agents.guardrails.save_error')),
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
    const cleaned = topicsDraft.value.map((topic) => topic.trim()).filter(Boolean);
    form.blocked_topics = [...new Set(cleaned)];
    topicsOpen.value = false;
};

const GUARDS = [
    {
        key: 'injection' as const,
        label: 'agents.guardrails.injection_label',
        help: 'agents.guardrails.injection_help',
    },
    { key: 'toxicity' as const, label: 'agents.guardrails.toxicity_label', help: 'agents.guardrails.toxicity_help' },
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
    <AgentLayout :agent="props.agent" :title="t('agents.guardrails.title')" :description="t('agents.guardrails.description')">
        <Head :title="t('agents.guardrails.head_title', { name: props.agent.name })" />

        <LockedFeature v-if="!hasAccess" :description="t('agents.guardrails.locked_description')" />

        <div v-else class="flex justify-center">
            <div class="min-h-[calc(100vh-220px)] w-full max-w-5xl">
                <Tabs v-model="activeTab">
                    <TabsList>
                        <TabsTrigger value="config" class="cursor-pointer">
                            <ShieldAlert class="h-4 w-4" />
                            {{ t('agents.guardrails.configuration') }}
                        </TabsTrigger>
                        <TabsTrigger value="activity" class="cursor-pointer">
                            <History class="h-4 w-4" />
                            {{ t('agents.guardrails.activity') }}
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
                                        <Label class="pointer-events-none text-sm font-medium">{{ t('agents.guardrails.enable_guardrails') }}</Label>
                                        <p class="text-muted-foreground text-sm">{{ t('agents.guardrails.master_switch') }}</p>
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
                                                <Label class="pointer-events-none text-sm font-medium">{{ t('agents.guardrails.pii_label') }}</Label>
                                                <p class="text-muted-foreground text-sm">
                                                    {{ t('agents.guardrails.pii_help') }}
                                                </p>
                                            </div>
                                            <Switch :model-value="piiEnabled" :disabled="!canManage" class="pointer-events-none" />
                                        </div>

                                        <div v-if="piiEnabled" class="flex flex-wrap items-center gap-2">
                                            <Badge v-for="filter in selectedFilters" :key="filter.key" variant="secondary" class="font-normal">{{
                                                filter.label
                                            }}</Badge>
                                            <Button v-if="canManage" type="button" variant="link" class="h-auto p-0 text-sm" @click="openFilters">
                                                <ShieldAlert class="me-1.5 h-4 w-4" />
                                                {{ t('agents.guardrails.manage_filters') }}
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
                                            <Label class="pointer-events-none text-sm font-medium">{{ t(guard.label) }}</Label>
                                            <p class="text-muted-foreground text-sm">{{ t(guard.help) }}</p>
                                        </div>
                                        <Switch v-model="form[guard.key]" :disabled="!canManage" class="pointer-events-none" />
                                    </div>

                                    <!-- Blocked topics -->
                                    <div class="space-y-1.5 pt-2">
                                        <Label>{{ t('agents.guardrails.blocked_topics') }}</Label>
                                        <div class="flex flex-wrap items-center gap-2">
                                            <Badge v-for="topic in form.blocked_topics" :key="topic" variant="secondary" class="font-normal">{{
                                                topic
                                            }}</Badge>
                                            <span v-if="!form.blocked_topics.length" class="text-muted-foreground text-sm">{{
                                                t('agents.guardrails.no_blocked_topics')
                                            }}</span>
                                            <Button v-if="canManage" type="button" variant="link" class="h-auto p-0 text-sm" @click="openTopics">
                                                <Ban class="me-1.5 h-4 w-4" />
                                                {{ t('agents.guardrails.manage_blocked_topics') }}
                                            </Button>
                                        </div>
                                        <p class="text-muted-foreground text-xs">
                                            {{ t('agents.guardrails.blocked_topics_help') }}
                                        </p>
                                    </div>

                                    <!-- Allowed scope -->
                                    <div class="space-y-1.5">
                                        <Label for="scope">{{ t('agents.guardrails.allowed_scope') }}</Label>
                                        <div class="relative">
                                            <Target class="text-muted-foreground absolute start-3 top-3 h-4 w-4" />
                                            <Textarea
                                                id="scope"
                                                v-model="form.allowed_scope"
                                                :disabled="!canManage"
                                                rows="2"
                                                class="ps-9"
                                                :placeholder="t('agents.guardrails.allowed_scope_placeholder')"
                                            />
                                        </div>
                                        <p class="text-muted-foreground text-xs">{{ t('agents.guardrails.allowed_scope_help') }}</p>
                                    </div>

                                    <!-- Refusal message -->
                                    <div class="space-y-1.5">
                                        <Label for="refusal">{{ t('agents.guardrails.refusal_message') }}</Label>
                                        <div class="relative">
                                            <MessageSquareOff class="text-muted-foreground absolute start-3 top-1/2 h-4 w-4 -translate-y-1/2" />
                                            <Input
                                                id="refusal"
                                                v-model="form.refusal_message"
                                                :disabled="!canManage"
                                                class="ps-9"
                                                :placeholder="t('agents.guardrails.refusal_message_placeholder')"
                                            />
                                        </div>
                                    </div>
                                </div>

                                <div v-if="canManage" class="flex justify-end">
                                    <Button :disabled="form.processing" @click="save">
                                        <LoaderCircle v-if="form.processing" class="me-2 h-4 w-4 animate-spin" />
                                        <Save v-else class="me-2 h-4 w-4" />
                                        {{ t('agents.guardrails.save') }}
                                    </Button>
                                </div>
                            </CardContent>
                        </Card>
                    </TabsContent>

                    <!-- Trigger log -->
                    <TabsContent value="activity" class="mt-6">
                        <section class="space-y-3">
                            <div>
                                <h2 class="text-base font-semibold">{{ t('agents.guardrails.recent_triggers') }}</h2>
                                <p class="text-muted-foreground text-sm">
                                    {{ t('agents.guardrails.triggers_logged', { total: props.triggers.total }) }}
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
                                            <TableCell class="text-muted-foreground text-end whitespace-nowrap">{{
                                                formatDateTime(row.created_at)
                                            }}</TableCell>
                                        </TableRow>
                                        <TableRow v-if="props.triggers.data.length === 0">
                                            <TableCell colspan="6" class="text-muted-foreground py-10 text-center text-sm">
                                                {{ t('agents.guardrails.no_triggers') }}
                                            </TableCell>
                                        </TableRow>
                                    </TableBody>
                                </Table>
                            </div>

                            <div v-if="props.triggers.last_page > 1" class="flex items-center justify-between">
                                <span class="text-muted-foreground text-sm">{{
                                    t('agents.guardrails.page_of', { current: props.triggers.current_page, last: props.triggers.last_page })
                                }}</span>
                                <div class="flex gap-2">
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        :disabled="!props.triggers.prev_page_url"
                                        @click="go(props.triggers.prev_page_url)"
                                        >{{ t('agents.guardrails.previous') }}</Button
                                    >
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        :disabled="!props.triggers.next_page_url"
                                        @click="go(props.triggers.next_page_url)"
                                        >{{ t('agents.guardrails.next') }}</Button
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
                    <DialogTitle>{{ t('agents.guardrails.pii_filters_title') }}</DialogTitle>
                    <DialogDescription>
                        {{ t('agents.guardrails.pii_filters_description') }}
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
                        <X class="me-2 h-4 w-4" />
                        {{ t('agents.guardrails.cancel') }}
                    </Button>
                    <Button type="button" @click="applyFilters">
                        <Check class="me-2 h-4 w-4" />
                        {{ t('agents.guardrails.done') }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Manage blocked topics -->
        <Dialog v-model:open="topicsOpen">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>{{ t('agents.guardrails.blocked_topics') }}</DialogTitle>
                    <DialogDescription>{{ t('agents.guardrails.blocked_topics_dialog_description') }}</DialogDescription>
                </DialogHeader>

                <div class="space-y-2">
                    <div v-for="(topic, index) in topicsDraft" :key="index" class="flex items-center gap-2">
                        <div class="relative flex-1">
                            <Ban class="text-muted-foreground absolute start-3 top-1/2 h-4 w-4 -translate-y-1/2" />
                            <Input
                                v-model="topicsDraft[index]"
                                :placeholder="
                                    index === 0 ? t('agents.guardrails.topic_placeholder_first') : t('agents.guardrails.topic_placeholder_more')
                                "
                                class="ps-9"
                                @keydown.enter.prevent="addTopic"
                            />
                        </div>
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            class="text-muted-foreground hover:text-destructive shrink-0"
                            :aria-label="t('agents.guardrails.remove_topic')"
                            @click="removeTopic(index)"
                        >
                            <Trash2 class="h-4 w-4" />
                        </Button>
                    </div>

                    <p v-if="!topicsDraft.length" class="text-muted-foreground text-sm">{{ t('agents.guardrails.no_topics_draft') }}</p>
                </div>

                <Button type="button" variant="outline" size="sm" class="w-fit" @click="addTopic">
                    <Plus class="me-2 h-4 w-4" />
                    {{ t('agents.guardrails.add_topic') }}
                </Button>

                <DialogFooter>
                    <Button type="button" variant="ghost" @click="topicsOpen = false">
                        <X class="me-2 h-4 w-4" />
                        {{ t('agents.guardrails.cancel') }}
                    </Button>
                    <Button type="button" @click="applyTopics">
                        <Check class="me-2 h-4 w-4" />
                        {{ t('agents.guardrails.done') }}
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
    </AgentLayout>
</template>
