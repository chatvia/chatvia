<script lang="ts" setup>
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import Switch from '@/components/ui/switch/Switch.vue';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Textarea } from '@/components/ui/textarea';
import { useAgentAttachments } from '@/composables/useAgentAttachments';
import axios from 'axios';
import { Bot, Check, Copy, Gauge, KeyRound, LoaderCircle, Power, Trash2, Users, Wrench, X } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { toast } from 'vue-sonner';

type ChatterRow = App.Identity.Chatter.Data.WorkspaceChatterRowData;

interface Props {
    open: boolean;
    member: ChatterRow | null;
}

const props = defineProps<Props>();
const emit = defineEmits<{
    'update:open': [value: boolean];
    reset: [member: ChatterRow];
    delete: [member: ChatterRow];
    'access-changed': [member: ChatterRow, attachedCount: number];
    updated: [member: ChatterRow];
}>();

const agents = useAgentAttachments('member');
const idCopied = ref(false);

// Access controls (monthly message limit + deactivation). Edited locally and
// persisted on "Save changes"; the server returns the refreshed row to merge.
const messagesLimit = ref<number | string | null>(null);
const deactivated = ref(false);
const deactivationReason = ref('');
const isSaving = ref(false);

watch(
    () => props.member?.external_id,
    () => {
        messagesLimit.value = props.member?.messages_limit ?? null;
        deactivated.value = props.member?.is_deactivated ?? false;
        deactivationReason.value = props.member?.deactivation_reason ?? '';
    },
    { immediate: true },
);

const save = async () => {
    if (!props.member) return;
    isSaving.value = true;
    try {
        const raw = messagesLimit.value;
        const limit = raw === null || raw === '' ? null : Number(raw);
        const { data } = await axios.patch(route('settings.members.update', { chatter: props.member.external_id }), {
            messages_limit: limit,
            deactivated: deactivated.value,
            deactivation_reason: deactivationReason.value || null,
        });
        // Tolerate either a bare row or a { data: row } envelope.
        emit('updated', (data?.data ?? data) as ChatterRow);
        toast.success(`${props.member.name} updated`);
    } catch {
        toast.error('Could not update this member');
    } finally {
        isSaving.value = false;
    }
};

// One-click clear of the monthly limit. Sends the SAVED deactivation state (not
// the local toggle) so removing the limit can't apply an unsaved deactivation.
const removeLimit = async () => {
    if (!props.member) return;
    isSaving.value = true;
    try {
        const { data } = await axios.patch(route('settings.members.update', { chatter: props.member.external_id }), {
            messages_limit: null,
            deactivated: props.member.is_deactivated,
            deactivation_reason: props.member.deactivation_reason,
        });
        messagesLimit.value = null;
        emit('updated', (data?.data ?? data) as ChatterRow);
        toast.success(`Limit removed for ${props.member.name}`);
    } catch {
        toast.error('Could not remove the limit');
    } finally {
        isSaving.value = false;
    }
};

const initials = computed(() => {
    if (!props.member?.name) return '?';
    return props.member.name
        .trim()
        .split(/\s+/)
        .slice(0, 2)
        .map((p) => p[0]?.toUpperCase() ?? '')
        .join('');
});

const formatDate = (iso: string) =>
    new Date(iso).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });

// (Re)load the toggle list when the sheet opens or the focused member
// changes. Closing the sheet doesn't reset — keeping the cache means
// re-opening the same member is instant.
watch(
    [() => props.open, () => props.member?.external_id],
    ([isOpen, id]) => {
        if (isOpen && id) {
            agents.load(id);
        }
    },
    { immediate: true },
);

const onToggle = async (agentId: string) => {
    if (!props.member) return;
    await agents.toggleAttachment(props.member.external_id, agentId);
    emit('access-changed', props.member, agents.attached.value.length);
};

const copyId = async () => {
    if (!props.member) return;
    await navigator.clipboard.writeText(props.member.external_id);
    idCopied.value = true;
    setTimeout(() => (idCopied.value = false), 2000);
};
</script>

<template>
    <Sheet :open="open" @update:open="(v) => emit('update:open', v)">
        <SheetContent v-if="member" class="w-full overflow-y-auto p-0 sm:max-w-md">
            <SheetHeader class="space-y-4 border-b px-6 pt-7 pb-6">
                <div class="flex items-start gap-4">
                    <Avatar class="size-14 shrink-0">
                        <AvatarImage v-if="member.avatar" :src="member.avatar" :alt="member.name" />
                        <AvatarFallback class="text-base font-semibold">{{ initials }}</AvatarFallback>
                    </Avatar>
                    <div class="min-w-0 flex-1 pt-1">
                        <SheetTitle class="truncate text-lg">{{ member.name }}</SheetTitle>
                        <div class="mt-1.5 flex items-center gap-2 text-sm">
                            <span
                                v-if="member.status === 'pending'"
                                class="inline-flex items-center rounded-full border border-amber-200 bg-amber-50 px-2 py-0.5 text-xs font-medium text-amber-900 dark:border-amber-900/50 dark:bg-amber-950/30 dark:text-amber-100"
                            >
                                Pending
                            </span>
                            <span
                                v-else
                                class="inline-flex items-center rounded-full border border-green-200 bg-green-50 px-2 py-0.5 text-xs font-medium text-green-900 dark:border-green-900/50 dark:bg-green-950/30 dark:text-green-100"
                            >
                                Active
                            </span>
                            <span class="text-muted-foreground truncate">{{ member.email || 'No email' }}</span>
                        </div>
                    </div>
                </div>
                <SheetDescription class="sr-only">Manage this member's limits, agent access, and status.</SheetDescription>
                <div class="space-y-1.5">
                    <div class="flex items-center gap-1.5 text-xs">
                        <span class="text-muted-foreground">ID</span>
                        <code class="min-w-0 flex-1 truncate font-mono" :title="member.external_id">{{ member.external_id }}</code>
                        <Button variant="ghost" size="sm" class="h-6 shrink-0 px-1.5" title="Copy member ID" @click="copyId">
                            <Check v-if="idCopied" class="size-3.5 text-green-600" />
                            <Copy v-else class="size-3.5" />
                        </Button>
                    </div>
                    <p class="text-muted-foreground text-xs">Joined {{ formatDate(member.created_at) }}</p>
                </div>
            </SheetHeader>

            <Tabs default-value="limits" class="px-6 py-6">
                <TabsList class="grid w-full grid-cols-4">
                    <TabsTrigger value="limits"><Gauge class="mr-1.5 size-3.5" />Limits</TabsTrigger>
                    <TabsTrigger value="agents"><Bot class="mr-1.5 size-3.5" />Agents</TabsTrigger>
                    <TabsTrigger value="status"><Power class="mr-1.5 size-3.5" />Status</TabsTrigger>
                    <TabsTrigger value="actions"><Wrench class="mr-1.5 size-3.5" />Actions</TabsTrigger>
                </TabsList>

                <!-- LIMITS — monthly message cap -->
                <TabsContent value="limits" class="mt-6 space-y-6">
                    <div class="space-y-3">
                        <div>
                            <h3 class="text-sm font-semibold">Monthly message limit</h3>
                            <p class="text-muted-foreground mt-1 text-xs">
                                Cap how many messages this member can send each month. Leave empty for no limit.
                            </p>
                        </div>
                        <div class="flex items-center gap-2">
                            <div class="relative w-40 shrink-0">
                                <Gauge class="text-muted-foreground pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2" />
                                <Input v-model="messagesLimit" type="number" min="1" placeholder="No limit" class="pl-9" />
                            </div>
                            <Button :disabled="isSaving" @click="save">
                                <LoaderCircle v-if="isSaving" class="mr-2 size-4 animate-spin" />
                                <Check v-else class="mr-2 size-4" />
                                Save
                            </Button>
                            <Button
                                v-if="member.messages_limit !== null"
                                variant="ghost"
                                size="sm"
                                class="text-destructive hover:text-destructive shrink-0"
                                :disabled="isSaving"
                                @click="removeLimit"
                            >
                                <X class="mr-1 h-3.5 w-3.5" />
                                Remove limit
                            </Button>
                        </div>
                        <p v-if="member.messages_limit !== null" class="text-muted-foreground text-xs">
                            {{ member.messages_used }} of {{ member.messages_limit }} used this month.
                        </p>
                    </div>
                </TabsContent>

                <!-- AGENTS — grant / revoke access -->
                <TabsContent value="agents" class="mt-6 space-y-3">
                    <p class="text-muted-foreground text-xs">Toggle which of your agents this member can chat with.</p>

                    <div v-if="agents.loading.value" class="text-muted-foreground flex items-center gap-2 py-6 text-sm">
                        <LoaderCircle class="size-4 animate-spin" />
                        Loading agents
                    </div>

                    <div
                        v-else-if="agents.all.value.length === 0"
                        class="text-muted-foreground rounded-md border border-dashed py-6 text-center text-sm"
                    >
                        <Users class="text-muted-foreground/50 mx-auto mb-2 h-6 w-6" />
                        You have no agents in this workspace yet.
                    </div>

                    <ul v-else class="divide-border divide-y rounded-md border">
                        <li v-for="agent in agents.all.value" :key="agent.id" class="flex items-center justify-between gap-3 px-3 py-3">
                            <span class="truncate text-sm font-medium">{{ agent.name }}</span>
                            <Switch
                                :model-value="agent.attached"
                                :disabled="agents.busyId.value === agent.id"
                                @update:model-value="() => onToggle(agent.id)"
                            />
                        </li>
                    </ul>
                </TabsContent>

                <!-- STATUS — deactivation -->
                <TabsContent value="status" class="mt-6 space-y-4">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <h3 class="text-sm font-semibold">Deactivate member</h3>
                            <p class="text-muted-foreground mt-1 text-xs">A deactivated member can't sign in or send messages.</p>
                        </div>
                        <Switch :model-value="deactivated" @update:model-value="(v) => (deactivated = v)" />
                    </div>
                    <Textarea v-if="deactivated" v-model="deactivationReason" placeholder="Reason (optional) — visible to your team only" :rows="2" />
                    <Button :disabled="isSaving" @click="save">
                        <LoaderCircle v-if="isSaving" class="mr-2 h-4 w-4 animate-spin" />
                        Save changes
                    </Button>
                </TabsContent>

                <!-- ACTIONS -->
                <TabsContent value="actions" class="mt-6 space-y-2">
                    <Button
                        variant="outline"
                        class="w-full justify-start"
                        :disabled="!member.email || member.status === 'pending'"
                        @click="emit('reset', member)"
                    >
                        <KeyRound class="mr-2 h-4 w-4" />
                        Send password reset link
                    </Button>
                    <Button
                        variant="outline"
                        class="text-destructive hover:text-destructive hover:bg-destructive/10 w-full justify-start"
                        @click="emit('delete', member)"
                    >
                        <Trash2 class="mr-2 h-4 w-4" />
                        Remove member
                    </Button>
                </TabsContent>
            </Tabs>
        </SheetContent>
    </Sheet>
</template>
