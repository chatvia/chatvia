<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { ScrollArea } from '@/components/ui/scroll-area';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Bot, Check, ChevronDown, Info, Loader2, Tag } from 'lucide-vue-next';

// Use generated TypeScript types from DTOs
type TeamData = App.Team.Data.Resources.Team.TeamData;
type TeamAgentData = App.Team.Data.Resources.Team.TeamAgentData;
type TeamRoleData = App.Team.Data.Resources.Roles.TeamRoleData;
type PermissionMetaData = App.Team.Data.Resources.Permissions.PermissionMetaData;
type PermissionGroupData = App.Team.Data.Resources.Permissions.PermissionGroupData;
type PermissionScopeData = App.Team.Data.Resources.Permissions.PermissionScopeData;

interface Props {
    team: TeamData;
    role: TeamRoleData;
    agents: TeamAgentData[];
    permissionsConfig: Record<string, PermissionMetaData>;
    permissionGroups: Record<string, PermissionGroupData>;
    permissionScopes: Record<string, PermissionScopeData>;
}

const props = defineProps<Props>();

const scopes = computed(() =>
    Object.entries(props.permissionScopes).map(([scopeKey, scope]) => ({
        key: scopeKey,
        label: scope.label,
        description: scope.description,
        groups: Object.entries(props.permissionGroups)
            .filter(([, group]) => group.scope === scopeKey)
            .map(([groupKey, group]) => ({ key: groupKey, ...group })),
    })),
);

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

// Validation: Ensure either all_agents is true OR at least one agent is selected
const isAgentSelectionValid = computed(() => {
    return form.all_agents || form.agent_ids.length > 0;
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
const togglePermission = (permission: string, checked: boolean | 'indeterminate') => {
    const isChecked = checked === true;

    if (isChecked) {
        // Add the permission and all its requirements
        const toAdd = [permission, ...getRequiredPermissions(permission)];
        const newPerms = toAdd.filter((p) => !form.permissions.includes(p));
        form.permissions = [...form.permissions, ...newPerms];
    } else {
        // Remove the permission and all permissions that depend on it
        const toRemove = [permission, ...getDependentPermissions(permission)];
        form.permissions = form.permissions.filter((p) => !toRemove.includes(p));
    }
};

const toggleGroupPermissions = (groupKey: string, checked: boolean | 'indeterminate') => {
    const isChecked = checked === true;
    const groupPerms = props.permissionGroups[groupKey]?.permissions || [];

    if (isChecked) {
        // Add all permissions in group plus their requirements
        const toAdd: string[] = [];
        for (const perm of groupPerms) {
            toAdd.push(perm, ...getRequiredPermissions(perm));
        }
        const newPerms = [...new Set(toAdd)].filter((p) => !form.permissions.includes(p));
        form.permissions = [...form.permissions, ...newPerms];
    } else {
        // Remove all group permissions and their dependents
        const toRemove: string[] = [];
        for (const perm of groupPerms) {
            toRemove.push(perm, ...getDependentPermissions(perm));
        }
        form.permissions = form.permissions.filter((p) => !toRemove.includes(p));
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
    const names = props.agents.filter((a) => form.agent_ids.includes(a.external_id)).map((a) => a.name);
    if (names.length <= 2) return names.join(', ');
    return `${names.slice(0, 2).join(', ')} +${names.length - 2} more`;
});

const toggleAgentSelection = (agentId: string) => {
    if (form.agent_ids.includes(agentId)) {
        form.agent_ids = form.agent_ids.filter((id) => id !== agentId);
    } else {
        form.agent_ids = [...form.agent_ids, agentId];
    }
};
</script>

<template>
    <Head :title="`Edit Role: ${role.name}`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="h-full overflow-auto">
            <div class="container mx-auto max-w-3xl px-4 py-8">
                <div class="mb-6">
                    <h1 class="text-2xl font-bold tracking-tight">Edit Role</h1>
                    <p class="text-muted-foreground">
                        Update the <span class="font-medium">{{ role.name }}</span> role for <span class="font-medium">{{ team.name }}</span>
                    </p>
                </div>

                <form @submit.prevent="submit" class="space-y-6">
                    <!-- Role Details + Agent Access combined -->
                    <Card>
                        <CardContent class="space-y-8 pt-6">
                            <section class="space-y-3">
                                <div class="flex items-center gap-2">
                                    <Tag class="text-muted-foreground size-4" />
                                    <h3 class="text-sm font-semibold tracking-tight">Role Details</h3>
                                </div>
                                <div class="space-y-2 pl-6">
                                    <Label for="role-name">Role Name</Label>
                                    <Input id="role-name" v-model="form.name" placeholder="e.g., Support Agent, Content Editor" required />
                                    <InputError :message="form.errors.name" />
                                </div>
                            </section>

                            <section class="space-y-3">
                                <div class="flex items-center gap-2">
                                    <Bot class="text-muted-foreground size-4" />
                                    <h3 class="text-sm font-semibold tracking-tight">Agent Access</h3>
                                </div>
                                <p class="text-muted-foreground pl-6 text-sm">Choose which agents this role can view and interact with.</p>
                                <div class="space-y-4 pl-6">
                                    <div class="flex items-center gap-3">
                                        <Checkbox
                                            id="all-agents"
                                            :model-value="form.all_agents"
                                            @update:model-value="
                                                (checked) => {
                                                    form.all_agents = checked === true;
                                                    if (checked) form.agent_ids = [];
                                                }
                                            "
                                        />
                                        <Label for="all-agents" class="cursor-pointer">
                                            <span class="font-medium">All Agents</span>
                                            <span class="text-muted-foreground ml-2 text-sm"> Access to all current and future agents </span>
                                        </Label>
                                    </div>

                                    <div v-if="!form.all_agents">
                                        <Label class="text-muted-foreground mb-2 block text-sm">Select specific agents:</Label>
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
                                                    <div class="space-y-1 p-2">
                                                        <div
                                                            v-for="agent in agents"
                                                            :key="agent.external_id"
                                                            @click="toggleAgentSelection(agent.external_id)"
                                                            class="hover:bg-accent flex cursor-pointer items-center gap-2 rounded-md px-2 py-1.5"
                                                        >
                                                            <div
                                                                class="flex h-4 w-4 items-center justify-center rounded border"
                                                                :class="
                                                                    form.agent_ids.includes(agent.external_id)
                                                                        ? 'bg-primary border-primary'
                                                                        : 'border-input'
                                                                "
                                                            >
                                                                <Check
                                                                    v-if="form.agent_ids.includes(agent.external_id)"
                                                                    class="text-primary-foreground h-3 w-3"
                                                                />
                                                            </div>
                                                            <span class="text-sm">{{ agent.name }}</span>
                                                        </div>
                                                        <div v-if="agents.length === 0" class="text-muted-foreground px-2 py-4 text-center text-sm">
                                                            No agents available
                                                        </div>
                                                    </div>
                                                </ScrollArea>
                                            </PopoverContent>
                                        </Popover>
                                        <InputError :message="form.errors.agent_ids" class="mt-2" />
                                    </div>
                                </div>
                            </section>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Permissions</CardTitle>
                            <CardDescription>Choose what actions users with this role can perform.</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <TooltipProvider>
                                <div class="space-y-10">
                                    <section v-for="scope in scopes" :key="scope.key" v-show="scope.groups.length > 0" class="space-y-4">
                                        <div class="border-primary/40 border-l-2 pl-4">
                                            <h3 class="text-base font-semibold tracking-tight">{{ scope.label }}</h3>
                                            <p class="text-muted-foreground mt-1 text-sm">{{ scope.description }}</p>
                                        </div>
                                        <div class="columns-1 gap-4 space-y-4 sm:columns-2">
                                            <div
                                                v-for="group in scope.groups"
                                                :key="group.key"
                                                class="break-inside-avoid space-y-3 rounded-lg border p-4"
                                            >
                                                <div class="flex items-center gap-2">
                                                    <Checkbox
                                                        :id="`group-${group.key}`"
                                                        :model-value="group.permissions.every((p) => form.permissions.includes(p))"
                                                        @update:model-value="(checked) => toggleGroupPermissions(group.key, checked)"
                                                    />
                                                    <Label :for="`group-${group.key}`" class="cursor-pointer font-semibold">
                                                        {{ group.label }}
                                                    </Label>
                                                </div>
                                                <p v-if="group.description" class="text-muted-foreground ml-6 text-sm">
                                                    {{ group.description }}
                                                </p>
                                                <div class="ml-6 space-y-2">
                                                    <div v-for="permission in group.permissions" :key="permission" class="flex items-center gap-2">
                                                        <Checkbox
                                                            :id="`perm-${permission}`"
                                                            :model-value="form.permissions.includes(permission)"
                                                            @update:model-value="(checked) => togglePermission(permission, checked)"
                                                        />
                                                        <Label
                                                            :for="`perm-${permission}`"
                                                            class="flex cursor-pointer items-center gap-1.5 text-sm font-normal"
                                                        >
                                                            {{ getPermissionLabel(permission) }}
                                                            <Tooltip v-if="getPermissionDescription(permission)">
                                                                <TooltipTrigger as-child>
                                                                    <Info
                                                                        class="text-muted-foreground hover:text-foreground h-3.5 w-3.5 cursor-help"
                                                                    />
                                                                </TooltipTrigger>
                                                                <TooltipContent side="top" class="max-w-xs">
                                                                    <p>{{ getPermissionDescription(permission) }}</p>
                                                                    <p
                                                                        v-if="getPermissionRequires(permission).length > 0"
                                                                        class="text-muted-foreground mt-1 text-xs"
                                                                    >
                                                                        Requires:
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
                            <InputError :message="form.errors.permissions" class="mt-4" />
                        </CardContent>
                    </Card>

                    <!-- Actions -->
                    <div class="flex items-center justify-end gap-3 pt-4">
                        <Link :href="route('teams.show', team.id)">
                            <Button type="button" variant="outline"> Cancel </Button>
                        </Link>
                        <Button type="submit" :disabled="form.processing || !isAgentSelectionValid">
                            <Loader2 v-if="form.processing" class="mr-2 h-4 w-4 animate-spin" />
                            Save
                        </Button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
