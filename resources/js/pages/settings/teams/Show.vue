<script setup lang="ts">
import { Head, Link, useForm, usePage, router } from '@inertiajs/vue3';
import { ref, computed, reactive, nextTick } from 'vue';

import HeadingSmall from '@/components/HeadingSmall.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { ScrollArea } from '@/components/ui/scroll-area';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { getInitials } from '@/composables/useInitials';
import {
    Users,
    Plus,
    ChevronLeft,
    ChevronDown,
    Crown,
    Shield,
    User,
    UserPlus,
    Mail,
    MoreHorizontal,
    Trash2,
    PencilLine,
    Save,
    Loader2,
    X,
    Edit,
    Clock,
    CheckCircle,
    Bot,
} from 'lucide-vue-next';

interface TeamOwner {
    id: number;
    name: string;
    email: string;
}

interface Team {
    id: number;
    name: string;
    owner: TeamOwner;
}

interface TeamMember {
    id: number;
    name: string;
    email: string;
    role: string;
    role_name: string;
    is_owner: boolean;
    joined_at: string | null;
}

interface TeamRole {
    id: number;
    code: string;
    name: string;
    description: string;
    permissions: string[];
    all_agents: boolean;
    agent_ids: number[];
}

interface TeamAgent {
    id: number;
    name: string;
}

interface Invitation {
    id: number;
    email: string;
    role: string;
    created_at: string;
}

interface PermissionGroup {
    label: string;
    description?: string;
    category: 'agent' | 'other';
    permissions: string[];
}

interface Props {
    team: Team;
    members: TeamMember[];
    roles: TeamRole[];
    agents: TeamAgent[];
    pendingInvitations: Invitation[];
    isOwner: boolean;
    userRole: string;
    availableRoles: Record<string, { name: string; description: string; permissions: string[] }>;
    availablePermissions: Record<string, string>;
    permissionGroups: Record<string, PermissionGroup>;
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Teams',
        href: '/settings/teams',
    },
    {
        title: props.team.name,
        href: `/settings/teams/${props.team.id}`,
    },
];

const page = usePage<SharedData>();
const currentUser = page.props.auth.user;

// Edit team name
const isEditingName = ref(false);
const teamNameInput = ref<HTMLInputElement | null>(null);
const updateTeamForm = useForm({
    name: props.team.name,
});

const startEditingName = () => {
    isEditingName.value = true;
    nextTick(() => {
        const input = teamNameInput.value?.$el || teamNameInput.value;
        input?.focus();
        input?.select();
    });
};

const updateTeamName = () => {
    updateTeamForm.put(route('teams.update', props.team.id), {
        onSuccess: () => {
            isEditingName.value = false;
        },
    });
};

// Invite member
const isInviteDialogOpen = ref(false);
const inviteForm = useForm({
    email: '',
    role: 'admin',
});

const inviteMember = () => {
    inviteForm.post(route('teams.invite', props.team.id), {
        onSuccess: () => {
            isInviteDialogOpen.value = false;
            inviteForm.reset();
        },
    });
};

const openInviteDialogForRole = (role: TeamRole) => {
    inviteForm.role = role.code;
    isInviteDialogOpen.value = true;
};

// Cancel invitation
const cancelInvitationForm = useForm({});
const cancelInvitation = (invitationId: number) => {
    cancelInvitationForm.delete(route('teams.cancel-invitation', { team: props.team.id, invitationId }));
};

// Update member role
const memberToEdit = ref<TeamMember | null>(null);
const updateMemberForm = useForm({
    role: '',
});

const openEditMemberDialog = (member: TeamMember) => {
    memberToEdit.value = member;
    updateMemberForm.role = member.role;
};

const updateMemberRole = () => {
    if (!memberToEdit.value) return;

    updateMemberForm.put(route('teams.update-member', { team: props.team.id, member: memberToEdit.value.id }), {
        onSuccess: () => {
            memberToEdit.value = null;
            updateMemberForm.reset();
        },
    });
};

// Remove member
const removeMemberForm = useForm({});
const memberToRemove = ref<TeamMember | null>(null);

const removeMember = () => {
    if (!memberToRemove.value) return;

    removeMemberForm.delete(route('teams.remove-member', { team: props.team.id, member: memberToRemove.value.id }), {
        onSuccess: () => {
            memberToRemove.value = null;
        },
    });
};

// Create role
const isCreateRoleDialogOpen = ref(false);
const createRoleForm = useForm({
    name: '',
    permissions: [] as string[],
    all_agents: true,
    agent_ids: [] as number[],
});

const createRole = () => {
    createRoleForm.post(route('teams.create-role', props.team.id), {
        onSuccess: () => {
            isCreateRoleDialogOpen.value = false;
            createRoleForm.reset();
        },
    });
};

const togglePermissionForCreate = (permission: string, checked: boolean | "indeterminate") => {
    const isChecked = checked === true;
    if (isChecked) {
        if (!createRoleForm.permissions.includes(permission)) {
            createRoleForm.permissions = [...createRoleForm.permissions, permission];
        }
    } else {
        createRoleForm.permissions = createRoleForm.permissions.filter(p => p !== permission);
    }
};

const toggleGroupPermissionsForCreate = (groupKey: string, checked: boolean | "indeterminate") => {
    const isChecked = checked === true;
    const groupPerms = props.permissionGroups[groupKey]?.permissions || [];

    if (isChecked) {
        // Select all in group
        const newPerms = groupPerms.filter(p => !createRoleForm.permissions.includes(p));
        createRoleForm.permissions = [...createRoleForm.permissions, ...newPerms];
    } else {
        // Deselect all in group
        createRoleForm.permissions = createRoleForm.permissions.filter(p => !groupPerms.includes(p));
    }
};

// Edit role
const roleToEdit = ref<TeamRole | null>(null);
const editRoleForm = useForm({
    name: '',
    permissions: [] as string[],
    all_agents: true,
    agent_ids: [] as number[],
});

const openEditRoleDialog = (role: TeamRole) => {
    roleToEdit.value = role;
    editRoleForm.name = role.name;
    editRoleForm.permissions = [...role.permissions];
    editRoleForm.all_agents = role.all_agents;
    editRoleForm.agent_ids = [...(role.agent_ids || [])];
};

const updateRole = () => {
    if (!roleToEdit.value) return;

    editRoleForm.put(route('teams.update-role', { team: props.team.id, roleId: roleToEdit.value.id }), {
        onSuccess: () => {
            roleToEdit.value = null;
            editRoleForm.reset();
        },
    });
};

const togglePermissionForEdit = (permission: string, checked: boolean | "indeterminate") => {
    const isChecked = checked === true;
    if (isChecked) {
        if (!editRoleForm.permissions.includes(permission)) {
            editRoleForm.permissions = [...editRoleForm.permissions, permission];
        }
    } else {
        editRoleForm.permissions = editRoleForm.permissions.filter(p => p !== permission);
    }
};

const toggleGroupPermissionsForEdit = (groupKey: string, checked: boolean | "indeterminate") => {
    const isChecked = checked === true;
    const groupPerms = props.permissionGroups[groupKey]?.permissions || [];

    if (isChecked) {
        // Select all in group
        const newPerms = groupPerms.filter(p => !editRoleForm.permissions.includes(p));
        editRoleForm.permissions = [...editRoleForm.permissions, ...newPerms];
    } else {
        // Deselect all in group
        editRoleForm.permissions = editRoleForm.permissions.filter(p => !groupPerms.includes(p));
    }
};

// Delete role
const roleToDelete = ref<TeamRole | null>(null);
const deleteRoleForm = useForm({});

const deleteRole = () => {
    if (!roleToDelete.value) return;

    deleteRoleForm.delete(route('teams.delete-role', { team: props.team.id, roleId: roleToDelete.value.id }), {
        onSuccess: () => {
            roleToDelete.value = null;
        },
    });
};

// Helper functions
const getRoleIcon = (role: string) => {
    switch (role) {
        case 'owner':
            return Crown;
        case 'admin':
            return Shield;
        default:
            return User;
    }
};

const getRoleBadgeVariant = (role: string): 'default' | 'secondary' | 'outline' => {
    switch (role) {
        case 'owner':
            return 'default';
        case 'admin':
            return 'secondary';
        default:
            return 'outline';
    }
};

const getPermissionLabel = (permission: string): string => {
    return props.availablePermissions[permission] || permission;
};

const hasPermission = (role: TeamRole, permission: string): boolean => {
    if (role.permissions.includes('*')) return true;
    const [group] = permission.split('.');
    if (role.permissions.includes(`${group}.*`)) return true;
    return role.permissions.includes(permission);
};

const canManageMembers = computed(() => props.isOwner);

// Get selectable roles (exclude owner)
const selectableRoles = computed(() => props.roles.filter(r => r.code !== 'owner'));

// Get displayable roles (exclude owner - owner is implicit)
const displayableRoles = computed(() => props.roles.filter(r => r.code !== 'owner'));

// Permission groups by category
const agentPermissionGroups = computed(() => {
    return Object.entries(props.permissionGroups)
        .filter(([_, group]) => group.category === 'agent')
        .reduce((acc, [key, value]) => ({ ...acc, [key]: value }), {} as Record<string, PermissionGroup>);
});

const otherPermissionGroups = computed(() => {
    return Object.entries(props.permissionGroups)
        .filter(([_, group]) => group.category === 'other')
        .reduce((acc, [key, value]) => ({ ...acc, [key]: value }), {} as Record<string, PermissionGroup>);
});

// Agent selection helpers
const getSelectedAgentNames = (agentIds: number[]): string => {
    if (agentIds.length === 0) return 'Select agents...';
    const names = agentIds
        .map(id => props.agents.find(a => a.id === id)?.name)
        .filter(Boolean);
    if (names.length <= 2) return names.join(', ');
    return `${names.slice(0, 2).join(', ')} +${names.length - 2} more`;
};

const toggleAgentSelection = (form: { agent_ids: number[] }, agentId: number) => {
    if (form.agent_ids.includes(agentId)) {
        form.agent_ids = form.agent_ids.filter(id => id !== agentId);
    } else {
        form.agent_ids = [...form.agent_ids, agentId];
    }
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="`Team: ${team.name}`" />

        <div class="container mx-auto max-w-5xl py-8">
            <!-- Header with back button -->
            <div class="mb-8">
                <Button variant="ghost" size="sm" as-child class="gap-2 -ml-2 mb-4">
                    <Link :href="route('teams.index')">
                        <ChevronLeft class="size-4" />
                        Back to Teams
                    </Link>
                </Button>

                <!-- Team Header -->
                <Card>
                    <CardHeader>
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-4">
                                <Avatar class="size-16 border-2 border-background shadow">
                                    <AvatarFallback class="bg-primary/10 text-primary text-xl font-semibold">
                                        {{ getInitials(team.name) }}
                                    </AvatarFallback>
                                </Avatar>
                                <div>
                                    <div v-if="!isEditingName" class="flex items-center gap-2">
                                        <CardTitle class="text-2xl">{{ team.name }}</CardTitle>
                                        <Button
                                            v-if="isOwner"
                                            variant="ghost"
                                            size="icon"
                                            class="size-8"
                                            @click="startEditingName"
                                        >
                                            <PencilLine class="size-4" />
                                        </Button>
                                    </div>
                                    <form v-else @submit.prevent="updateTeamName" class="flex items-center gap-2">
                                        <Input
                                            ref="teamNameInput"
                                            v-model="updateTeamForm.name"
                                            class="h-8 w-64"
                                        />
                                        <Button type="submit" size="icon" class="size-8" :disabled="updateTeamForm.processing">
                                            <Save class="size-4" />
                                        </Button>
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="icon"
                                            class="size-8"
                                            @click="isEditingName = false; updateTeamForm.reset()"
                                        >
                                            <X class="size-4" />
                                        </Button>
                                    </form>
                                    <CardDescription class="mt-1">
                                        Owned by {{ team.owner.name }}
                                    </CardDescription>
                                </div>
                            </div>
                            <Badge :variant="getRoleBadgeVariant(userRole)" class="gap-1.5">
                                <component :is="getRoleIcon(userRole)" class="size-3.5" />
                                {{ roles.find(r => r.code === userRole)?.name || 'Owner' }}
                            </Badge>
                        </div>
                    </CardHeader>
                </Card>
            </div>

            <!-- Cards Section -->
            <div class="space-y-6">
                <!-- Roles & Permissions -->
                <Card>
                    <CardHeader>
                        <div class="flex items-center justify-between">
                            <div>
                                <CardTitle class="flex items-center gap-2">
                                    <Shield class="size-5" />
                                    Roles & Permissions
                                </CardTitle>
                                <CardDescription>
                                    Define roles and their permissions for team members.
                                </CardDescription>
                            </div>
                            <Dialog v-if="isOwner" v-model:open="isCreateRoleDialogOpen">
                                    <DialogTrigger as-child>
                                        <Button class="gap-2">
                                            <Plus class="size-4" />
                                            Create Role
                                        </Button>
                                    </DialogTrigger>
                                <DialogContent class="sm:max-w-2xl max-h-[85vh] overflow-hidden flex flex-col">
                                    <DialogHeader>
                                        <DialogTitle>Create New Role</DialogTitle>
                                        <DialogDescription>
                                            Define a role name and select the permissions for this role.
                                        </DialogDescription>
                                    </DialogHeader>
                                    <form @submit.prevent="createRole" class="flex flex-col flex-1 overflow-hidden">
                                        <div class="flex-1 overflow-y-auto pr-2 space-y-6 py-4">
                                            <div class="space-y-2">
                                                <Label for="role-name">Role Name</Label>
                                                <Input
                                                    id="role-name"
                                                    v-model="createRoleForm.name"
                                                    placeholder="e.g., Support Agent"
                                                    required
                                                />
                                                <InputError :message="createRoleForm.errors.name" />
                                            </div>

                                            <!-- Agent Access Section (moved to first) -->
                                            <div class="space-y-4">
                                                <div>
                                                    <Label class="text-base flex items-center gap-2">
                                                        <Bot class="size-4" />
                                                        Agent Access
                                                    </Label>
                                                    <p class="text-sm text-muted-foreground mt-1">
                                                        Which agents can members with this role access?
                                                    </p>
                                                </div>

                                                <div class="flex items-center gap-2">
                                                    <Checkbox
                                                        id="create-all-agents"
                                                        :model-value="createRoleForm.all_agents"
                                                        @update:model-value="(checked) => createRoleForm.all_agents = checked === true"
                                                    />
                                                    <Label for="create-all-agents" class="font-medium cursor-pointer">
                                                        All agents (including future agents)
                                                    </Label>
                                                </div>

                                                <div v-if="!createRoleForm.all_agents">
                                                    <Popover>
                                                        <PopoverTrigger as-child>
                                                            <Button
                                                                variant="outline"
                                                                role="combobox"
                                                                class="w-full justify-between font-normal h-auto min-h-10 py-2"
                                                            >
                                                                <span class="flex items-center gap-2 truncate text-left">
                                                                    <Bot class="size-4 text-muted-foreground shrink-0" />
                                                                    {{ getSelectedAgentNames(createRoleForm.agent_ids) }}
                                                                </span>
                                                                <ChevronDown class="ml-2 size-4 shrink-0 opacity-50" />
                                                            </Button>
                                                        </PopoverTrigger>
                                                        <PopoverContent class="w-[--radix-popover-trigger-width] p-2" align="start">
                                                            <ScrollArea class="max-h-60">
                                                                <div v-if="agents.length === 0" class="text-sm text-muted-foreground py-2 text-center">
                                                                    No agents available
                                                                </div>
                                                                <div v-else class="space-y-1">
                                                                    <div
                                                                        v-for="agent in agents"
                                                                        :key="agent.id"
                                                                        class="flex items-center gap-2 p-2 rounded-md hover:bg-accent cursor-pointer"
                                                                        @click="toggleAgentSelection(createRoleForm, agent.id)"
                                                                    >
                                                                        <Checkbox
                                                                            :model-value="createRoleForm.agent_ids.includes(agent.id)"
                                                                            @click.stop
                                                                            @update:model-value="() => toggleAgentSelection(createRoleForm, agent.id)"
                                                                        />
                                                                        <Bot class="size-4 text-muted-foreground" />
                                                                        <span class="text-sm">{{ agent.name }}</span>
                                                                    </div>
                                                                </div>
                                                            </ScrollArea>
                                                        </PopoverContent>
                                                    </Popover>
                                                </div>
                                            </div>

                                            <div class="space-y-4 pt-4 border-t">
                                                <div class="flex items-center justify-between">
                                                    <Label class="text-base">Permissions</Label>
                                                    <span class="text-xs text-muted-foreground">
                                                        {{ createRoleForm.permissions.length }} selected
                                                    </span>
                                                </div>
                                                <InputError :message="createRoleForm.errors.permissions" />

                                                <!-- Agent-specific permissions -->
                                                <div class="space-y-3">
                                                    <p class="text-sm font-medium text-muted-foreground">Agent Permissions</p>
                                                    <p class="text-xs text-muted-foreground -mt-2">These permissions apply to assigned agents only</p>
                                                    <div class="grid gap-4 sm:grid-cols-2">
                                                        <div
                                                            v-for="(groupData, groupKey) in agentPermissionGroups"
                                                            :key="groupKey"
                                                            class="border rounded-lg p-4 space-y-3"
                                                        >
                                                            <div class="flex items-center gap-2">
                                                                <Checkbox
                                                                    :id="`create-group-${groupKey}`"
                                                                    :model-value="groupData.permissions.every(p => createRoleForm.permissions.includes(p))"
                                                                    @update:model-value="(checked) => toggleGroupPermissionsForCreate(groupKey.toString(), checked)"
                                                                />
                                                                <Label :for="`create-group-${groupKey}`" class="font-semibold cursor-pointer">
                                                                    {{ groupData.label }}
                                                                </Label>
                                                            </div>
                                                            <div class="space-y-2 ml-6">
                                                                <div
                                                                    v-for="permission in groupData.permissions"
                                                                    :key="permission"
                                                                    class="flex items-center gap-2"
                                                                >
                                                                    <Checkbox
                                                                        :id="`create-${permission}`"
                                                                        :model-value="createRoleForm.permissions.includes(permission)"
                                                                        @update:model-value="(checked) => togglePermissionForCreate(permission, checked)"
                                                                    />
                                                                    <Label :for="`create-${permission}`" class="text-sm font-normal cursor-pointer">
                                                                        {{ getPermissionLabel(permission) }}
                                                                    </Label>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>

                                                <!-- Other permissions -->
                                                <div class="space-y-3 pt-4 border-t">
                                                    <p class="text-sm font-medium text-muted-foreground">Other Permissions</p>
                                                    <p class="text-xs text-muted-foreground -mt-2">Team-wide and account-level permissions</p>
                                                    <div class="grid gap-4 sm:grid-cols-2">
                                                        <div
                                                            v-for="(groupData, groupKey) in otherPermissionGroups"
                                                            :key="groupKey"
                                                            class="border rounded-lg p-4 space-y-3"
                                                        >
                                                            <div class="flex items-center gap-2">
                                                                <Checkbox
                                                                    :id="`create-other-group-${groupKey}`"
                                                                    :model-value="groupData.permissions.every(p => createRoleForm.permissions.includes(p))"
                                                                    @update:model-value="(checked) => toggleGroupPermissionsForCreate(groupKey.toString(), checked)"
                                                                />
                                                                <Label :for="`create-other-group-${groupKey}`" class="font-semibold cursor-pointer">
                                                                    {{ groupData.label }}
                                                                </Label>
                                                            </div>
                                                            <div class="space-y-2 ml-6">
                                                                <div
                                                                    v-for="permission in groupData.permissions"
                                                                    :key="permission"
                                                                    class="flex items-center gap-2"
                                                                >
                                                                    <Checkbox
                                                                        :id="`create-other-${permission}`"
                                                                        :model-value="createRoleForm.permissions.includes(permission)"
                                                                        @update:model-value="(checked) => togglePermissionForCreate(permission, checked)"
                                                                    />
                                                                    <Label :for="`create-other-${permission}`" class="text-sm font-normal cursor-pointer">
                                                                        {{ getPermissionLabel(permission) }}
                                                                    </Label>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <DialogFooter class="pt-4 border-t mt-4">
                                            <Button type="button" variant="outline" @click="isCreateRoleDialogOpen = false">
                                                Cancel
                                            </Button>
                                            <Button type="submit" :disabled="createRoleForm.processing || createRoleForm.permissions.length === 0">
                                                <Loader2 v-if="createRoleForm.processing" class="mr-2 size-4 animate-spin" />
                                                Create Role
                                            </Button>
                                        </DialogFooter>
                                    </form>
                                </DialogContent>
                            </Dialog>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <div v-if="displayableRoles.length === 0" class="text-center py-8 text-muted-foreground">
                            <p>No custom roles yet. Create a role to assign specific permissions to team members.</p>
                        </div>
                        <div v-else class="space-y-4">
                            <div
                                v-for="role in displayableRoles"
                                :key="role.id"
                                class="border rounded-lg p-4"
                            >
                                <div class="flex items-center justify-between mb-3">
                                    <Badge :variant="getRoleBadgeVariant(role.code)" class="gap-1.5">
                                        <component :is="getRoleIcon(role.code)" class="size-3.5" />
                                        {{ role.name }}
                                    </Badge>
                                    <div class="flex items-center gap-1">
                                        <Button
                                            v-if="canManageMembers"
                                            variant="ghost"
                                            size="icon"
                                            class="size-8"
                                            @click="openInviteDialogForRole(role)"
                                        >
                                            <UserPlus class="size-4" />
                                        </Button>
                                        <Button
                                            v-if="isOwner"
                                            variant="ghost"
                                            size="icon"
                                            class="size-8"
                                            @click="openEditRoleDialog(role)"
                                        >
                                            <Edit class="size-4" />
                                        </Button>
                                        <Button
                                            v-if="isOwner && role.code !== 'admin'"
                                            variant="ghost"
                                            size="icon"
                                            class="size-8 text-muted-foreground hover:text-destructive"
                                            @click="roleToDelete = role"
                                        >
                                            <Trash2 class="size-4" />
                                        </Button>
                                    </div>
                                </div>
                                <!-- Agent Access -->
                                <div class="flex items-center gap-2 text-sm mb-3 pb-3 border-b">
                                    <Bot class="size-4 text-muted-foreground" />
                                    <span class="font-medium">Agent Access:</span>
                                    <span v-if="role.all_agents" class="text-green-600">All agents</span>
                                    <span v-else-if="role.agent_ids?.length === 0" class="text-muted-foreground">No agents assigned</span>
                                    <span v-else class="text-foreground">
                                        {{ role.agent_ids?.length }} agent{{ role.agent_ids?.length === 1 ? '' : 's' }}
                                        <span class="text-muted-foreground">
                                            ({{ agents.filter(a => role.agent_ids?.includes(a.id)).map(a => a.name).join(', ') }})
                                        </span>
                                    </span>
                                </div>

                                <!-- Permissions Grid -->
                                <div class="grid grid-cols-2 sm:grid-cols-3 gap-2">
                                    <template v-for="(groupData, groupKey) in permissionGroups" :key="groupKey">
                                        <div
                                            v-for="permission in groupData.permissions"
                                            :key="permission"
                                            class="flex items-center gap-2 text-sm"
                                        >
                                            <CheckCircle
                                                v-if="hasPermission(role, permission)"
                                                class="size-4 text-green-500 shrink-0"
                                            />
                                            <X v-else class="size-4 text-muted-foreground/50 shrink-0" />
                                            <span :class="[
                                                hasPermission(role, permission)
                                                    ? 'text-foreground'
                                                    : 'text-muted-foreground/50'
                                            ]">
                                                {{ getPermissionLabel(permission) }}
                                            </span>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Team Members -->
                <Card>
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2">
                            <Users class="size-5" />
                            Team Members
                        </CardTitle>
                        <CardDescription>
                            Members who have access to this team.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Member</TableHead>
                                    <TableHead>Role</TableHead>
                                    <TableHead class="text-right">Actions</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow v-for="member in members" :key="member.id">
                                    <TableCell>
                                        <div class="flex items-center gap-3">
                                            <Avatar class="size-9">
                                                <AvatarFallback class="bg-muted text-xs">
                                                    {{ getInitials(member.name) }}
                                                </AvatarFallback>
                                            </Avatar>
                                            <div>
                                                <div class="flex items-center gap-2">
                                                    <span class="font-medium">{{ member.name }}</span>
                                                    <Badge v-if="member.id === currentUser.id" variant="outline" class="text-xs">
                                                        You
                                                    </Badge>
                                                </div>
                                                <span class="text-sm text-muted-foreground">{{ member.email }}</span>
                                            </div>
                                        </div>
                                    </TableCell>
                                    <TableCell>
                                        <Badge :variant="getRoleBadgeVariant(member.role)" class="gap-1">
                                            <component :is="getRoleIcon(member.role)" class="size-3" />
                                            {{ member.role_name }}
                                        </Badge>
                                    </TableCell>
                                    <TableCell class="text-right">
                                        <DropdownMenu v-if="canManageMembers && !member.is_owner && member.id !== currentUser.id">
                                            <DropdownMenuTrigger as-child>
                                                <Button variant="ghost" size="icon" class="size-8">
                                                    <MoreHorizontal class="size-4" />
                                                </Button>
                                            </DropdownMenuTrigger>
                                            <DropdownMenuContent align="end">
                                                <DropdownMenuItem @click="openEditMemberDialog(member)">
                                                    <Edit class="mr-2 size-4" />
                                                    Change Role
                                                </DropdownMenuItem>
                                                <DropdownMenuSeparator />
                                                <DropdownMenuItem
                                                    class="text-destructive focus:text-destructive"
                                                    @click="memberToRemove = member"
                                                >
                                                    <Trash2 class="mr-2 size-4" />
                                                    Remove
                                                </DropdownMenuItem>
                                            </DropdownMenuContent>
                                        </DropdownMenu>
                                        <span v-else-if="member.is_owner" class="text-sm text-muted-foreground">Owner</span>
                                    </TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>
                    </CardContent>
                </Card>

                <!-- Pending Invitations -->
                <Card v-if="pendingInvitations.length > 0">
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2">
                            <Mail class="size-5" />
                            Pending Invitations
                        </CardTitle>
                        <CardDescription>
                            These invitations are waiting to be accepted.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Email</TableHead>
                                    <TableHead>Role</TableHead>
                                    <TableHead>Sent</TableHead>
                                    <TableHead class="text-right">Actions</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow v-for="invitation in pendingInvitations" :key="invitation.id">
                                    <TableCell>
                                        <div class="flex items-center gap-2">
                                            <Clock class="size-4 text-muted-foreground" />
                                            {{ invitation.email }}
                                        </div>
                                    </TableCell>
                                    <TableCell>
                                        <Badge :variant="getRoleBadgeVariant(invitation.role)" class="gap-1">
                                            <component :is="getRoleIcon(invitation.role)" class="size-3" />
                                            {{ roles.find(r => r.code === invitation.role)?.name || invitation.role }}
                                        </Badge>
                                    </TableCell>
                                    <TableCell class="text-sm text-muted-foreground">
                                        {{ new Date(invitation.created_at).toLocaleDateString() }}
                                    </TableCell>
                                    <TableCell class="text-right">
                                        <Button
                                            v-if="canManageMembers"
                                            variant="ghost"
                                            size="sm"
                                            class="text-destructive hover:text-destructive"
                                            @click="cancelInvitation(invitation.id)"
                                            :disabled="cancelInvitationForm.processing"
                                        >
                                            <X class="mr-1 size-4" />
                                            Cancel
                                        </Button>
                                    </TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>
                    </CardContent>
                </Card>
            </div>

            <!-- Invite Member Dialog -->
            <Dialog v-model:open="isInviteDialogOpen">
                <DialogContent class="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>Invite Team Member</DialogTitle>
                        <DialogDescription>
                            Send an invitation to join this team with the selected role.
                        </DialogDescription>
                    </DialogHeader>
                    <form @submit.prevent="inviteMember">
                        <div class="grid gap-4 py-4">
                            <div class="grid gap-2">
                                <Label for="invite-email">Email Address</Label>
                                <Input
                                    id="invite-email"
                                    v-model="inviteForm.email"
                                    type="email"
                                    placeholder="colleague@example.com"
                                    required
                                />
                                <InputError :message="inviteForm.errors.email" />
                            </div>
                            <div class="grid gap-2">
                                <Label>Role</Label>
                                <Badge :variant="getRoleBadgeVariant(inviteForm.role)" class="w-fit gap-1.5">
                                    <component :is="getRoleIcon(inviteForm.role)" class="size-3.5" />
                                    {{ roles.find(r => r.code === inviteForm.role)?.name || inviteForm.role }}
                                </Badge>
                            </div>
                        </div>
                        <DialogFooter>
                            <Button type="button" variant="outline" @click="isInviteDialogOpen = false">
                                Cancel
                            </Button>
                            <Button type="submit" :disabled="inviteForm.processing">
                                <Loader2 v-if="inviteForm.processing" class="mr-2 size-4 animate-spin" />
                                Send Invitation
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            <!-- Edit Member Role Dialog -->
            <Dialog :open="!!memberToEdit" @update:open="(open) => !open && (memberToEdit = null)">
                <DialogContent class="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>Change Member Role</DialogTitle>
                        <DialogDescription>
                            Update the role for {{ memberToEdit?.name }}.
                        </DialogDescription>
                    </DialogHeader>
                    <form @submit.prevent="updateMemberRole">
                        <div class="grid gap-4 py-4">
                            <div class="grid gap-2">
                                <Label for="update-role">Role</Label>
                                <Select v-model="updateMemberForm.role">
                                    <SelectTrigger>
                                        <SelectValue placeholder="Select a role" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem
                                            v-for="role in selectableRoles"
                                            :key="role.code"
                                            :value="role.code"
                                        >
                                            {{ role.name }}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                                <InputError :message="updateMemberForm.errors.role" />
                            </div>
                        </div>
                        <DialogFooter>
                            <Button type="button" variant="outline" @click="memberToEdit = null">
                                Cancel
                            </Button>
                            <Button type="submit" :disabled="updateMemberForm.processing">
                                <Loader2 v-if="updateMemberForm.processing" class="mr-2 size-4 animate-spin" />
                                Update Role
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            <!-- Edit Role Dialog -->
            <Dialog :open="!!roleToEdit" @update:open="(open) => !open && (roleToEdit = null)">
                <DialogContent class="sm:max-w-2xl max-h-[85vh] overflow-hidden flex flex-col">
                    <DialogHeader>
                        <DialogTitle>Edit Role: {{ roleToEdit?.name }}</DialogTitle>
                        <DialogDescription>
                            Modify the role name and permissions.
                        </DialogDescription>
                    </DialogHeader>
                    <form @submit.prevent="updateRole" class="flex flex-col flex-1 overflow-hidden">
                        <div class="flex-1 overflow-y-auto pr-2 space-y-6 py-4">
                            <div class="space-y-2">
                                <Label for="edit-role-name">Role Name</Label>
                                <Input
                                    id="edit-role-name"
                                    v-model="editRoleForm.name"
                                    required
                                />
                                <InputError :message="editRoleForm.errors.name" />
                            </div>

                            <!-- Agent Access Section (moved to first) -->
                            <div class="space-y-4">
                                <div>
                                    <Label class="text-base flex items-center gap-2">
                                        <Bot class="size-4" />
                                        Agent Access
                                    </Label>
                                    <p class="text-sm text-muted-foreground mt-1">
                                        Which agents can members with this role access?
                                    </p>
                                </div>

                                <div class="flex items-center gap-2">
                                    <Checkbox
                                        id="edit-all-agents"
                                        :model-value="editRoleForm.all_agents"
                                        @update:model-value="(checked) => editRoleForm.all_agents = checked === true"
                                    />
                                    <Label for="edit-all-agents" class="font-medium cursor-pointer">
                                        All agents (including future agents)
                                    </Label>
                                </div>

                                <div v-if="!editRoleForm.all_agents">
                                    <Popover>
                                        <PopoverTrigger as-child>
                                            <Button
                                                variant="outline"
                                                role="combobox"
                                                class="w-full justify-between font-normal h-auto min-h-10 py-2"
                                            >
                                                <span class="flex items-center gap-2 truncate text-left">
                                                    <Bot class="size-4 text-muted-foreground shrink-0" />
                                                    {{ getSelectedAgentNames(editRoleForm.agent_ids) }}
                                                </span>
                                                <ChevronDown class="ml-2 size-4 shrink-0 opacity-50" />
                                            </Button>
                                        </PopoverTrigger>
                                        <PopoverContent class="w-[--radix-popover-trigger-width] p-2" align="start">
                                            <ScrollArea class="max-h-60">
                                                <div v-if="agents.length === 0" class="text-sm text-muted-foreground py-2 text-center">
                                                    No agents available
                                                </div>
                                                <div v-else class="space-y-1">
                                                    <div
                                                        v-for="agent in agents"
                                                        :key="agent.id"
                                                        class="flex items-center gap-2 p-2 rounded-md hover:bg-accent cursor-pointer"
                                                        @click="toggleAgentSelection(editRoleForm, agent.id)"
                                                    >
                                                        <Checkbox
                                                            :model-value="editRoleForm.agent_ids.includes(agent.id)"
                                                            @click.stop
                                                            @update:model-value="() => toggleAgentSelection(editRoleForm, agent.id)"
                                                        />
                                                        <Bot class="size-4 text-muted-foreground" />
                                                        <span class="text-sm">{{ agent.name }}</span>
                                                    </div>
                                                </div>
                                            </ScrollArea>
                                        </PopoverContent>
                                    </Popover>
                                </div>
                            </div>

                            <div class="space-y-4 pt-4 border-t">
                                <div class="flex items-center justify-between">
                                    <Label class="text-base">Permissions</Label>
                                    <span class="text-xs text-muted-foreground">
                                        {{ editRoleForm.permissions.length }} selected
                                    </span>
                                </div>
                                <InputError :message="editRoleForm.errors.permissions" />

                                <!-- Agent-specific permissions -->
                                <div class="space-y-3">
                                    <p class="text-sm font-medium text-muted-foreground">Agent Permissions</p>
                                    <p class="text-xs text-muted-foreground -mt-2">These permissions apply to assigned agents only</p>
                                    <div class="grid gap-4 sm:grid-cols-2">
                                        <div
                                            v-for="(groupData, groupKey) in agentPermissionGroups"
                                            :key="groupKey"
                                            class="border rounded-lg p-4 space-y-3"
                                        >
                                            <div class="flex items-center gap-2">
                                                <Checkbox
                                                    :id="`edit-group-${groupKey}`"
                                                    :model-value="groupData.permissions.every(p => editRoleForm.permissions.includes(p))"
                                                    @update:model-value="(checked) => toggleGroupPermissionsForEdit(groupKey.toString(), checked)"
                                                />
                                                <Label :for="`edit-group-${groupKey}`" class="font-semibold cursor-pointer">
                                                    {{ groupData.label }}
                                                </Label>
                                            </div>
                                            <div class="space-y-2 ml-6">
                                                <div
                                                    v-for="permission in groupData.permissions"
                                                    :key="permission"
                                                    class="flex items-center gap-2"
                                                >
                                                    <Checkbox
                                                        :id="`edit-${permission}`"
                                                        :model-value="editRoleForm.permissions.includes(permission)"
                                                        @update:model-value="(checked) => togglePermissionForEdit(permission, checked)"
                                                    />
                                                    <Label :for="`edit-${permission}`" class="text-sm font-normal cursor-pointer">
                                                        {{ getPermissionLabel(permission) }}
                                                    </Label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Other permissions -->
                                <div class="space-y-3 pt-4 border-t">
                                    <p class="text-sm font-medium text-muted-foreground">Other Permissions</p>
                                    <p class="text-xs text-muted-foreground -mt-2">Team-wide and account-level permissions</p>
                                    <div class="grid gap-4 sm:grid-cols-2">
                                        <div
                                            v-for="(groupData, groupKey) in otherPermissionGroups"
                                            :key="groupKey"
                                            class="border rounded-lg p-4 space-y-3"
                                        >
                                            <div class="flex items-center gap-2">
                                                <Checkbox
                                                    :id="`edit-other-group-${groupKey}`"
                                                    :model-value="groupData.permissions.every(p => editRoleForm.permissions.includes(p))"
                                                    @update:model-value="(checked) => toggleGroupPermissionsForEdit(groupKey.toString(), checked)"
                                                />
                                                <Label :for="`edit-other-group-${groupKey}`" class="font-semibold cursor-pointer">
                                                    {{ groupData.label }}
                                                </Label>
                                            </div>
                                            <div class="space-y-2 ml-6">
                                                <div
                                                    v-for="permission in groupData.permissions"
                                                    :key="permission"
                                                    class="flex items-center gap-2"
                                                >
                                                    <Checkbox
                                                        :id="`edit-other-${permission}`"
                                                        :model-value="editRoleForm.permissions.includes(permission)"
                                                        @update:model-value="(checked) => togglePermissionForEdit(permission, checked)"
                                                    />
                                                    <Label :for="`edit-other-${permission}`" class="text-sm font-normal cursor-pointer">
                                                        {{ getPermissionLabel(permission) }}
                                                    </Label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <DialogFooter class="pt-4 border-t mt-4">
                            <Button type="button" variant="outline" @click="roleToEdit = null">
                                Cancel
                            </Button>
                            <Button type="submit" :disabled="editRoleForm.processing || editRoleForm.permissions.length === 0">
                                <Loader2 v-if="editRoleForm.processing" class="mr-2 size-4 animate-spin" />
                                Save Changes
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            <!-- Remove Member Confirmation -->
            <AlertDialog :open="!!memberToRemove" @update:open="(open) => !open && (memberToRemove = null)">
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>Remove Team Member</AlertDialogTitle>
                        <AlertDialogDescription>
                            Are you sure you want to remove {{ memberToRemove?.name }} from this team? They will lose access to all team resources.
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel @click="memberToRemove = null">Cancel</AlertDialogCancel>
                        <AlertDialogAction
                            class="bg-destructive text-destructive-foreground hover:bg-destructive/90"
                            @click="removeMember"
                            :disabled="removeMemberForm.processing"
                        >
                            <Loader2 v-if="removeMemberForm.processing" class="mr-2 size-4 animate-spin" />
                            Remove Member
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>

            <!-- Delete Role Confirmation -->
            <AlertDialog :open="!!roleToDelete" @update:open="(open) => !open && (roleToDelete = null)">
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>Delete Role</AlertDialogTitle>
                        <AlertDialogDescription>
                            Are you sure you want to delete the "{{ roleToDelete?.name }}" role? This action cannot be undone.
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel @click="roleToDelete = null">Cancel</AlertDialogCancel>
                        <Button
                            variant="destructive"
                            @click="deleteRole"
                            :disabled="deleteRoleForm.processing"
                        >
                            <Loader2 v-if="deleteRoleForm.processing" class="mr-2 size-4 animate-spin" />
                            Delete Role
                        </Button>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </div>
    </AppLayout>
</template>
