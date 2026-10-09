<script setup lang="ts">
// The role form shared by "Create role" and "Edit role": name, agent access,
// the permission matrix and the Cancel/submit row. The page owns the Inertia
// form and decides where it is sent; this component only edits its fields
// (bound with v-model) and emits `submit`.
import { Link } from '@inertiajs/vue3';
import { Loader2, Tag } from 'lucide-vue-next';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import RoleAgentAccess from './RoleAgentAccess.vue';
import RolePermissionsCard from './RolePermissionsCard.vue';

type TeamData = Domain.Membership.Team.Data.Resources.Team.TeamData;
type TeamAgentData = Domain.Membership.Team.Data.Resources.Team.TeamAgentData;
type PermissionMetaData = Domain.Membership.Team.Data.Resources.Permissions.PermissionMetaData;
type PermissionGroupData = Domain.Membership.Team.Data.Resources.Permissions.PermissionGroupData;
type PermissionScopeData = Domain.Membership.Team.Data.Resources.Permissions.PermissionScopeData;

const name = defineModel<string>('name', { required: true });
const allAgents = defineModel<boolean>('allAgents', { required: true });
const agentIds = defineModel<string[]>('agentIds', { required: true });
const permissions = defineModel<string[]>('permissions', { required: true });

defineProps<{
    team: TeamData;
    agents: TeamAgentData[];
    permissionsConfig: Record<string, PermissionMetaData>;
    permissionGroups: Record<string, PermissionGroupData>;
    permissionScopes: Record<string, PermissionScopeData>;
    errors: Partial<Record<'name' | 'permissions' | 'all_agents' | 'agent_ids', string>>;
    processing: boolean;
    submitLabel: string;
    autofocusName?: boolean;
}>();

const emit = defineEmits<{ submit: [] }>();

const { t } = useI18n();

// Validation: Ensure either all_agents is true OR at least one agent is selected
const isAgentSelectionValid = computed(() => allAgents.value || agentIds.value.length > 0);
</script>

<template>
    <form @submit.prevent="emit('submit')" class="space-y-6">
        <!-- Role Details + Agent Access combined -->
        <Card>
            <CardContent class="space-y-8 pt-6">
                <section class="space-y-3">
                    <div class="flex items-center gap-2">
                        <Tag class="text-muted-foreground size-4" />
                        <h3 class="text-sm font-semibold tracking-tight">{{ t('teams.roles.details') }}</h3>
                    </div>
                    <div class="space-y-2 ps-6">
                        <Label for="role-name">{{ t('teams.roles.role_name_label') }}</Label>
                        <Input
                            id="role-name"
                            v-model="name"
                            :placeholder="t('teams.roles.role_name_placeholder')"
                            required
                            :autofocus="autofocusName"
                        />
                        <InputError :message="errors.name" />
                    </div>
                </section>

                <RoleAgentAccess v-model:all-agents="allAgents" v-model:agent-ids="agentIds" :agents="agents" :error="errors.agent_ids" />
            </CardContent>
        </Card>

        <RolePermissionsCard
            v-model="permissions"
            :permissions-config="permissionsConfig"
            :permission-groups="permissionGroups"
            :permission-scopes="permissionScopes"
            :error="errors.permissions"
        />

        <!-- Actions -->
        <div class="flex items-center justify-end gap-3 pt-4">
            <Link :href="route('teams.show', team.external_id)">
                <Button type="button" variant="outline">{{ t('teams.common.cancel') }}</Button>
            </Link>
            <Button type="submit" :disabled="processing || !isAgentSelectionValid">
                <Loader2 v-if="processing" class="me-2 h-4 w-4 animate-spin" />
                {{ submitLabel }}
            </Button>
        </div>
    </form>
</template>
