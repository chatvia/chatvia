<script setup lang="ts">
// Invite a chatter from the workspace page and grant one or more protected
// agents in the same step (a chatter without any agent could not chat with
// anything, so at least one is required). After a successful send the dialog
// switches to the sign-up link so the owner can also share it by hand.
import InvitationResult from '@/dashboard/components/chatters/InvitationResult.vue';
import { flashedInvitation, type ChatterInvitation, type InvitableAgent } from '@/dashboard/components/chatters/types';
import { Button } from '@/shared/ui/button';
import { Checkbox } from '@/shared/ui/checkbox';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/shared/ui/dialog';
import { Input } from '@/shared/ui/input';
import { Label } from '@/shared/ui/label';
import { useDateFormat } from '@/dashboard/composables/useDateFormat';
import { invite as inviteChatter } from '@/routes/people/chatters';
import { useForm } from '@inertiajs/vue3';
import { LoaderCircle, Mail, Send, UserPlus, X } from 'lucide-vue-next';
import { ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { toast } from 'vue-sonner';

const props = defineProps<{
    /** Protected agents the new chatter can be given access to. */
    agents: InvitableAgent[];
}>();

const emit = defineEmits<{
    /** An invitation was sent, so a pending chatter now exists. */
    invited: [];
}>();

const open = defineModel<boolean>('open', { required: true });

const { t } = useI18n();
const { formatDate } = useDateFormat();

const inviteForm = useForm<{ email: string; agents: string[] }>({ email: '', agents: [] });
const lastInvitation = ref<ChatterInvitation | null>(null);

const resetInvite = () => {
    inviteForm.reset();
    inviteForm.clearErrors();
    lastInvitation.value = null;
};

// Every time the dialog opens it starts from an empty form.
watch(open, (isOpen) => {
    if (isOpen) resetInvite();
});

const toggleInviteAgent = (externalId: string, checked: boolean) => {
    inviteForm.agents = checked ? [...inviteForm.agents, externalId] : inviteForm.agents.filter((id) => id !== externalId);
};

const submitInvite = () => {
    inviteForm.post(inviteChatter.url(), {
        preserveScroll: true,
        onSuccess: (page) => {
            const invitation = flashedInvitation(page);
            if (invitation) {
                lastInvitation.value = invitation;
                toast.success(t('workspace.members.toast.invite_sent_to', { email: invitation.email }));
            } else {
                toast.success(t('workspace.members.toast.invite_sent'));
                open.value = false;
            }
            inviteForm.reset();
            // The pending chatter now exists — refresh so it shows in the table.
            emit('invited');
        },
        onError: () => toast.error(t('workspace.members.toast.invite_failed')),
    });
};
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle class="flex items-center gap-2">
                    <UserPlus class="h-5 w-5" />
                    {{ t('workspace.members.invite_dialog.title') }}
                </DialogTitle>
                <DialogDescription>
                    {{ t('workspace.members.invite_dialog.description') }}
                </DialogDescription>
            </DialogHeader>

            <form v-if="!lastInvitation" class="space-y-4" @submit.prevent="submitInvite">
                <div class="space-y-2">
                    <Label for="invite-email">{{ t('workspace.members.invite_dialog.email') }}</Label>
                    <div class="relative">
                        <div class="text-muted-foreground pointer-events-none absolute inset-y-0 start-0 flex items-center ps-3">
                            <Mail class="h-4 w-4" />
                        </div>
                        <Input
                            id="invite-email"
                            v-model="inviteForm.email"
                            type="email"
                            required
                            autofocus
                            :placeholder="t('agents.chatters.email_placeholder')"
                            class="ps-9"
                            :class="{ 'border-red-500': inviteForm.errors.email }"
                        />
                    </div>
                    <p v-if="inviteForm.errors.email" class="text-xs text-red-500">{{ inviteForm.errors.email }}</p>
                </div>

                <div class="space-y-2">
                    <Label>{{ t('workspace.members.invite_dialog.agents') }}</Label>
                    <div class="max-h-48 space-y-1 overflow-y-auto rounded-md border p-1">
                        <label
                            v-for="agent in props.agents"
                            :key="agent.external_id"
                            class="hover:bg-muted flex cursor-pointer items-center gap-3 rounded-md px-2 py-2 text-sm"
                        >
                            <Checkbox
                                :model-value="inviteForm.agents.includes(agent.external_id)"
                                @update:model-value="(checked) => toggleInviteAgent(agent.external_id, checked === true)"
                            />
                            <span class="truncate">{{ agent.name }}</span>
                        </label>
                    </div>
                    <p v-if="inviteForm.errors.agents" class="text-xs text-red-500">{{ inviteForm.errors.agents }}</p>
                    <p v-else class="text-muted-foreground text-xs">{{ t('workspace.members.invite_dialog.agents_hint') }}</p>
                </div>

                <DialogFooter>
                    <Button type="button" variant="outline" :disabled="inviteForm.processing" @click="open = false">
                        <X class="me-2 h-4 w-4" />
                        {{ t('workspace.common.cancel') }}
                    </Button>
                    <Button type="submit" :disabled="inviteForm.processing || !inviteForm.email || inviteForm.agents.length === 0">
                        <LoaderCircle v-if="inviteForm.processing" class="me-2 h-4 w-4 animate-spin" />
                        <Send v-else class="me-2 h-4 w-4" />
                        {{ t('workspace.members.invite_dialog.send') }}
                    </Button>
                </DialogFooter>
            </form>

            <!-- After-send view: lets the owner copy the link manually -->
            <InvitationResult
                v-else
                :accept-url="lastInvitation.accept_url"
                :signup-link-label="t('workspace.members.invite_dialog.signup_link')"
                :expires-label="t('workspace.members.invite_dialog.expires', { date: formatDate(lastInvitation.expires_at) })"
                :invite-another-label="t('workspace.members.invite_dialog.invite_another')"
                :done-label="t('workspace.members.invite_dialog.done')"
                @another="resetInvite"
                @done="open = false"
            >
                <i18n-t keypath="workspace.members.invite_dialog.emailed_to" tag="span">
                    <template #email
                        ><span class="font-medium">{{ lastInvitation.email }}</span></template
                    >
                </i18n-t>
            </InvitationResult>
        </DialogContent>
    </Dialog>
</template>
