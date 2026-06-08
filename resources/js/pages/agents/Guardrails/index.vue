<script setup lang="ts">
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Textarea } from '@/components/ui/textarea';
import { getInitials } from '@/composables/useInitials';
import { useDateFormat } from '@/composables/useDateFormat';
import { useTeamPermissions } from '@/composables/useTeamPermissions';
import AgentLayout from '@/layouts/AgentLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { LoaderCircle, ShieldAlert, User } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';

interface GuardSettings {
    enabled: boolean;
    pii: boolean;
    injection: boolean;
    toxicity: boolean;
    blocked_topics: string[];
    allowed_scope: string;
    refusal_message: string;
}

interface Props {
    agent: { external_id: string; name: string };
    settings: GuardSettings;
    triggers: { data: any[]; current_page: number; last_page: number; total: number; prev_page_url: string | null; next_page_url: string | null };
    guardTypes: string[];
    outcomes: string[];
}

const props = defineProps<Props>();

const { hasPermission } = useTeamPermissions();
const canManage = computed(() => hasPermission('agent.guardrails.manage'));

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
    pii: props.settings.pii,
    injection: props.settings.injection,
    toxicity: props.settings.toxicity,
    blocked_topics_text: props.settings.blocked_topics.join(', '),
    allowed_scope: props.settings.allowed_scope,
    refusal_message: props.settings.refusal_message,
});

form.transform((d) => ({
    enabled: d.enabled,
    pii: d.pii,
    injection: d.injection,
    toxicity: d.toxicity,
    blocked_topics: d.blocked_topics_text
        .split(',')
        .map((t) => t.trim())
        .filter(Boolean),
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
const toggle = (key: 'enabled' | 'pii' | 'injection' | 'toxicity') => {
    if (canManage.value) form[key] = !form[key];
};

const GUARDS = [
    { key: 'pii' as const, label: 'PII detection & redaction', help: 'Mask emails, phones, cards and SSNs in the message and the answer.' },
    { key: 'injection' as const, label: 'Prompt-injection defense', help: 'Block jailbreak/override attempts, and strip injected instructions from retrieved sources.' },
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
            <div class="min-h-[calc(100vh-220px)] w-full max-w-5xl space-y-8">
                <!-- Settings -->
                <Card>
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2 text-base">
                            <ShieldAlert class="h-4 w-4" />
                            Configuration
                        </CardTitle>
                        <CardDescription>Off by default. Turn it on, then choose which guards run for this agent.</CardDescription>
                    </CardHeader>
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

                        <!-- Per-guard toggles — only shown once the master is on. -->
                        <div v-if="form.enabled" class="space-y-3">
                            <div
                                v-for="guard in GUARDS"
                                :key="guard.key"
                                class="flex items-start justify-between gap-4 pr-[13px]"
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
                                <Label for="blocked">Blocked topics</Label>
                                <Input id="blocked" v-model="form.blocked_topics_text" :disabled="!canManage" placeholder="e.g. medical advice, legal advice, competitors" />
                                <p class="text-muted-foreground text-xs">Comma-separated. The agent refuses questions matching these. Leave empty to allow all topics.</p>
                            </div>

                            <!-- Allowed scope -->
                            <div class="space-y-1.5">
                                <Label for="scope">Allowed scope (optional)</Label>
                                <Textarea id="scope" v-model="form.allowed_scope" :disabled="!canManage" rows="2" placeholder="Only answer questions about our product, billing, and support." />
                                <p class="text-muted-foreground text-xs">If set, the agent refuses anything outside this scope.</p>
                            </div>

                            <!-- Refusal message -->
                            <div class="space-y-1.5">
                                <Label for="refusal">Refusal message</Label>
                                <Input id="refusal" v-model="form.refusal_message" :disabled="!canManage" placeholder="Sorry, I can’t help with that request." />
                            </div>
                        </div>

                        <div v-if="canManage" class="flex justify-end">
                            <Button :disabled="form.processing" @click="save">
                                <LoaderCircle v-if="form.processing" class="mr-2 h-4 w-4 animate-spin" />
                                Save
                            </Button>
                        </div>
                    </CardContent>
                </Card>

                <!-- Trigger log -->
                <section class="space-y-3">
                    <div>
                        <h2 class="text-base font-semibold">Recent triggers</h2>
                        <p class="text-muted-foreground text-sm">{{ props.triggers.total }} logged · only blocks, redactions, and flags are recorded.</p>
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
                                            <span :class="row.user_name ? 'text-sm' : 'text-muted-foreground text-sm'">{{ row.user_name ?? 'visitor' }}</span>
                                        </div>
                                    </TableCell>
                                    <TableCell><Badge variant="outline">{{ guardLabel(row.guard_type) }}</Badge></TableCell>
                                    <TableCell class="text-muted-foreground text-sm capitalize">{{ row.stage }}</TableCell>
                                    <TableCell><Badge :variant="outcomeVariant(row.outcome)" class="capitalize">{{ row.outcome }}</Badge></TableCell>
                                    <TableCell class="text-muted-foreground text-sm capitalize">{{ row.severity ?? '—' }}</TableCell>
                                    <TableCell class="text-muted-foreground text-right whitespace-nowrap">{{ formatDateTime(row.created_at) }}</TableCell>
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
                        <span class="text-muted-foreground text-sm">Page {{ props.triggers.current_page }} of {{ props.triggers.last_page }}</span>
                        <div class="flex gap-2">
                            <Button variant="outline" size="sm" :disabled="!props.triggers.prev_page_url" @click="go(props.triggers.prev_page_url)">Previous</Button>
                            <Button variant="outline" size="sm" :disabled="!props.triggers.next_page_url" @click="go(props.triggers.next_page_url)">Next</Button>
                        </div>
                    </div>
                </section>
            </div>
        </div>

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
                        <p class="bg-muted/40 max-h-48 overflow-y-auto rounded-md p-3 break-words whitespace-pre-wrap">{{ selected.snippet || '—' }}</p>
                    </div>
                    <div v-if="selected.detail">
                        <p class="text-muted-foreground mb-1 text-xs font-medium uppercase">Detail</p>
                        <pre class="bg-muted/40 max-h-48 overflow-y-auto rounded-md p-3 text-xs whitespace-pre-wrap break-words">{{ prettyDetail(selected.detail) }}</pre>
                    </div>
                    <div class="text-muted-foreground text-xs">Conversation: {{ selected.conversation_external_id ?? '—' }} · Trace: {{ selected.trace_id ?? '—' }}</div>
                </div>
            </DialogContent>
        </Dialog>
    </AgentLayout>
</template>
