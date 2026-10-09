<script setup lang="ts">
// "Add team member" dialog, opened for one role via `openForRole(role)`. A
// member can be picked from the workspace's existing teammates or invited by
// email; the request only carries the fields for the chosen way.
import { useForm } from '@inertiajs/vue3';
import { Loader2, Mail, Send, UserPlus, Users, X } from 'lucide-vue-next';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { toast } from 'vue-sonner';

import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { roleBadgeVariant } from '../composables/roleBadgeVariant';

type TeamData = Domain.Membership.Team.Data.Resources.Team.TeamData;
type TeamRoleData = Domain.Membership.Team.Data.Resources.Roles.TeamRoleData;
type ManagedUserData = Domain.Membership.Team.Data.Resources.Members.ManagedUserData;

const props = defineProps<{
    team: TeamData;
    roles: TeamRoleData[];
    managedUsers: ManagedUserData[];
}>();

const { t } = useI18n();

const isOpen = ref(false);
const inviteType = ref<'existing' | 'email'>('existing'); // default to existing managed users
const inviteForm = useForm({
    user_id: null as number | null,
    email: '',
    role: 'admin',
});

const roleName = (code: string): string => props.roles.find((r) => r.code === code)?.name || code;

const openForRole = (role: TeamRoleData) => {
    inviteForm.role = role.code;
    inviteType.value = props.managedUsers.length > 0 ? 'existing' : 'email';
    isOpen.value = true;
};

const inviteMember = () => {
    // Reshape the submitted payload depending on whether we're inviting an
    // existing managed user (by id) or a new one (by email). Inertia's
    // `transform()` is the supported way to mutate the data right before send.
    const wasEmailInvite = inviteType.value === 'email';
    const invitedEmail = inviteForm.email;

    inviteForm
        .transform((current) =>
            inviteType.value === 'existing' ? { user_id: current.user_id, role: current.role } : { email: current.email, role: current.role },
        )
        .post(route('teams.invite', props.team.external_id), {
            onSuccess: () => {
                isOpen.value = false;
                inviteForm.reset();
                inviteType.value = 'existing';

                toast.success(
                    wasEmailInvite ? t('teams.show.invite.toast_invite_sent', { email: invitedEmail }) : t('teams.show.invite.toast_member_added'),
                );
            },
            onError: () => toast.error(t('teams.show.invite.toast_failed')),
        });
};

defineExpose({ openForRole });
</script>

<template>
    <!-- Invite Member Dialog -->
    <Dialog v-model:open="isOpen">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>{{ t('teams.show.invite.title') }}</DialogTitle>
                <DialogDescription>{{ t('teams.show.invite.description') }}</DialogDescription>
            </DialogHeader>
            <form @submit.prevent="inviteMember">
                <div class="grid gap-4 py-4">
                    <!-- Toggle between existing users and email -->
                    <div v-if="managedUsers.length > 0" class="bg-muted flex items-center gap-2 rounded-lg p-2">
                        <Button
                            type="button"
                            :variant="inviteType === 'existing' ? 'default' : 'ghost'"
                            size="sm"
                            @click="inviteType = 'existing'"
                            class="flex-1"
                        >
                            <Users class="me-2 size-4" />
                            {{ t('teams.show.invite.select_existing') }}
                        </Button>
                        <Button
                            type="button"
                            :variant="inviteType === 'email' ? 'default' : 'ghost'"
                            size="sm"
                            @click="inviteType = 'email'"
                            class="flex-1"
                        >
                            <Mail class="me-2 size-4" />
                            {{ t('teams.show.invite.invite_by_email') }}
                        </Button>
                    </div>

                    <!-- Existing users dropdown -->
                    <div v-if="inviteType === 'existing' && managedUsers.length > 0" class="grid gap-2">
                        <Label>{{ t('teams.show.invite.select_user_label') }}</Label>
                        <Select v-model="inviteForm.user_id">
                            <SelectTrigger>
                                <SelectValue :placeholder="t('teams.show.invite.select_user_placeholder')" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="user in managedUsers" :key="user.id" :value="user.id">
                                    <div class="flex flex-col">
                                        <span>{{ user.name }}</span>
                                        <span class="text-muted-foreground text-xs">{{ user.email }}</span>
                                    </div>
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <InputError :message="inviteForm.errors.user_id" />
                    </div>

                    <!-- Email input -->
                    <div v-if="inviteType === 'email'" class="grid gap-2">
                        <Label for="invite-email">{{ t('teams.show.invite.email_label') }}</Label>
                        <div class="relative">
                            <div class="text-muted-foreground pointer-events-none absolute inset-y-0 start-0 flex items-center ps-3">
                                <Mail class="size-4" />
                            </div>
                            <Input
                                id="invite-email"
                                v-model="inviteForm.email"
                                type="email"
                                :placeholder="t('teams.show.invite.email_placeholder')"
                                :required="inviteType === 'email'"
                                class="ps-9"
                            />
                        </div>
                        <InputError :message="inviteForm.errors.email" />
                    </div>

                    <!-- Role display -->
                    <div class="grid gap-2">
                        <Label>{{ t('teams.show.role') }}</Label>
                        <Badge :variant="roleBadgeVariant(inviteForm.role)" class="w-fit">
                            {{ roleName(inviteForm.role) }}
                        </Badge>
                    </div>
                </div>
                <DialogFooter>
                    <Button type="button" variant="outline" @click="isOpen = false">
                        <X class="me-2 size-4" />
                        {{ t('teams.common.cancel') }}
                    </Button>
                    <Button type="submit" :disabled="inviteForm.processing || (inviteType === 'existing' && !inviteForm.user_id)">
                        <Loader2 v-if="inviteForm.processing" class="me-2 size-4 animate-spin" />
                        <component :is="inviteType === 'existing' ? UserPlus : Send" v-else class="me-2 size-4" />
                        {{ inviteType === 'existing' ? t('teams.show.add_member') : t('teams.show.invite.send_invitation') }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
