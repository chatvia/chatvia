<script setup lang="ts">
// Side sheet for one chatter on the agent's Chatters tab: identity, access to
// this agent, the pending invitation (copy the link or send a fresh one) and a
// Grant / Revoke button. Full account management lives on the workspace page.
import type { AgentChatterRow } from '@/dashboard/api/chatters';
import ChatterSheetHeader from '@/dashboard/components/chatters/ChatterSheetHeader.vue';
import CopyableLinkField from '@/dashboard/components/chatters/CopyableLinkField.vue';
import type { ChatterSheetHeaderLabels } from '@/dashboard/components/chatters/types';
import { Button } from '@/shared/ui/button';
import { Label } from '@/shared/ui/label';
import { Sheet, SheetContent, SheetFooter } from '@/shared/ui/sheet';
import { useDateFormat } from '@/dashboard/composables/useDateFormat';
import { store as storeAgentChatter } from '@/routes/agents/chatters';
import { index as chattersIndex } from '@/routes/people/chatters';
import { Link, router } from '@inertiajs/vue3';
import { CircleAlert, Clock, LoaderCircle, PlusCircle, RefreshCw, Trash2 } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { toast } from 'vue-sonner';
import ChatterAccessBadge from './ChatterAccessBadge.vue';

const props = defineProps<{
    agentId: string;
    member: AgentChatterRow | null;
    grantingId: string | null;
}>();

const emit = defineEmits<{
    grant: [member: AgentChatterRow];
    revoke: [member: AgentChatterRow];
    /** A fresh invitation link was minted; the list should be reloaded to show it. */
    resent: [];
}>();

const open = defineModel<boolean>('open', { required: true });

const { t } = useI18n();
const { formatDate } = useDateFormat();

const headerLabels = computed<ChatterSheetHeaderLabels>(() => ({
    pending: t('agents.chatters.status.pending'),
    active: t('agents.chatters.status.active'),
    noEmail: t('agents.chatters.no_email'),
    description: t('agents.chatters.sheet_description'),
    id: t('agents.chatters.member_id'),
    copyId: t('agents.chatters.copy_member_id'),
    joined: t('agents.chatters.joined', { date: formatDate(props.member?.created_at) }),
}));

const isResending = ref(false);

// Re-invite an existing pending member: mints a fresh link + emails it. After
// the refresh the row carries the new `invitation_url`, so the sheet (driven by
// the parent's list) shows the copyable link without any manual patching.
const resendInvitation = () => {
    const member = props.member;
    if (!member?.email) return;
    isResending.value = true;
    router.post(
        storeAgentChatter.url(props.agentId),
        { email: member.email },
        {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                toast.success(t('agents.chatters.fresh_link', { name: member.name }));
                emit('resent');
            },
            onError: () => toast.error(t('agents.chatters.link_error')),
            onFinish: () => {
                isResending.value = false;
            },
        },
    );
};
</script>

<template>
    <Sheet v-model:open="open">
        <SheetContent v-if="member" class="w-full overflow-y-auto p-0 sm:max-w-md">
            <ChatterSheetHeader :chatter="member" :labels="headerLabels" />

            <div class="space-y-6 px-6 py-6">
                <!-- Access -->
                <div class="space-y-1.5">
                    <p class="text-muted-foreground text-xs font-medium uppercase">{{ t('agents.chatters.table.access') }}</p>
                    <div>
                        <ChatterAccessBadge :has-access="member.has_access" />
                    </div>
                </div>

                <!-- Pending invitation -->
                <div v-if="member.status === 'pending'" class="space-y-3 rounded-md border p-4">
                    <div class="flex items-start gap-2">
                        <Clock class="mt-0.5 h-4 w-4 shrink-0 text-amber-600 dark:text-amber-400" />
                        <div class="space-y-0.5">
                            <p class="text-sm font-medium">{{ t('agents.chatters.invitation_pending') }}</p>
                            <p class="text-muted-foreground text-xs">{{ t('agents.chatters.invitation_pending_hint') }}</p>
                        </div>
                    </div>

                    <div v-if="member.invitation_url" class="space-y-2">
                        <Label>{{ t('agents.chatters.signup_link') }}</Label>
                        <CopyableLinkField :url="member.invitation_url" />
                        <p v-if="member.invitation_expires_at" class="text-muted-foreground text-xs">
                            {{ t('agents.chatters.expires', { date: formatDate(member.invitation_expires_at) }) }}
                        </p>
                    </div>

                    <div v-else class="text-muted-foreground flex items-start gap-2 text-xs">
                        <CircleAlert class="mt-0.5 h-4 w-4 shrink-0" />
                        <p>{{ t('agents.chatters.link_expired') }}</p>
                    </div>

                    <Button type="button" variant="outline" size="sm" class="w-full" :disabled="isResending" @click="resendInvitation">
                        <LoaderCircle v-if="isResending" class="me-2 h-4 w-4 animate-spin" />
                        <RefreshCw v-else class="me-2 h-4 w-4" />
                        {{ member.invitation_url ? t('agents.chatters.resend_invitation') : t('agents.chatters.generate_link') }}
                    </Button>
                </div>

                <!-- Manage hint -->
                <i18n-t keypath="agents.chatters.manage_hint" tag="p" class="text-muted-foreground text-sm" scope="global">
                    <template #link>
                        <Link :href="chattersIndex.url()" class="text-foreground font-medium underline underline-offset-4 hover:no-underline">
                            {{ t('agents.chatters.members_page') }}
                        </Link>
                    </template>
                </i18n-t>
            </div>

            <SheetFooter class="border-t px-6 py-4">
                <Button v-if="member?.has_access" variant="outline" class="text-destructive hover:text-destructive" @click="emit('revoke', member)">
                    <Trash2 class="me-2 h-4 w-4" />
                    {{ t('agents.chatters.revoke_access') }}
                </Button>
                <Button v-else-if="member" variant="outline" :disabled="grantingId === member.external_id" @click="emit('grant', member)">
                    <LoaderCircle v-if="grantingId === member.external_id" class="me-2 h-4 w-4 animate-spin" />
                    <PlusCircle v-else class="me-2 h-4 w-4" />
                    {{ t('agents.chatters.grant_access') }}
                </Button>
            </SheetFooter>
        </SheetContent>
    </Sheet>
</template>
