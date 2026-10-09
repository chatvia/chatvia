<script setup lang="ts">
// "Roles & Permissions" card on the team page: the team's roles (the owner
// role is implicit and never listed) with a "Create role" link for owners.
import { Link } from '@inertiajs/vue3';
import { Plus, Shield } from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';

import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import TeamRoleItem from './TeamRoleItem.vue';

type TeamData = Domain.Membership.Team.Data.Resources.Team.TeamData;
type TeamMemberData = Domain.Membership.Team.Data.Resources.Members.TeamMemberData;
type TeamRoleData = Domain.Membership.Team.Data.Resources.Roles.TeamRoleData;
type TeamAgentData = Domain.Membership.Team.Data.Resources.Team.TeamAgentData;
type PermissionMetaData = Domain.Membership.Team.Data.Resources.Permissions.PermissionMetaData;
type PermissionGroupData = Domain.Membership.Team.Data.Resources.Permissions.PermissionGroupData;

const props = defineProps<{
    team: TeamData;
    roles: TeamRoleData[];
    members: TeamMemberData[];
    agents: TeamAgentData[];
    permissionsConfig: Record<string, PermissionMetaData>;
    permissionGroups: Record<string, PermissionGroupData>;
    isOwner: boolean;
    canManageMembers: boolean;
}>();

const emit = defineEmits<{
    addMember: [role: TeamRoleData];
    viewMembers: [role: TeamRoleData];
    deleteRole: [role: TeamRoleData];
}>();

const { t } = useI18n();

const memberCount = (roleCode: string): number => props.members.filter((m) => m.role === roleCode).length;
</script>

<template>
    <!-- Roles & Permissions -->
    <Card>
        <CardHeader>
            <div class="flex items-center justify-between">
                <div>
                    <CardTitle class="flex items-center gap-2">
                        <Shield class="size-5" />
                        {{ t('teams.show.roles.title') }}
                    </CardTitle>
                    <CardDescription>{{ t('teams.show.roles.description') }}</CardDescription>
                </div>
                <Link v-if="isOwner" :href="route('teams.roles.create', team.external_id)">
                    <Button class="gap-2">
                        <Plus class="size-4" />
                        {{ t('teams.common.create_role') }}
                    </Button>
                </Link>
            </div>
        </CardHeader>
        <CardContent>
            <div v-if="roles.length === 0" class="text-muted-foreground py-8 text-center">
                <p>{{ t('teams.show.roles.empty') }}</p>
            </div>
            <div v-else class="space-y-4">
                <TeamRoleItem
                    v-for="role in roles"
                    :key="role.id"
                    :team="team"
                    :role="role"
                    :member-count="memberCount(role.code)"
                    :agents="agents"
                    :permissions-config="permissionsConfig"
                    :permission-groups="permissionGroups"
                    :is-owner="isOwner"
                    :can-manage-members="canManageMembers"
                    @add-member="emit('addMember', $event)"
                    @view-members="emit('viewMembers', $event)"
                    @delete="emit('deleteRole', $event)"
                />
            </div>
        </CardContent>
    </Card>
</template>
