<script setup lang="ts">
// Detail sheet for one chatter on the workspace People page, in four tabs:
// monthly message limit, which agents they can use, deactivation, and account
// actions (password reset, delete). Saves go straight to the server; the parent
// merges the returned row so the table stays in sync.
import { updateChatterAccess, type WorkspaceChatterRow } from '@/api/chatters';
import ChatterSheetHeader from '@/components/chatters/ChatterSheetHeader.vue';
import type { ChatterSheetHeaderLabels } from '@/components/chatters/types';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Sheet, SheetContent } from '@/components/ui/sheet';
import Switch from '@/components/ui/switch/Switch.vue';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Textarea } from '@/components/ui/textarea';
import { useAgentAttachments } from '@/composables/useAgentAttachments';
import { useDateFormat } from '@/composables/useDateFormat';
import { Bot, Check, Gauge, KeyRound, LoaderCircle, Power, Trash2, Users, Wrench, X } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { toast } from 'vue-sonner';

const { t } = useI18n();
const { formatDate } = useDateFormat();

interface Props {
    open: boolean;
    member: WorkspaceChatterRow | null;
}

const props = defineProps<Props>();
const emit = defineEmits<{
    'update:open': [value: boolean];
    reset: [member: WorkspaceChatterRow];
    delete: [member: WorkspaceChatterRow];
    'access-changed': [member: WorkspaceChatterRow, attachedCount: number];
    updated: [member: WorkspaceChatterRow];
}>();

const agents = useAgentAttachments('chatter');

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
        const updated = await updateChatterAccess(props.member.external_id, {
            messages_limit: limit,
            deactivated: deactivated.value,
            deactivation_reason: deactivationReason.value || null,
        });
        emit('updated', updated);
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
        const updated = await updateChatterAccess(props.member.external_id, {
            messages_limit: null,
            deactivated: props.member.is_deactivated,
            deactivation_reason: props.member.deactivation_reason,
        });
        messagesLimit.value = null;
        emit('updated', updated);
        toast.success(t('shared.member_sheet.toast_limit_removed', { name: props.member.name }));
    } catch {
        toast.error(t('shared.member_sheet.toast_limit_remove_failed'));
    } finally {
        isSaving.value = false;
    }
};

const headerLabels = computed<ChatterSheetHeaderLabels>(() => ({
    pending: t('shared.member_sheet.status_pending'),
    active: t('shared.member_sheet.status_active'),
    noEmail: t('shared.member_sheet.no_email'),
    description: t('shared.member_sheet.sr_description'),
    id: t('shared.member_sheet.id_label'),
    copyId: t('shared.member_sheet.copy_id_title'),
    joined: t('shared.member_sheet.joined', { date: formatDate(props.member?.created_at) }),
}));

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
</script>

<template>
    <Sheet :open="open" @update:open="(v) => emit('update:open', v)">
        <SheetContent v-if="member" class="w-full overflow-y-auto p-0 sm:max-w-md">
            <ChatterSheetHeader :chatter="member" :labels="headerLabels" />

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
