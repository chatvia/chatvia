<script setup lang="ts">
// Invite a new chatter to this agent by email. After a successful send the
// dialog switches to the sign-up link so the owner can also share it by hand.
import InvitationResult from '@/dashboard/components/chatters/InvitationResult.vue';
import { flashedInvitation, type ChatterInvitation } from '@/dashboard/components/chatters/types';
import { Button } from '@/shared/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/shared/ui/dialog';
import { Input } from '@/shared/ui/input';
import { Label } from '@/shared/ui/label';
import { useDateFormat } from '@/dashboard/composables/useDateFormat';
import { store as storeAgentChatter } from '@/routes/agents/chatters';
import { useForm } from '@inertiajs/vue3';
import { LoaderCircle, Mail, Send, UserPlus, X } from 'lucide-vue-next';
import { ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { toast } from 'vue-sonner';

const props = defineProps<{
    agentId: string;
}>();

const emit = defineEmits<{
    /** An invitation was sent, so a pending row now exists. */
    invited: [];
}>();

const open = defineModel<boolean>('open', { required: true });

const { t } = useI18n();
const { formatDate } = useDateFormat();

const inviteForm = useForm({ email: '' });
const lastInvitation = ref<ChatterInvitation | null>(null);

// Every time the dialog opens it starts from an empty form.
watch(open, (isOpen) => {
    if (!isOpen) return;
    inviteForm.reset();
    inviteForm.clearErrors();
    lastInvitation.value = null;
});

const submitInvite = () => {
    inviteForm.post(storeAgentChatter.url(props.agentId), {
        preserveScroll: true,
        onSuccess: (page) => {
            const invitation = flashedInvitation(page);
            if (invitation) {
                lastInvitation.value = invitation;
                toast.success(t('agents.chatters.invitation_sent_to', { email: invitation.email }));
            } else {
                toast.success(t('agents.chatters.invitation_sent'));
                open.value = false;
            }
            inviteForm.reset();
            // Pending row now exists in the pivot — refresh so the owner
            // can see it without leaving the page.
            emit('invited');
        },
    });
};
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle class="flex items-center gap-2">
                    <UserPlus class="h-5 w-5" />
                    {{ t('agents.chatters.invite_dialog_title') }}
                </DialogTitle>
                <DialogDescription>
                    {{ t('agents.chatters.invite_dialog_description') }}
                </DialogDescription>
            </DialogHeader>

            <form v-if="!lastInvitation" @submit.prevent="submitInvite" class="space-y-4">
                <div class="space-y-2">
                    <Label for="invite-email">{{ t('agents.chatters.email_label') }}</Label>
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

                <DialogFooter>
                    <Button type="button" variant="outline" @click="open = false" :disabled="inviteForm.processing">
                        <X class="me-2 h-4 w-4" />
                        {{ t('common.cancel') }}
                    </Button>
                    <Button type="submit" :disabled="inviteForm.processing || !inviteForm.email">
                        <LoaderCircle v-if="inviteForm.processing" class="me-2 h-4 w-4 animate-spin" />
                        <Send v-else class="me-2 h-4 w-4" />
                        {{ t('agents.chatters.send_invite') }}
                    </Button>
                </DialogFooter>
            </form>

            <!-- After-send view: lets the owner copy the link manually -->
            <InvitationResult
                v-else
                :accept-url="lastInvitation.accept_url"
                :signup-link-label="t('agents.chatters.signup_link')"
                :expires-label="t('agents.chatters.expires', { date: formatDate(lastInvitation.expires_at) })"
                :invite-another-label="t('agents.chatters.invite_another')"
                :done-label="t('agents.chatters.done')"
                @another="lastInvitation = null"
                @done="open = false"
            >
                {{ t('agents.chatters.invitation_emailed', { email: lastInvitation.email }) }}
            </InvitationResult>
        </DialogContent>
    </Dialog>
</template>
