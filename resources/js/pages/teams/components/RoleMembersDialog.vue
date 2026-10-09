<script setup lang="ts">
// Lists the team members who hold one role. Removing someone closes this
// dialog and hands the member to the page's "remove from team" confirmation,
// so two modals are never stacked.
import { Trash2 } from 'lucide-vue-next';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { getInitials } from '@/composables/useInitials';

type TeamMemberData = Domain.Membership.Team.Data.Resources.Members.TeamMemberData;
type TeamRoleData = Domain.Membership.Team.Data.Resources.Roles.TeamRoleData;

const open = defineModel<boolean>('open', { required: true });

const props = defineProps<{
    role: TeamRoleData | null;
    members: TeamMemberData[];
}>();

const emit = defineEmits<{
    remove: [member: TeamMemberData];
}>();

const { t } = useI18n();

const roleMembers = computed(() => (props.role ? props.members.filter((m) => m.role === props.role?.code) : []));

const removeMember = (member: TeamMemberData) => {
    emit('remove', member);
    open.value = false;
};
</script>

<template>
    <!-- Role Members Dialog -->
    <Dialog v-model:open="open">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle v-if="role">{{ t('teams.show.role_members.title', { name: role.name }) }}</DialogTitle>
                <DialogDescription>{{ t('teams.show.role_members.description') }}</DialogDescription>
            </DialogHeader>
            <div v-if="role" class="py-4">
                <div v-if="roleMembers.length === 0" class="text-muted-foreground py-8 text-center">
                    <p>{{ t('teams.show.role_members.empty') }}</p>
                </div>
                <div v-else class="space-y-2">
                    <div
                        v-for="member in roleMembers"
                        :key="member.id"
                        class="hover:bg-accent flex items-center justify-between rounded-lg border p-3"
                    >
                        <div class="flex items-center gap-3">
                            <Avatar class="size-8">
                                <AvatarFallback>{{ getInitials(member.name) }}</AvatarFallback>
                            </Avatar>
                            <div>
                                <div class="text-sm font-medium">{{ member.name }}</div>
                                <div class="text-muted-foreground text-xs">{{ member.email }}</div>
                            </div>
                        </div>
                        <Button
                            v-if="!member.is_owner"
                            variant="ghost"
                            size="sm"
                            class="text-destructive hover:text-destructive"
                            :aria-label="t('teams.show.members.remove')"
                            @click="removeMember(member)"
                        >
                            <Trash2 class="size-4" />
                        </Button>
                    </div>
                </div>
            </div>
            <DialogFooter>
                <Button type="button" variant="outline" @click="open = false">{{ t('teams.show.role_members.close') }}</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
