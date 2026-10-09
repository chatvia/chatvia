<script setup lang="ts">
// Permission matrix of the role form: permissions grouped by scope (agent /
// account) and group, with a tick-all box per group. Ticking or unticking
// also adjusts the permissions they depend on (see usePermissionDependencies).
import { Info } from 'lucide-vue-next';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

import InputError from '@/components/InputError.vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import { useTeamPermissionsI18n } from '@/composables/useTeamPermissionsI18n';
import { usePermissionCatalog } from '../../composables/usePermissionCatalog';
import { usePermissionDependencies } from '../../composables/usePermissionDependencies';

type PermissionMetaData = Domain.Membership.Team.Data.Resources.Permissions.PermissionMetaData;
type PermissionGroupData = Domain.Membership.Team.Data.Resources.Permissions.PermissionGroupData;
type PermissionScopeData = Domain.Membership.Team.Data.Resources.Permissions.PermissionScopeData;

const permissions = defineModel<string[]>({ required: true });

const props = defineProps<{
    permissionsConfig: Record<string, PermissionMetaData>;
    permissionGroups: Record<string, PermissionGroupData>;
    permissionScopes: Record<string, PermissionScopeData>;
    error?: string;
}>();

const { t } = useI18n();
const { scopeLabel, scopeDescription, groupLabel, groupDescription } = useTeamPermissionsI18n();
const {
    label: getPermissionLabel,
    description: getPermissionDescription,
    requires: getPermissionRequires,
} = usePermissionCatalog(() => props.permissionsConfig);
const { togglePermission, toggleGroupPermissions } = usePermissionDependencies(
    permissions,
    () => props.permissionsConfig,
    () => props.permissionGroups,
);

const scopes = computed(() =>
    Object.entries(props.permissionScopes).map(([scopeKey, scope]) => ({
        key: scopeKey,
        label: scopeLabel(scopeKey, scope.label),
        description: scopeDescription(scopeKey, scope.description),
        groups: Object.entries(props.permissionGroups)
            .filter(([, group]) => group.scope === scopeKey)
            .map(([groupKey, group]) => ({
                key: groupKey,
                ...group,
                label: groupLabel(groupKey, group.label),
                description: groupDescription(groupKey, group.description ?? ''),
            })),
    })),
);
</script>

<template>
    <Card>
        <CardHeader>
            <CardTitle>{{ t('teams.roles.permissions.title') }}</CardTitle>
            <CardDescription>{{ t('teams.roles.permissions.description') }}</CardDescription>
        </CardHeader>
        <CardContent>
            <TooltipProvider>
                <div class="space-y-10">
                    <section v-for="scope in scopes" :key="scope.key" v-show="scope.groups.length > 0" class="space-y-4">
                        <div class="border-primary/40 border-s-2 ps-4">
                            <h3 class="text-base font-semibold tracking-tight">{{ scope.label }}</h3>
                            <p class="text-muted-foreground mt-1 text-sm">{{ scope.description }}</p>
                        </div>
                        <div class="columns-1 gap-4 space-y-4 sm:columns-2">
                            <div v-for="group in scope.groups" :key="group.key" class="break-inside-avoid space-y-3 rounded-lg border p-4">
                                <div class="flex items-center gap-2">
                                    <Checkbox
                                        :id="`group-${group.key}`"
                                        :model-value="group.permissions.every((p) => permissions.includes(p))"
                                        @update:model-value="(checked) => toggleGroupPermissions(group.key, checked)"
                                    />
                                    <Label :for="`group-${group.key}`" class="cursor-pointer font-semibold">
                                        {{ group.label }}
                                    </Label>
                                </div>
                                <p v-if="group.description" class="text-muted-foreground ms-6 text-sm">
                                    {{ group.description }}
                                </p>
                                <div class="ms-6 space-y-2">
                                    <div v-for="permission in group.permissions" :key="permission" class="flex items-center gap-2">
                                        <Checkbox
                                            :id="`perm-${permission}`"
                                            :model-value="permissions.includes(permission)"
                                            @update:model-value="(checked) => togglePermission(permission, checked)"
                                        />
                                        <Label :for="`perm-${permission}`" class="flex cursor-pointer items-center gap-1.5 text-sm font-normal">
                                            {{ getPermissionLabel(permission) }}
                                            <Tooltip v-if="getPermissionDescription(permission)">
                                                <TooltipTrigger as-child>
                                                    <Info class="text-muted-foreground hover:text-foreground h-3.5 w-3.5 cursor-help" />
                                                </TooltipTrigger>
                                                <TooltipContent side="top" class="max-w-xs">
                                                    <p>{{ getPermissionDescription(permission) }}</p>
                                                    <p v-if="getPermissionRequires(permission).length > 0" class="text-muted-foreground mt-1 text-xs">
                                                        {{ t('teams.roles.permissions.requires') }}
                                                        {{
                                                            getPermissionRequires(permission)
                                                                .map((r) => getPermissionLabel(r))
                                                                .join(', ')
                                                        }}
                                                    </p>
                                                </TooltipContent>
                                            </Tooltip>
                                        </Label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>
                </div>
            </TooltipProvider>
            <InputError :message="error" class="mt-4" />
        </CardContent>
    </Card>
</template>
