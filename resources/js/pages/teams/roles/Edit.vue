<script setup lang="ts">
import { Head, useForm, Link } from '@inertiajs/vue3';
import { computed } from 'vue';

import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { ScrollArea } from '@/components/ui/scroll-area';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Loader2, Bot, ChevronDown, Check, Info } from 'lucide-vue-next';

// Use generated TypeScript types from DTOs
type TeamData = App.Data.Resources.Team.TeamData;
type TeamAgentData = App.Data.Resources.Team.TeamAgentData;
type TeamRoleData = App.Data.Resources.Team.TeamRoleData;
type PermissionMetaData = App.Data.Resources.Team.PermissionMetaData;
type PermissionGroupData = App.Data.Resources.Team.PermissionGroupData;

interface Props {
    team: TeamData;
    role: TeamRoleData;
    agents: TeamAgentData[];
    permissionsConfig: Record<string, PermissionMetaData>;
    permissionGroups: Record<string, PermissionGroupData>;
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Teams',
        href: '/my/teams',
    },
    {
        title: props.team.name,
        href: `/my/teams/${props.team.id}`,
    },
    {
        title: `Edit: ${props.role.name}`,
        href: `/my/teams/${props.team.id}/roles/${props.role.id}/edit`,
    },
];

const form = useForm({
    name: props.role.name,
    permissions: [...props.role.permissions],
    all_agents: props.role.all_agents,
    agent_ids: [...(props.role.agent_ids || [])],
});

const submit = () => {
    form.put(route('teams.update-role', { team: props.team.id, roleId: props.role.id }));
};

// Get all required permissions recursively
const getRequiredPermissions = (permission: string): string[] => {
    const permData = props.permissionsConfig[permission];
    if (!permData?.requires?.length) return [];

    const required: string[] = [];
    for (const req of permData.requires) {
        required.push(req);
        // Recursively get requirements of requirements
        required.push(...getRequiredPermissions(req));
    }
    return [...new Set(required)]; // Remove duplicates
};

// Get all permissions that depend on a given permission
const getDependentPermissions = (permission: string): string[] => {
    const dependents: string[] = [];
    for (const [code, data] of Object.entries(props.permissionsConfig)) {
        if (data.requires?.includes(permission)) {
            dependents.push(code);
            // Recursively get permissions that depend on this one
            dependents.push(...getDependentPermissions(code));
        }
    }
    return [...new Set(dependents)];
};

// Permission helpers
const togglePermission = (permission: string, checked: boolean | "indeterminate") => {
    const isChecked = checked === true;

    if (isChecked) {
        // Add the permission and all its requirements
        const toAdd = [permission, ...getRequiredPermissions(permission)];
        const newPerms = toAdd.filter(p => !form.permissions.includes(p));
        form.permissions = [...form.permissions, ...newPerms];
    } else {
        // Remove the permission and all permissions that depend on it
        const toRemove = [permission, ...getDependentPermissions(permission)];
        form.permissions = form.permissions.filter(p => !toRemove.includes(p));
    }
};

const toggleGroupPermissions = (groupKey: string, checked: boolean | "indeterminate") => {
    const isChecked = checked === true;
    const groupPerms = props.permissionGroups[groupKey]?.permissions || [];

    if (isChecked) {
        // Add all permissions in group plus their requirements
        const toAdd: string[] = [];
        for (const perm of groupPerms) {
            toAdd.push(perm, ...getRequiredPermissions(perm));
        }
        const newPerms = [...new Set(toAdd)].filter(p => !form.permissions.includes(p));
        form.permissions = [...form.permissions, ...newPerms];
    } else {
        // Remove all group permissions and their dependents
        const toRemove: string[] = [];
        for (const perm of groupPerms) {
            toRemove.push(perm, ...getDependentPermissions(perm));
        }
        form.permissions = form.permissions.filter(p => !toRemove.includes(p));
    }
};

const getPermissionLabel = (permission: string): string => {
    const permData = props.permissionsConfig[permission];
    return permData?.label || permission;
};

const getPermissionDescription = (permission: string): string => {
    const permData = props.permissionsConfig[permission];
    return permData?.description || '';
};

const getPermissionRequires = (permission: string): string[] => {
    const permData = props.permissionsConfig[permission];
    return permData?.requires || [];
};

// Agent selection helpers
const selectedAgentNames = computed(() => {
    if (form.all_agents) return 'All Agents';
    if (form.agent_ids.length === 0) return 'Select agents...';
    const names = props.agents
        .filter(a => form.agent_ids.includes(a.external_id))
        .map(a => a.name);
    if (names.length <= 2) return names.join(', ');
    return `${names.slice(0, 2).join(', ')} +${names.length - 2} more`;
});

const toggleAgentSelection = (agentId: string) => {
    if (form.agent_ids.includes(agentId)) {
        form.agent_ids = form.agent_ids.filter(id => id !== agentId);
    } else {
        form.agent_ids = [...form.agent_ids, agentId];
    }
};
</script>

<template>
    <Head :title="`Edit Role: ${role.name}`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="h-full overflow-auto">
            <div class="container mx-auto max-w-3xl py-8 px-4">
                <div class="mb-6">
                    <h1 class="text-2xl font-bold tracking-tight">Edit Role</h1>
                    <p class="text-muted-foreground">
                        Update the <span class="font-medium">{{ role.name }}</span> role for <span class="font-medium">{{ team.name }}</span>
                    </p>
                </div>

                <form @submit.prevent="submit" class="space-y-6">
                    <!-- Role Name -->
                    <Card>
                        <CardHeader>
                            <CardTitle>Role Details</CardTitle>
                            <CardDescription>
                                Update the role name.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div class="space-y-2">
                                <Label for="role-name">Role Name</Label>
                                <Input
                                    id="role-name"
                                    v-model="form.name"
                                    placeholder="e.g., Support Agent, Content Editor"
                                    required
                                />
                                <InputError :message="form.errors.name" />
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Agent Access -->
                    <Card>
                        <CardHeader>
                            <CardTitle>Agent Access</CardTitle>
                            <CardDescription>
                                Choose which agents this role can access. This determines which AI agents users with this role can view and interact with.
                            </CardDescription>
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <div class="flex items-center gap-3">
                                <Checkbox
                                    id="all-agents"
                                    :model-value="form.all_agents"
                                    @update:model-value="(checked) => {
                                        form.all_agents = checked === true;
                                        if (checked) form.agent_ids = [];
                                    }"
                                />
                                <Label for="all-agents" class="cursor-pointer">
                                    <span class="font-medium">All Agents</span>
                                    <span class="text-muted-foreground ml-2 text-sm">
                                        Access to all current and future agents
                                    </span>
                                </Label>
                            </div>

                            <div v-if="!form.all_agents" class="pt-2">
                                <Label class="text-sm text-muted-foreground mb-2 block">Select specific agents:</Label>
                                <Popover>
                                    <PopoverTrigger as-child>
                                        <Button variant="outline" class="w-full justify-between">
                                            <span class="flex items-center gap-2 truncate">
                                                <Bot class="h-4 w-4 shrink-0" />
                                                {{ selectedAgentNames }}
                                            </span>
                                            <ChevronDown class="h-4 w-4 shrink-0 opacity-50" />
                                        </Button>
                                    </PopoverTrigger>
                                    <PopoverContent class="w-[300px] p-0" align="start">
                                        <ScrollArea class="h-[200px]">
                                            <div class="p-2 space-y-1">
                                                <div
                                                    v-for="agent in agents"
                                                    :key="agent.external_id"
                                                    @click="toggleAgentSelection(agent.external_id)"
                                                    class="flex items-center gap-2 px-2 py-1.5 rounded-md cursor-pointer hover:bg-accent"
                                                >
                                                    <div class="w-4 h-4 border rounded flex items-center justify-center"
                                                        :class="form.agent_ids.includes(agent.external_id) ? 'bg-primary border-primary' : 'border-input'"
                                                    >
                                                        <Check v-if="form.agent_ids.includes(agent.external_id)" class="h-3 w-3 text-primary-foreground" />
                                                    </div>
                                                    <span class="text-sm">{{ agent.name }}</span>
                                                </div>
                                                <div v-if="agents.length === 0" class="px-2 py-4 text-center text-sm text-muted-foreground">
                                                    No agents available
                                                </div>
                                            </div>
                                        </ScrollArea>
                                    </PopoverContent>
                                </Popover>
                                <InputError :message="form.errors.agent_ids" class="mt-2" />
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Permissions -->
                    <Card>
                        <CardHeader>
                            <CardTitle>Permissions</CardTitle>
                            <CardDescription>
                                Choose what actions users with this role can perform on the selected agents.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <TooltipProvider>
                                <div class="columns-1 sm:columns-2 gap-4 space-y-4">
                                    <div
                                        v-for="(groupData, groupKey) in permissionGroups"
                                        :key="groupKey"
                                        class="border rounded-lg p-4 space-y-3 break-inside-avoid"
                                    >
                                        <div class="flex items-center gap-2">
                                            <Checkbox
                                                :id="`group-${groupKey}`"
                                                :model-value="groupData.permissions.every(p => form.permissions.includes(p))"
                                                @update:model-value="(checked) => toggleGroupPermissions(groupKey.toString(), checked)"
                                            />
                                            <Label :for="`group-${groupKey}`" class="font-semibold cursor-pointer">
                                                {{ groupData.label }}
                                            </Label>
                                        </div>
                                        <p v-if="groupData.description" class="text-sm text-muted-foreground ml-6">
                                            {{ groupData.description }}
                                        </p>
                                        <div class="space-y-2 ml-6">
                                            <div
                                                v-for="permission in groupData.permissions"
                                                :key="permission"
                                                class="flex items-center gap-2"
                                            >
                                                <Checkbox
                                                    :id="`perm-${permission}`"
                                                    :model-value="form.permissions.includes(permission)"
                                                    @update:model-value="(checked) => togglePermission(permission, checked)"
                                                />
                                                <Label
                                                    :for="`perm-${permission}`"
                                                    class="text-sm font-normal cursor-pointer flex items-center gap-1.5"
                                                >
                                                    {{ getPermissionLabel(permission) }}
                                                    <Tooltip v-if="getPermissionDescription(permission)">
                                                        <TooltipTrigger as-child>
                                                            <Info class="h-3.5 w-3.5 text-muted-foreground hover:text-foreground cursor-help" />
                                                        </TooltipTrigger>
                                                        <TooltipContent side="top" class="max-w-xs">
                                                            <p>{{ getPermissionDescription(permission) }}</p>
                                                            <p v-if="getPermissionRequires(permission).length > 0" class="mt-1 text-xs text-muted-foreground">
                                                                Requires: {{ getPermissionRequires(permission).map(r => getPermissionLabel(r)).join(', ') }}
                                                            </p>
                                                        </TooltipContent>
                                                    </Tooltip>
                                                </Label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </TooltipProvider>
                            <InputError :message="form.errors.permissions" class="mt-4" />
                        </CardContent>
                    </Card>

                    <!-- Actions -->
                    <div class="flex items-center justify-end gap-3 pt-4">
                        <Link :href="route('teams.show', team.id)">
                            <Button type="button" variant="outline">
                                Cancel
                            </Button>
                        </Link>
                        <Button type="submit" :disabled="form.processing || form.permissions.length === 0">
                            <Loader2 v-if="form.processing" class="mr-2 h-4 w-4 animate-spin" />
                            Save Changes
                        </Button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
