<script setup lang="ts">
// One role in the "Roles & Permissions" card: name, copyable role ID, the
// owner/manager actions (add member, view members, edit, delete) and, when
// expanded, which agents the role can reach and a read-only permission grid.
import { Link } from '@inertiajs/vue3';
import { useClipboard } from '@vueuse/core';
import { Bot, Check, CheckCircle, ChevronRight, Copy, Edit, Info, Trash2, UserCog, UserPlus, X } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';

import { Button } from '@/components/ui/button';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import { usePermissionCatalog } from '../composables/usePermissionCatalog';

type TeamData = Domain.Membership.Team.Data.Resources.Team.TeamData;
type TeamRoleData = Domain.Membership.Team.Data.Resources.Roles.TeamRoleData;
type TeamAgentData = Domain.Membership.Team.Data.Resources.Team.TeamAgentData;
type PermissionMetaData = Domain.Membership.Team.Data.Resources.Permissions.PermissionMetaData;
type PermissionGroupData = Domain.Membership.Team.Data.Resources.Permissions.PermissionGroupData;

const props = defineProps<{
    team: TeamData;
    role: TeamRoleData;
    memberCount: number;
    agents: TeamAgentData[];
    permissionsConfig: Record<string, PermissionMetaData>;
    permissionGroups: Record<string, PermissionGroupData>;
    isOwner: boolean;
    canManageMembers: boolean;
}>();

const emit = defineEmits<{
    addMember: [role: TeamRoleData];
    viewMembers: [role: TeamRoleData];
    delete: [role: TeamRoleData];
}>();

const { t } = useI18n();
const { copy } = useClipboard({ legacy: true });
const { label: permissionLabel, description: permissionDescription } = usePermissionCatalog(() => props.permissionsConfig);

const expanded = ref(false);

// Two-second "Copied!" flash after clicking the role-id chip.
const copied = ref(false);
const copyRoleExternalId = async () => {
    await copy(props.role.external_id);
    copied.value = true;
    setTimeout(() => (copied.value = false), 2000);
};

const assignedAgentNames = computed(() =>
    props.agents
        .filter((a) => props.role.agent_ids?.includes(a.external_id))
        .map((a) => a.name)
        .join(', '),
);

// A wildcard (`*`) grants everything; `group.*` grants the whole group.
const hasPermission = (permission: string): boolean => {
    const granted = props.role.permissions;
    if (granted.includes('*')) return true;
    const [group] = permission.split('.');
    if (granted.includes(`${group}.*`)) return true;
    return granted.includes(permission);
};
</script>

<template>
    <div class="rounded-lg border p-4">
        <div class="mb-3 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <Button
                    variant="ghost"
                    size="icon"
                    class="size-6"
                    :aria-label="t('teams.show.roles.toggle_permissions')"
                    @click="expanded = !expanded"
                >
                    <ChevronRight class="size-4 transition-transform" :class="expanded ? 'rotate-90' : ''" />
                </Button>
                <div class="flex flex-wrap items-center gap-1.5">
                    <span class="text-base font-semibold">{{ role.name }}</span>
                    <TooltipProvider :delay-duration="0">
                        <Tooltip>
                            <TooltipTrigger as-child>
                                <button
                                    type="button"
                                    class="bg-muted text-muted-foreground hover:bg-muted/80 hover:text-foreground inline-flex items-center gap-1 rounded-md px-1.5 py-0.5 font-mono text-[11px] transition-colors"
                                    @click="copyRoleExternalId"
                                >
                                    <span class="max-w-[140px] truncate">{{ role.external_id }}</span>
                                    <Check v-if="copied" class="size-3 text-green-600" />
                                    <Copy v-else class="size-3" />
                                </button>
                            </TooltipTrigger>
                            <TooltipContent>
                                <p>{{ copied ? t('teams.show.copied') : t('teams.show.roles.copy_role_id') }}</p>
                            </TooltipContent>
                        </Tooltip>
                    </TooltipProvider>
                </div>
            </div>
            <div class="flex items-center gap-1">
                <TooltipProvider v-if="canManageMembers" :delay-duration="0">
                    <Tooltip>
                        <TooltipTrigger as-child>
                            <Button
                                variant="ghost"
                                size="icon"
                                class="size-8"
                                :aria-label="t('teams.show.add_member')"
                                @click="emit('addMember', role)"
                            >
                                <UserPlus class="size-4" />
                            </Button>
                        </TooltipTrigger>
                        <TooltipContent>
                            <p>{{ t('teams.show.add_member') }}</p>
                        </TooltipContent>
                    </Tooltip>
                </TooltipProvider>
                <TooltipProvider v-if="canManageMembers" :delay-duration="0">
                    <Tooltip>
                        <TooltipTrigger as-child>
                            <Button
                                variant="ghost"
                                size="icon"
                                class="relative size-8"
                                :aria-label="t('teams.show.view_members', { count: memberCount })"
                                @click="emit('viewMembers', role)"
                            >
                                <UserCog class="size-4" />
                                <span
                                    v-if="memberCount > 0"
                                    class="bg-primary text-primary-foreground absolute -end-1 -top-1 flex size-4 items-center justify-center rounded-full text-[10px]"
                                >
                                    {{ memberCount }}
                                </span>
                            </Button>
                        </TooltipTrigger>
                        <TooltipContent>
                            <p>{{ t('teams.show.view_members', { count: memberCount }) }}</p>
                        </TooltipContent>
                    </Tooltip>
                </TooltipProvider>
                <TooltipProvider v-if="isOwner" :delay-duration="0">
                    <Tooltip>
                        <TooltipTrigger as-child>
                            <Button variant="ghost" size="icon" class="size-8" as-child :aria-label="t('teams.show.roles.edit_role')">
                                <Link :href="route('teams.roles.edit', { team: team.external_id, roleId: role.id })">
                                    <Edit class="size-4" />
                                </Link>
                            </Button>
                        </TooltipTrigger>
                        <TooltipContent>
                            <p>{{ t('teams.show.roles.edit_role') }}</p>
                        </TooltipContent>
                    </Tooltip>
                </TooltipProvider>
                <TooltipProvider v-if="isOwner && role.code !== 'admin'" :delay-duration="0">
                    <Tooltip>
                        <TooltipTrigger as-child>
                            <Button
                                variant="ghost"
                                size="icon"
                                class="text-muted-foreground hover:text-destructive size-8"
                                :aria-label="t('teams.show.roles.delete_role')"
                                @click="emit('delete', role)"
                            >
                                <Trash2 class="size-4" />
                            </Button>
                        </TooltipTrigger>
                        <TooltipContent>
                            <p>{{ t('teams.show.roles.delete_role') }}</p>
                        </TooltipContent>
                    </Tooltip>
                </TooltipProvider>
            </div>
        </div>

        <div v-if="expanded">
            <!-- Agent Access -->
            <div class="mb-3 flex items-center gap-2 border-b pb-3 text-sm">
                <Bot class="text-muted-foreground size-4" />
                <span class="font-medium">{{ t('teams.show.roles.agent_access_label') }}</span>
                <span v-if="role.all_agents" class="text-green-600">{{ t('teams.show.roles.all_agents') }}</span>
                <span v-else-if="role.agent_ids?.length === 0" class="text-muted-foreground">{{ t('teams.show.roles.no_agents_assigned') }}</span>
                <span v-else class="text-foreground">
                    {{ t('teams.show.roles.agent_count', { count: role.agent_ids?.length }, role.agent_ids?.length ?? 0) }}
                    <span class="text-muted-foreground"> ({{ assignedAgentNames }}) </span>
                </span>
            </div>

            <!-- Permissions Grid -->
            <TooltipProvider>
                <div class="grid grid-cols-2 gap-2 sm:grid-cols-3">
                    <template v-for="(groupData, groupKey) in permissionGroups" :key="groupKey">
                        <div v-for="permission in groupData.permissions" :key="permission" class="flex items-center gap-1.5 text-sm">
                            <CheckCircle v-if="hasPermission(permission)" class="size-4 shrink-0 text-green-500" />
                            <X v-else class="text-muted-foreground/50 size-4 shrink-0" />
                            <span :class="[hasPermission(permission) ? 'text-foreground' : 'text-muted-foreground/50']">
                                {{ permissionLabel(permission) }}
                            </span>
                            <Tooltip v-if="permissionDescription(permission)">
                                <TooltipTrigger as-child>
                                    <Info class="text-muted-foreground hover:text-foreground size-3.5 shrink-0 cursor-help" />
                                </TooltipTrigger>
                                <TooltipContent side="top" class="max-w-xs">
                                    <p>{{ permissionDescription(permission) }}</p>
                                </TooltipContent>
                            </Tooltip>
                        </div>
                    </template>
                </div>
            </TooltipProvider>
        </div>
    </div>
</template>
