<script setup lang="ts">
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed, nextTick, ref } from 'vue';

import InputError from '@/components/InputError.vue';
import {
    AlertDialog,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuSeparator, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import { getInitials } from '@/composables/useInitials';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem, type SharedData } from '@/types';
import {
    Bot,
    CheckCircle,
    ChevronLeft,
    ChevronRight,
    Clock,
    Edit,
    Info,
    Loader2,
    Mail,
    MoreHorizontal,
    PencilLine,
    Plus,
    Save,
    Shield,
    Trash2,
    UserCog,
    UserPlus,
    Users,
    X,
} from 'lucide-vue-next';

// Use generated TypeScript types from DTOs
type TeamData = App.Data.Resources.Team.TeamData;
type TeamMemberData = App.Data.Resources.Team.TeamMemberData;
type TeamRoleData = App.Data.Resources.Team.TeamRoleData;
type TeamAgentData = App.Data.Resources.Team.TeamAgentData;
type TeamInvitationData = App.Data.Resources.Team.TeamInvitationData;
type ManagedUserData = App.Data.Resources.Team.ManagedUserData;
type PermissionMetaData = App.Data.Resources.Team.PermissionMetaData;
type PermissionGroupData = App.Data.Resources.Team.PermissionGroupData;
type RoleConfigData = App.Data.Resources.Team.RoleConfigData;

interface Props {
    team: TeamData;
    members: TeamMemberData[];
    roles: TeamRoleData[];
    agents: TeamAgentData[];
    pendingInvitations: TeamInvitationData[];
    managedUsers: ManagedUserData[];
    isOwner: boolean;
    userRole: string;
    availableRoles: Record<string, RoleConfigData>;
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

// Expanded roles (for permissions toggle)
const expandedRoles = ref<Set<number>>(new Set());

const toggleRoleExpanded = (roleId: number) => {
    if (expandedRoles.value.has(roleId)) {
        expandedRoles.value.delete(roleId);
    } else {
        expandedRoles.value.add(roleId);
    }
};

// Invite member
const isInviteDialogOpen = ref(false);
const inviteType = ref<'existing' | 'email'>('existing'); // default to existing managed users
const inviteForm = useForm({
    user_id: null as number | null,
    email: '',
    role: 'admin',
});

const inviteMember = () => {
    const data =
        inviteType.value === 'existing' ? { user_id: inviteForm.user_id, role: inviteForm.role } : { email: inviteForm.email, role: inviteForm.role };

    inviteForm.post(route('teams.invite', props.team.id), {
        data,
        onSuccess: () => {
            isInviteDialogOpen.value = false;
            inviteForm.reset();
            inviteType.value = 'existing';
        },
    });
};

const openInviteDialogForRole = (role: TeamRoleData) => {
    inviteForm.role = role.code;
    inviteType.value = props.managedUsers.length > 0 ? 'existing' : 'email';
    isInviteDialogOpen.value = true;
};

// Cancel invitation
const cancelInvitationForm = useForm({});
const cancelInvitation = (invitationId: number) => {
    cancelInvitationForm.delete(route('teams.cancel-invitation', { team: props.team.id, invitationId }));
};

// Update member role
const memberToEdit = ref<TeamMemberData | null>(null);
const updateMemberForm = useForm({
    role: '',
});

const openEditMemberDialog = (member: TeamMemberData) => {
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
const memberToRemove = ref<TeamMemberData | null>(null);

const removeMember = () => {
    if (!memberToRemove.value) return;

    removeMemberForm.delete(route('teams.remove-member', { team: props.team.id, member: memberToRemove.value.id }), {
        onSuccess: () => {
            memberToRemove.value = null;
        },
    });
};

// Create and Edit role now use dedicated pages
// See: /settings/teams/{team}/roles/create
// See: /settings/teams/{team}/roles/{roleId}/edit

// Delete role
const roleToDelete = ref<TeamRoleData | null>(null);
const deleteRoleForm = useForm({});

// View role members
const viewingRoleMembers = ref<TeamRoleData | null>(null);
const isRoleMembersDialogOpen = ref(false);

const openRoleMembersDialog = (role: TeamRoleData) => {
    viewingRoleMembers.value = role;
    isRoleMembersDialogOpen.value = true;
};

const getRoleMemberCount = (roleCode: string): number => {
    return props.members.filter((m) => m.role === roleCode).length;
};

const getRoleMembers = (roleCode: string) => {
    return props.members.filter((m) => m.role === roleCode);
};

const deleteRole = () => {
    if (!roleToDelete.value) return;

    deleteRoleForm.delete(route('teams.delete-role', { team: props.team.id, roleId: roleToDelete.value.id }), {
        onSuccess: () => {
            roleToDelete.value = null;
        },
    });
};

// Helper functions
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
    return props.permissionsConfig[permission]?.label || permission;
};

const getPermissionDescription = (permission: string): string => {
    return props.permissionsConfig[permission]?.description || '';
};

const hasPermission = (role: TeamRoleData, permission: string): boolean => {
    if (role.permissions.includes('*')) return true;
    const [group] = permission.split('.');
    if (role.permissions.includes(`${group}.*`)) return true;
    return role.permissions.includes(permission);
};

const canManageMembers = computed(() => props.isOwner);

// Get selectable roles (exclude owner)
const selectableRoles = computed(() => props.roles.filter((r) => r.code !== 'owner'));

// Get displayable roles (exclude owner - owner is implicit)
const displayableRoles = computed(() => props.roles.filter((r) => r.code !== 'owner'));
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="`Team: ${team.name}`" />

        <div class="container mx-auto max-w-5xl py-8">
            <!-- Header with back button -->
            <div class="mb-8">
                <Button variant="ghost" size="sm" as-child class="mb-4 -ml-2 gap-2">
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
                                <Avatar class="border-background size-16 border-2 shadow">
                                    <AvatarFallback class="bg-primary/10 text-primary text-xl font-semibold">
                                        {{ getInitials(team.name) }}
                                    </AvatarFallback>
                                </Avatar>
                                <div>
                                    <div v-if="!isEditingName" class="flex items-center gap-2">
                                        <CardTitle class="text-2xl">{{ team.name }}</CardTitle>
                                        <Button v-if="isOwner" variant="ghost" size="icon" class="size-8" @click="startEditingName">
                                            <PencilLine class="size-4" />
                                        </Button>
                                    </div>
                                    <form v-else @submit.prevent="updateTeamName" class="flex items-center gap-2">
                                        <Input ref="teamNameInput" v-model="updateTeamForm.name" class="h-8 w-64" />
                                        <Button type="submit" size="icon" class="size-8" :disabled="updateTeamForm.processing">
                                            <Save class="size-4" />
                                        </Button>
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="icon"
                                            class="size-8"
                                            @click="
                                                isEditingName = false;
                                                updateTeamForm.reset();
                                            "
                                        >
                                            <X class="size-4" />
                                        </Button>
                                    </form>
                                </div>
                            </div>
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
                                <CardDescription> Define roles and their permissions for team members. </CardDescription>
                            </div>
                            <Link v-if="isOwner" :href="route('teams.roles.create', team.id)">
                                <Button class="gap-2">
                                    <Plus class="size-4" />
                                    Create Role
                                </Button>
                            </Link>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <div v-if="displayableRoles.length === 0" class="text-muted-foreground py-8 text-center">
                            <p>No custom roles yet. Create a role to assign specific permissions to team members.</p>
                        </div>
                        <div v-else class="space-y-4">
                            <div v-for="role in displayableRoles" :key="role.id" class="rounded-lg border p-4">
                                <div class="mb-3 flex items-center justify-between">
                                    <div class="flex items-center gap-2">
                                        <Button variant="ghost" size="icon" class="size-6" @click="toggleRoleExpanded(role.id)">
                                            <ChevronRight
                                                class="size-4 transition-transform"
                                                :class="expandedRoles.has(role.id) ? 'rotate-90' : ''"
                                            />
                                        </Button>
                                        <div class="flex items-center gap-1.5">
                                            <span class="text-base font-semibold">{{ role.name }}</span>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-1">
                                        <TooltipProvider v-if="canManageMembers" :delay-duration="0">
                                            <Tooltip>
                                                <TooltipTrigger as-child>
                                                    <Button variant="ghost" size="icon" class="size-8" @click="openInviteDialogForRole(role)">
                                                        <UserPlus class="size-4" />
                                                    </Button>
                                                </TooltipTrigger>
                                                <TooltipContent>
                                                    <p>Add Member</p>
                                                </TooltipContent>
                                            </Tooltip>
                                        </TooltipProvider>
                                        <TooltipProvider v-if="canManageMembers" :delay-duration="0">
                                            <Tooltip>
                                                <TooltipTrigger as-child>
                                                    <Button variant="ghost" size="icon" class="relative size-8" @click="openRoleMembersDialog(role)">
                                                        <UserCog class="size-4" />
                                                        <span
                                                            v-if="getRoleMemberCount(role.code) > 0"
                                                            class="bg-primary text-primary-foreground absolute -top-1 -right-1 flex size-4 items-center justify-center rounded-full text-[10px]"
                                                        >
                                                            {{ getRoleMemberCount(role.code) }}
                                                        </span>
                                                    </Button>
                                                </TooltipTrigger>
                                                <TooltipContent>
                                                    <p>View Members ({{ getRoleMemberCount(role.code) }})</p>
                                                </TooltipContent>
                                            </Tooltip>
                                        </TooltipProvider>
                                        <TooltipProvider v-if="isOwner" :delay-duration="0">
                                            <Tooltip>
                                                <TooltipTrigger as-child>
                                                    <Button variant="ghost" size="icon" class="size-8" as-child>
                                                        <Link :href="route('teams.roles.edit', { team: team.id, roleId: role.id })">
                                                            <Edit class="size-4" />
                                                        </Link>
                                                    </Button>
                                                </TooltipTrigger>
                                                <TooltipContent>
                                                    <p>Edit Role</p>
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
                                                        @click="roleToDelete = role"
                                                    >
                                                        <Trash2 class="size-4" />
                                                    </Button>
                                                </TooltipTrigger>
                                                <TooltipContent>
                                                    <p>Delete Role</p>
                                                </TooltipContent>
                                            </Tooltip>
                                        </TooltipProvider>
                                    </div>
                                </div>

                                <div v-if="expandedRoles.has(role.id)">
                                    <!-- Agent Access -->
                                    <div class="mb-3 flex items-center gap-2 border-b pb-3 text-sm">
                                        <Bot class="text-muted-foreground size-4" />
                                        <span class="font-medium">Agent Access:</span>
                                        <span v-if="role.all_agents" class="text-green-600">All agents</span>
                                        <span v-else-if="role.agent_ids?.length === 0" class="text-muted-foreground">No agents assigned</span>
                                        <span v-else class="text-foreground">
                                            {{ role.agent_ids?.length }} agent{{ role.agent_ids?.length === 1 ? '' : 's' }}
                                            <span class="text-muted-foreground">
                                                ({{
                                                    agents
                                                        .filter((a) => role.agent_ids?.includes(a.id))
                                                        .map((a) => a.name)
                                                        .join(', ')
                                                }})
                                            </span>
                                        </span>
                                    </div>

                                    <!-- Permissions Grid -->
                                    <TooltipProvider>
                                        <div class="grid grid-cols-2 gap-2 sm:grid-cols-3">
                                            <template v-for="(groupData, groupKey) in permissionGroups" :key="groupKey">
                                                <div
                                                    v-for="permission in groupData.permissions"
                                                    :key="permission"
                                                    class="flex items-center gap-1.5 text-sm"
                                                >
                                                    <CheckCircle v-if="hasPermission(role, permission)" class="size-4 shrink-0 text-green-500" />
                                                    <X v-else class="text-muted-foreground/50 size-4 shrink-0" />
                                                    <span :class="[hasPermission(role, permission) ? 'text-foreground' : 'text-muted-foreground/50']">
                                                        {{ getPermissionLabel(permission) }}
                                                    </span>
                                                    <Tooltip v-if="getPermissionDescription(permission)">
                                                        <TooltipTrigger as-child>
                                                            <Info class="text-muted-foreground hover:text-foreground size-3.5 shrink-0 cursor-help" />
                                                        </TooltipTrigger>
                                                        <TooltipContent side="top" class="max-w-xs">
                                                            <p>{{ getPermissionDescription(permission) }}</p>
                                                        </TooltipContent>
                                                    </Tooltip>
                                                </div>
                                            </template>
                                        </div>
                                    </TooltipProvider>
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
                        <CardDescription> Members who have access to this team. </CardDescription>
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
                                                    <Badge v-if="member.id === currentUser.id" variant="outline" class="text-xs"> You </Badge>
                                                </div>
                                                <span class="text-muted-foreground text-sm">{{ member.email }}</span>
                                            </div>
                                        </div>
                                    </TableCell>
                                    <TableCell>
                                        <Badge :variant="getRoleBadgeVariant(member.role)">
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
                                                <DropdownMenuItem class="text-destructive focus:text-destructive" @click="memberToRemove = member">
                                                    <Trash2 class="mr-2 size-4" />
                                                    Remove
                                                </DropdownMenuItem>
                                            </DropdownMenuContent>
                                        </DropdownMenu>
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
                        <CardDescription> These invitations are waiting to be accepted. </CardDescription>
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
                                            <Clock class="text-muted-foreground size-4" />
                                            {{ invitation.email }}
                                        </div>
                                    </TableCell>
                                    <TableCell>
                                        <Badge :variant="getRoleBadgeVariant(invitation.role)">
                                            {{ roles.find((r) => r.code === invitation.role)?.name || invitation.role }}
                                        </Badge>
                                    </TableCell>
                                    <TableCell class="text-muted-foreground text-sm">
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
                        <DialogTitle>Add Team Member</DialogTitle>
                        <DialogDescription> Add an existing user or invite someone new to join this team. </DialogDescription>
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
                                    Select Existing
                                </Button>
                                <Button
                                    type="button"
                                    :variant="inviteType === 'email' ? 'default' : 'ghost'"
                                    size="sm"
                                    @click="inviteType = 'email'"
                                    class="flex-1"
                                >
                                    Invite by Email
                                </Button>
                            </div>

                            <!-- Existing users dropdown -->
                            <div v-if="inviteType === 'existing' && managedUsers.length > 0" class="grid gap-2">
                                <Label>Select User</Label>
                                <Select v-model="inviteForm.user_id">
                                    <SelectTrigger>
                                        <SelectValue placeholder="Choose a user..." />
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
                                <Label for="invite-email">Email Address</Label>
                                <Input
                                    id="invite-email"
                                    v-model="inviteForm.email"
                                    type="email"
                                    placeholder="colleague@example.com"
                                    :required="inviteType === 'email'"
                                />
                                <InputError :message="inviteForm.errors.email" />
                            </div>

                            <!-- Role display -->
                            <div class="grid gap-2">
                                <Label>Role</Label>
                                <Badge :variant="getRoleBadgeVariant(inviteForm.role)" class="w-fit">
                                    {{ roles.find((r) => r.code === inviteForm.role)?.name || inviteForm.role }}
                                </Badge>
                            </div>
                        </div>
                        <DialogFooter>
                            <Button type="button" variant="outline" @click="isInviteDialogOpen = false"> Cancel </Button>
                            <Button type="submit" :disabled="inviteForm.processing || (inviteType === 'existing' && !inviteForm.user_id)">
                                <Loader2 v-if="inviteForm.processing" class="mr-2 size-4 animate-spin" />
                                {{ inviteType === 'existing' ? 'Add Member' : 'Send Invitation' }}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            <!-- Role Members Dialog -->
            <Dialog v-model:open="isRoleMembersDialogOpen">
                <DialogContent class="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle v-if="viewingRoleMembers"> {{ viewingRoleMembers.name }} Members </DialogTitle>
                        <DialogDescription> Members with this role. You can remove their access to this team. </DialogDescription>
                    </DialogHeader>
                    <div v-if="viewingRoleMembers" class="py-4">
                        <div v-if="getRoleMembers(viewingRoleMembers.code).length === 0" class="text-muted-foreground py-8 text-center">
                            <p>No members with this role yet.</p>
                        </div>
                        <div v-else class="space-y-2">
                            <div
                                v-for="member in getRoleMembers(viewingRoleMembers.code)"
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
                                    @click="
                                        memberToRemove = member;
                                        isRoleMembersDialogOpen = false;
                                    "
                                >
                                    <Trash2 class="size-4" />
                                </Button>
                            </div>
                        </div>
                    </div>
                    <DialogFooter>
                        <Button type="button" variant="outline" @click="isRoleMembersDialogOpen = false"> Close </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <!-- Edit Member Role Dialog -->
            <Dialog :open="!!memberToEdit" @update:open="(open) => !open && (memberToEdit = null)">
                <DialogContent class="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>Change Member Role</DialogTitle>
                        <DialogDescription> Update the role for {{ memberToEdit?.name }}. </DialogDescription>
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
                                        <SelectItem v-for="role in selectableRoles" :key="role.code" :value="role.code">
                                            {{ role.name }}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                                <InputError :message="updateMemberForm.errors.role" />
                            </div>
                        </div>
                        <DialogFooter>
                            <Button type="button" variant="outline" @click="memberToEdit = null"> Cancel </Button>
                            <Button type="submit" :disabled="updateMemberForm.processing">
                                <Loader2 v-if="updateMemberForm.processing" class="mr-2 size-4 animate-spin" />
                                Update Role
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            <!-- Edit Role now uses dedicated page at /settings/teams/{team}/roles/{roleId}/edit -->

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
                        <AlertDialogCancel>Cancel</AlertDialogCancel>
                        <Button variant="destructive" @click="removeMember" :disabled="removeMemberForm.processing">
                            <Loader2 v-if="removeMemberForm.processing" class="mr-2 size-4 animate-spin" />
                            Remove Member
                        </Button>
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
                        <AlertDialogCancel>Cancel</AlertDialogCancel>
                        <Button variant="destructive" @click="deleteRole" :disabled="deleteRoleForm.processing">
                            <Loader2 v-if="deleteRoleForm.processing" class="mr-2 size-4 animate-spin" />
                            Delete Role
                        </Button>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </div>
    </AppLayout>
</template>
