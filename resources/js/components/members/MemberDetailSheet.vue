<script lang="ts" setup>
import { Button } from '@/components/ui/button';
import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import Switch from '@/components/ui/switch/Switch.vue';
import { useAgentAttachments } from '@/composables/useAgentAttachments';
import { Check, Copy, KeyRound, LoaderCircle, Trash2, Users } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

type ChatterRow = Domain.Auth.Data.WorkspaceChatterRowData;

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
}>();

const agents = useAgentAttachments('member');
const idCopied = ref(false);

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
            <SheetHeader class="space-y-5 border-b px-6 pt-7 pb-6">
                <div class="flex items-start gap-4">
                    <div class="bg-muted text-foreground flex h-14 w-14 shrink-0 items-center justify-center rounded-full text-base font-semibold">
                        {{ initials }}
                    </div>
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
                <SheetDescription class="text-sm leading-relaxed">
                    A chat member of your workspace. Below is everything they can do and how to identify them via the API.
                </SheetDescription>
            </SheetHeader>

            <div class="space-y-6 px-6 py-6">
                <!-- Identity / metadata -->
                <dl class="divide-border divide-y text-sm">
                    <div class="grid grid-cols-3 gap-4 py-3 first:pt-0">
                        <dt class="text-muted-foreground col-span-1">Member ID</dt>
                        <dd class="col-span-2 flex min-w-0 items-center gap-1.5">
                            <code class="min-w-0 flex-1 truncate font-mono text-xs" :title="member.external_id">{{ member.external_id }}</code>
                            <Button variant="ghost" size="sm" class="h-6 shrink-0 px-1.5" title="Copy member ID" @click="copyId">
                                <Check v-if="idCopied" class="size-3.5 text-green-600" />
                                <Copy v-else class="size-3.5" />
                            </Button>
                        </dd>
                    </div>
                    <div class="grid grid-cols-3 gap-4 py-3">
                        <dt class="text-muted-foreground col-span-1">Email</dt>
                        <dd class="col-span-2 break-all">{{ member.email || '—' }}</dd>
                    </div>
                    <div class="grid grid-cols-3 gap-4 py-3">
                        <dt class="text-muted-foreground col-span-1">Created</dt>
                        <dd class="col-span-2">{{ formatDate(member.created_at) }}</dd>
                    </div>
                </dl>

                <!-- Agents the member can access — toggle to grant / revoke -->
                <div class="space-y-3">
                    <div>
                        <h3 class="text-sm font-semibold">Agents</h3>
                        <p class="text-muted-foreground mt-1 text-xs">Toggle which of your agents this member can chat with.</p>
                    </div>

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
                </div>

                <!-- Actions -->
                <div class="space-y-2 border-t pt-6">
                    <h3 class="text-sm font-semibold">Actions</h3>
                    <div class="flex flex-col gap-2">
                        <Button
                            variant="outline"
                            class="justify-start"
                            :disabled="!member.email || member.status === 'pending'"
                            @click="emit('reset', member)"
                        >
                            <KeyRound class="mr-2 h-4 w-4" />
                            Send password reset link
                        </Button>
                        <Button
                            variant="outline"
                            class="text-destructive hover:text-destructive hover:bg-destructive/10 justify-start"
                            @click="emit('delete', member)"
                        >
                            <Trash2 class="mr-2 h-4 w-4" />
                            Remove member
                        </Button>
                    </div>
                </div>
            </div>
        </SheetContent>
    </Sheet>
</template>
