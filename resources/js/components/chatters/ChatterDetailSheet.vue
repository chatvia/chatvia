<script lang="ts" setup>
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import Switch from '@/components/ui/switch/Switch.vue';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Textarea } from '@/components/ui/textarea';
import { useAgentAttachments } from '@/composables/useAgentAttachments';
import { useDateFormat } from '@/composables/useDateFormat';
import axios from 'axios';
import { Bot, Check, Copy, Gauge, KeyRound, LoaderCircle, Power, Trash2, Users, Wrench, X } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { toast } from 'vue-sonner';

const { t } = useI18n();
const { formatDate } = useDateFormat();

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

const agents = useAgentAttachments('chatter');
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
        const { data } = await axios.patch(route('people.chatters.update', { chatter: props.member.external_id }), {
            messages_limit: limit,
            deactivated: deactivated.value,
            deactivation_reason: deactivationReason.value || null,
        });
        // Tolerate either a bare row or a { data: row } envelope.
        emit('updated', (data?.data ?? data) as ChatterRow);
        toast.success(t('shared.member_sheet.toast_updated', { name: props.member.name }));
    } catch {
        toast.error(t('shared.member_sheet.toast_update_failed'));
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
        const { data } = await axios.patch(route('people.chatters.update', { chatter: props.member.external_id }), {
            messages_limit: null,
            deactivated: props.member.is_deactivated,
            deactivation_reason: props.member.deactivation_reason,
        });
        messagesLimit.value = null;
        emit('updated', (data?.data ?? data) as ChatterRow);
        toast.success(t('shared.member_sheet.toast_limit_removed', { name: props.member.name }));
    } catch {
        toast.error(t('shared.member_sheet.toast_limit_remove_failed'));
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
                                {{ t('shared.member_sheet.status_pending') }}
                            </span>
                            <span
                                v-else
                                class="inline-flex items-center rounded-full border border-green-200 bg-green-50 px-2 py-0.5 text-xs font-medium text-green-900 dark:border-green-900/50 dark:bg-green-950/30 dark:text-green-100"
                            >
                                {{ t('shared.member_sheet.status_active') }}
                            </span>
                            <span class="text-muted-foreground truncate">{{ member.email || t('shared.member_sheet.no_email') }}</span>
                        </div>
                    </div>
                </div>
                <SheetDescription class="sr-only">{{ t('shared.member_sheet.sr_description') }}</SheetDescription>
                <div class="space-y-1.5">
                    <div class="flex items-center gap-1.5 text-xs">
                        <span class="text-muted-foreground">{{ t('shared.member_sheet.id_label') }}</span>
                        <code class="min-w-0 flex-1 truncate font-mono" :title="member.external_id">{{ member.external_id }}</code>
                        <Button variant="ghost" size="sm" class="h-6 shrink-0 px-1.5" :title="t('shared.member_sheet.copy_id_title')" @click="copyId">
                            <Check v-if="idCopied" class="size-3.5 text-green-600" />
                            <Copy v-else class="size-3.5" />
                        </Button>
                    </div>
                    <p class="text-muted-foreground text-xs">{{ t('shared.member_sheet.joined', { date: formatDate(member.created_at) }) }}</p>
                </div>
            </SheetHeader>

            <Tabs default-value="limits" class="px-6 py-6">
                <TabsList class="grid w-full grid-cols-4">
                    <TabsTrigger value="limits"><Gauge class="me-1.5 size-3.5" />{{ t('shared.member_sheet.tab_limits') }}</TabsTrigger>
                    <TabsTrigger value="agents"><Bot class="me-1.5 size-3.5" />{{ t('shared.member_sheet.tab_agents') }}</TabsTrigger>
                    <TabsTrigger value="status"><Power class="me-1.5 size-3.5" />{{ t('shared.member_sheet.tab_status') }}</TabsTrigger>
                    <TabsTrigger value="actions"><Wrench class="me-1.5 size-3.5" />{{ t('shared.member_sheet.tab_actions') }}</TabsTrigger>
                </TabsList>

                <!-- LIMITS — monthly message cap -->
                <TabsContent value="limits" class="mt-6 space-y-6">
                    <div class="space-y-3">
                        <div>
                            <h3 class="text-sm font-semibold">{{ t('shared.member_sheet.monthly_limit_title') }}</h3>
                            <p class="text-muted-foreground mt-1 text-xs">
                                {{ t('shared.member_sheet.monthly_limit_description') }}
                            </p>
                        </div>
                        <div class="flex items-center gap-2">
                            <div class="relative w-40 shrink-0">
                                <Gauge class="text-muted-foreground pointer-events-none absolute start-3 top-1/2 size-4 -translate-y-1/2" />
                                <Input
                                    v-model="messagesLimit"
                                    type="number"
                                    min="1"
                                    :placeholder="t('shared.member_sheet.no_limit_placeholder')"
                                    class="ps-9"
                                />
                            </div>
                            <Button :disabled="isSaving" @click="save">
                                <LoaderCircle v-if="isSaving" class="me-2 size-4 animate-spin" />
                                <Check v-else class="me-2 size-4" />
                                {{ t('common.save') }}
                            </Button>
                            <Button
                                v-if="member.messages_limit !== null"
                                variant="ghost"
                                size="sm"
                                class="text-destructive hover:text-destructive shrink-0"
                                :disabled="isSaving"
                                @click="removeLimit"
                            >
                                <X class="me-1 h-3.5 w-3.5" />
                                {{ t('shared.member_sheet.remove_limit') }}
                            </Button>
                        </div>
                        <p v-if="member.messages_limit !== null" class="text-muted-foreground text-xs">
                            {{ t('shared.member_sheet.usage_this_month', { used: member.messages_used, limit: member.messages_limit }) }}
                        </p>
                    </div>
                </TabsContent>

                <!-- AGENTS — grant / revoke access -->
                <TabsContent value="agents" class="mt-6 space-y-3">
                    <p class="text-muted-foreground text-xs">{{ t('shared.member_sheet.agents_toggle_description') }}</p>

                    <div v-if="agents.loading.value" class="text-muted-foreground flex items-center gap-2 py-6 text-sm">
                        <LoaderCircle class="size-4 animate-spin" />
                        {{ t('shared.member_sheet.loading_agents') }}
                    </div>

                    <div
                        v-else-if="agents.all.value.length === 0"
                        class="text-muted-foreground rounded-md border border-dashed py-6 text-center text-sm"
                    >
                        <Users class="text-muted-foreground/50 mx-auto mb-2 h-6 w-6" />
                        {{ t('shared.member_sheet.no_agents_in_workspace') }}
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
                            <h3 class="text-sm font-semibold">{{ t('shared.member_sheet.deactivate_title') }}</h3>
                            <p class="text-muted-foreground mt-1 text-xs">{{ t('shared.member_sheet.deactivate_description') }}</p>
                        </div>
                        <Switch :model-value="deactivated" @update:model-value="(v) => (deactivated = v)" />
                    </div>
                    <Textarea
                        v-if="deactivated"
                        v-model="deactivationReason"
                        :placeholder="t('shared.member_sheet.deactivation_reason_placeholder')"
                        :rows="2"
                    />
                    <Button :disabled="isSaving" @click="save">
                        <LoaderCircle v-if="isSaving" class="me-2 h-4 w-4 animate-spin" />
                        {{ t('shared.member_sheet.save_changes') }}
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
                        <KeyRound class="me-2 h-4 w-4" />
                        {{ t('shared.member_sheet.send_password_reset') }}
                    </Button>
                    <Button
                        variant="outline"
                        class="text-destructive hover:text-destructive hover:bg-destructive/10 w-full justify-start"
                        @click="emit('delete', member)"
                    >
                        <Trash2 class="me-2 h-4 w-4" />
                        {{ t('shared.member_sheet.remove_member') }}
                    </Button>
                </TabsContent>
            </Tabs>
        </SheetContent>
    </Sheet>
</template>
