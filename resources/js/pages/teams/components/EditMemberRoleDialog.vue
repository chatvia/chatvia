<script setup lang="ts">
// "Change member role" dialog. Open it by setting `v-model:member`; it closes
// (and clears the member) after a successful update or on cancel.
import { useForm } from '@inertiajs/vue3';
import { Loader2 } from 'lucide-vue-next';
import { watch } from 'vue';
import { useI18n } from 'vue-i18n';

import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';

type TeamData = Domain.Membership.Team.Data.Resources.Team.TeamData;
type TeamMemberData = Domain.Membership.Team.Data.Resources.Members.TeamMemberData;
type TeamRoleData = Domain.Membership.Team.Data.Resources.Roles.TeamRoleData;

const member = defineModel<TeamMemberData | null>('member', { required: true });

const props = defineProps<{
    team: TeamData;
    // Roles a member can be given — the owner role is never assignable.
    roles: TeamRoleData[];
}>();

const { t } = useI18n();

const updateMemberForm = useForm({
    role: '',
});

// Start from the member's current role each time the dialog opens.
watch(member, (opened) => {
    if (opened) updateMemberForm.role = opened.role;
});

const updateMemberRole = () => {
    if (!member.value) return;

    updateMemberForm.put(route('teams.update-member', { team: props.team.external_id, member: member.value.id }), {
        onSuccess: () => {
            member.value = null;
            updateMemberForm.reset();
        },
    });
};
</script>

<template>
    <!-- Edit Member Role Dialog -->
    <Dialog :open="!!member" @update:open="(open) => !open && (member = null)">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>{{ t('teams.show.members.change_role_title') }}</DialogTitle>
                <DialogDescription>{{ t('teams.show.members.update_role_description', { name: member?.name }) }}</DialogDescription>
            </DialogHeader>
            <form @submit.prevent="updateMemberRole">
                <div class="grid gap-4 py-4">
                    <div class="grid gap-2">
                        <Label for="update-role">{{ t('teams.show.role') }}</Label>
                        <Select v-model="updateMemberForm.role">
                            <SelectTrigger>
                                <SelectValue :placeholder="t('teams.show.members.select_role_placeholder')" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="role in roles" :key="role.code" :value="role.code">
                                    {{ role.name }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <InputError :message="updateMemberForm.errors.role" />
                    </div>
                </div>
                <DialogFooter>
                    <Button type="button" variant="outline" @click="member = null">{{ t('teams.common.cancel') }}</Button>
                    <Button type="submit" :disabled="updateMemberForm.processing">
                        <Loader2 v-if="updateMemberForm.processing" class="me-2 size-4 animate-spin" />
                        {{ t('teams.show.members.update_role') }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
