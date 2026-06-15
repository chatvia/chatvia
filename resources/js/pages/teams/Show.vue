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
import { useTeamPermissionsI18n } from '@/composables/useTeamPermissionsI18n';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem, type SharedData } from '@/types';
import {
    Bot,
    Check,
    CheckCircle,
    ChevronLeft,
    ChevronRight,
    Clock,
    Copy,
    Edit,
    Info,
    Loader2,
    Mail,
    MoreHorizontal,
    PencilLine,
    Plus,
    Save,
    Send,
    Shield,
    Trash2,
    UserCog,
    UserPlus,
    Users,
    UserX,
    X,
} from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';
import { toast } from 'vue-sonner';

const { t } = useI18n();
const { permissionLabel, permissionDescription } = useTeamPermissionsI18n();

// Use generated TypeScript types from DTOs
type TeamData = Domain.Membership.Team.Data.Resources.Team.TeamData;
type TeamMemberData = Domain.Membership.Team.Data.Resources.Members.TeamMemberData;
type TeamRoleData = Domain.Membership.Team.Data.Resources.Roles.TeamRoleData;
type TeamAgentData = Domain.Membership.Team.Data.Resources.Team.TeamAgentData;
type TeamInvitationData = Domain.Membership.Team.Data.Resources.Members.TeamInvitationData;
type ManagedUserData = Domain.Membership.Team.Data.Resources.Members.ManagedUserData;
type PermissionMetaData = Domain.Membership.Team.Data.Resources.Permissions.PermissionMetaData;
type PermissionGroupData = Domain.Membership.Team.Data.Resources.Permissions.PermissionGroupData;
type RoleConfigData = Domain.Membership.Team.Data.Resources.Roles.RoleConfigData;

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
        title: t('teams.breadcrumb'),
        href: '/my/teams',
    },
    {
        title: props.team.name,
        href: `/my/teams/${props.team.external_id}`,
    },
];

// Two-second "Copied!" flash after clicking the team-id copy button.
const copiedExternalId = ref(false);
const copyExternalId = async () => {
    try {
        await navigator.clipboard.writeText(props.team.external_id);
        copiedExternalId.value = true;
        setTimeout(() => (copiedExternalId.value = false), 2000);
    } catch {
        // Clipboard API can fail under permissions / non-HTTPS — fail silently
        // rather than throwing a toast for what's just a convenience copy.
    }
};

const page = usePage<SharedData>();
const currentUser = page.props.auth.user;

// Edit team name
const isEditingName = ref(false);
// The template attaches this ref to a wrapped `Input` component; `$el` is the
// underlying <input> when present, otherwise the ref already is the input.
const teamNameInput = ref<HTMLInputElement | { $el?: HTMLInputElement } | null>(null);
const updateTeamForm = useForm({
    name: props.team.name,
});

const startEditingName = () => {
    isEditingName.value = true;
    nextTick(() => {
        const refValue = teamNameInput.value;
        const input = (refValue && '$el' in refValue ? refValue.$el : refValue) as HTMLInputElement | undefined;
        input?.focus();
        input?.select();
    });
};

const updateTeamName = () => {
    updateTeamForm.put(route('teams.update', props.team.external_id), {
        onSuccess: () => {
            isEditingName.value = false;
        },
    });
};

// Expanded roles (for permissions toggle)
const expandedRoles = ref<Set<number>>(new Set());

// Two-second "Copied!" flash per role, keyed by role id. A Set lets multiple
// rapid copies coexist without overwriting each other's flash state.
const copiedRoleIds = ref<Set<number>>(new Set());
const copyRoleExternalId = async (role: TeamRoleData) => {
    try {
        await navigator.clipboard.writeText(role.external_id);
        copiedRoleIds.value.add(role.id);
        setTimeout(() => copiedRoleIds.value.delete(role.id), 2000);
    } catch {
        // Clipboard API can fail under permissions / non-HTTPS — fail silently.
    }
};

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
    // Reshape the submitted payload depending on whether we're inviting an
    // existing managed user (by id) or a new one (by email). Inertia's
    // `transform()` is the supported way to mutate the data right before send.
    const wasEmailInvite = inviteType.value === 'email';
    const invitedEmail = inviteForm.email;

    inviteForm
        .transform((current) =>
            inviteType.value === 'existing' ? { user_id: current.user_id, role: current.role } : { email: current.email, role: current.role },
        )
        .post(route('teams.invite', props.team.external_id), {
            onSuccess: () => {
                isInviteDialogOpen.value = false;
                inviteForm.reset();
                inviteType.value = 'existing';

                toast.success(
                    wasEmailInvite ? t('teams.show.invite.toast_invite_sent', { email: invitedEmail }) : t('teams.show.invite.toast_member_added'),
                );
            },
            onError: () => toast.error(t('teams.show.invite.toast_failed')),
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
    cancelInvitationForm.delete(route('teams.cancel-invitation', { team: props.team.external_id, invitationId }));
};

// Resend invitation
const resendInvitationForm = useForm({});
const resendingInvitationId = ref<number | null>(null);
const resendInvitation = (invitationId: number) => {
    resendingInvitationId.value = invitationId;
    resendInvitationForm.post(route('teams.resend-invitation', { team: props.team.external_id, invitationId }), {
        preserveScroll: true,
        onSuccess: () => toast.success(t('teams.show.invitations.toast_resent')),
        onError: (errors) => toast.error(errors.invitation || t('teams.show.invitations.toast_resend_failed')),
        onFinish: () => {
            resendingInvitationId.value = null;
        },
    });
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

    updateMemberForm.put(route('teams.update-member', { team: props.team.external_id, member: memberToEdit.value.id }), {
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

    removeMemberForm.delete(route('teams.remove-member', { team: props.team.external_id, member: memberToRemove.value.id }), {
        onSuccess: () => {
            memberToRemove.value = null;
        },
    });
};

// Remove from workspace — permanently delete the teammate's account and all
// their data (distinct from removeMember above, which only detaches the team
// pivot). Workspace-level, so it is not scoped to this team.
const removeFromWorkspaceForm = useForm({});
const memberToDeleteFromWorkspace = ref<TeamMemberData | null>(null);

const removeFromWorkspace = () => {
    if (!memberToDeleteFromWorkspace.value) return;

    removeFromWorkspaceForm.delete(route('teammates.remove', { member: memberToDeleteFromWorkspace.value.id }), {
        onSuccess: () => {
            memberToDeleteFromWorkspace.value = null;
        },
    });
};

// Create and Edit role now use dedicated pages
// See: /my/teams/{team}/roles/create
// See: /my/teams/{team}/roles/{roleId}/edit

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

    deleteRoleForm.delete(route('teams.delete-role', { team: props.team.external_id, roleId: roleToDelete.value.id }), {
        onSuccess: () => {
            roleToDelete.value = null;
        },
    });
};

// Helper functions
const getRoleBadgeVariant = (role: string | null): 'default' | 'secondary' | 'outline' => {
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
    const fallback = props.permissionsConfig[permission]?.label || permission;
    return permissionLabel(permission, fallback);
};

const getPermissionDescription = (permission: string): string => {
    const fallback = props.permissionsConfig[permission]?.description || '';
    return permissionDescription(permission, fallback);
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
        <Head :title="t('teams.show.title', { name: team.name })" />

        <div class="container mx-auto max-w-5xl py-8">
            <!-- Header with back button -->
            <div class="mb-8">
                <Button variant="ghost" size="sm" as-child class="-ms-2 mb-4 gap-2">
                    <Link :href="route('teams.index')">
                        <ChevronLeft class="size-4" />
                        {{ t('teams.show.back') }}
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

                                    <!-- Team identifier — the opaque UUID API callers reference. Click
                                         the chip OR the copy icon to put it on the clipboard. -->
                                    <TooltipProvider>
                                        <Tooltip>
                                            <TooltipTrigger as-child>
                                                <button
                                                    type="button"
                                                    class="text-muted-foreground hover:bg-muted/60 hover:text-foreground mt-2 inline-flex max-w-full items-center gap-1.5 rounded-md px-2 py-1 font-mono text-xs transition-colors"
                                                    @click="copyExternalId"
                                                >
                                                    <span class="truncate" dir="ltr">{{ team.external_id }}</span>
                                                    <component
                                                        :is="copiedExternalId ? Check : Copy"
                                                        class="size-3.5 shrink-0"
                                                        :class="copiedExternalId ? 'text-green-500' : ''"
                                                    />
                                                </button>
                                            </TooltipTrigger>
                                            <TooltipContent side="bottom">
                                                {{ copiedExternalId ? t('teams.show.copied') : t('teams.show.copy_team_id') }}
                                            </TooltipContent>
                                        </Tooltip>
                                    </TooltipProvider>
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
                        <div v-if="displayableRoles.length === 0" class="text-muted-foreground py-8 text-center">
                            <p>{{ t('teams.show.roles.empty') }}</p>
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
                                        <div class="flex flex-wrap items-center gap-1.5">
                                            <span class="text-base font-semibold">{{ role.name }}</span>
                                            <TooltipProvider :delay-duration="0">
                                                <Tooltip>
                                                    <TooltipTrigger as-child>
                                                        <button
                                                            type="button"
                                                            class="bg-muted text-muted-foreground hover:bg-muted/80 hover:text-foreground inline-flex items-center gap-1 rounded-md px-1.5 py-0.5 font-mono text-[11px] transition-colors"
                                                            @click="copyRoleExternalId(role)"
                                                        >
                                                            <span class="max-w-[140px] truncate">{{ role.external_id }}</span>
                                                            <Check v-if="copiedRoleIds.has(role.id)" class="size-3 text-green-600" />
                                                            <Copy v-else class="size-3" />
                                                        </button>
                                                    </TooltipTrigger>
                                                    <TooltipContent>
                                                        <p>
                                                            {{
                                                                copiedRoleIds.has(role.id)
                                                                    ? t('teams.show.copied')
                                                                    : t('teams.show.roles.copy_role_id')
                                                            }}
                                                        </p>
                                                    </TooltipContent>
                                                </Tooltip>
                                            </TooltipProvider>
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
                                                    <p>{{ t('teams.show.add_member') }}</p>
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
                                                            class="bg-primary text-primary-foreground absolute -end-1 -top-1 flex size-4 items-center justify-center rounded-full text-[10px]"
                                                        >
                                                            {{ getRoleMemberCount(role.code) }}
                                                        </span>
                                                    </Button>
                                                </TooltipTrigger>
                                                <TooltipContent>
                                                    <p>{{ t('teams.show.view_members', { count: getRoleMemberCount(role.code) }) }}</p>
                                                </TooltipContent>
                                            </Tooltip>
                                        </TooltipProvider>
                                        <TooltipProvider v-if="isOwner" :delay-duration="0">
                                            <Tooltip>
                                                <TooltipTrigger as-child>
                                                    <Button variant="ghost" size="icon" class="size-8" as-child>
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
                                                        @click="roleToDelete = role"
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

                                <div v-if="expandedRoles.has(role.id)">
                                    <!-- Agent Access -->
                                    <div class="mb-3 flex items-center gap-2 border-b pb-3 text-sm">
                                        <Bot class="text-muted-foreground size-4" />
                                        <span class="font-medium">{{ t('teams.show.roles.agent_access_label') }}</span>
                                        <span v-if="role.all_agents" class="text-green-600">{{ t('teams.show.roles.all_agents') }}</span>
                                        <span v-else-if="role.agent_ids?.length === 0" class="text-muted-foreground">{{
                                            t('teams.show.roles.no_agents_assigned')
                                        }}</span>
                                        <span v-else class="text-foreground">
                                            {{ t('teams.show.roles.agent_count', { count: role.agent_ids?.length }, role.agent_ids?.length ?? 0) }}
                                            <span class="text-muted-foreground">
                                                ({{
                                                    agents
                                                        .filter((a) => role.agent_ids?.includes(a.external_id))
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
                            {{ t('teams.show.members.title') }}
                        </CardTitle>
                        <CardDescription>{{ t('teams.show.members.description') }}</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>{{ t('teams.show.members.table.member') }}</TableHead>
                                    <TableHead>{{ t('teams.show.role') }}</TableHead>
                                    <TableHead class="text-end">{{ t('teams.show.actions') }}</TableHead>
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
                                                    <Badge v-if="member.id === currentUser.id" variant="outline" class="text-xs">{{
                                                        t('teams.show.members.you')
                                                    }}</Badge>
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
                                    <TableCell class="text-end">
                                        <DropdownMenu v-if="canManageMembers && !member.is_owner && member.id !== currentUser.id">
                                            <DropdownMenuTrigger as-child>
                                                <Button variant="ghost" size="icon" class="size-8">
                                                    <MoreHorizontal class="size-4" />
                                                </Button>
                                            </DropdownMenuTrigger>
                                            <DropdownMenuContent align="end">
                                                <DropdownMenuItem @click="openEditMemberDialog(member)">
                                                    <Edit class="me-2 size-4" />
                                                    {{ t('teams.show.members.change_role') }}
                                                </DropdownMenuItem>
                                                <DropdownMenuSeparator />
                                                <DropdownMenuItem class="text-destructive focus:text-destructive" @click="memberToRemove = member">
                                                    <Trash2 class="me-2 size-4" />
                                                    {{ t('teams.show.members.remove') }}
                                                </DropdownMenuItem>
                                                <DropdownMenuItem
                                                    class="text-destructive focus:text-destructive"
                                                    @click="memberToDeleteFromWorkspace = member"
                                                >
                                                    <UserX class="me-2 size-4" />
                                                    {{ t('teams.show.members.delete_from_workspace') }}
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
                            {{ t('teams.show.invitations.title') }}
                        </CardTitle>
                        <CardDescription>{{ t('teams.show.invitations.description') }}</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>{{ t('teams.show.invitations.table.email') }}</TableHead>
                                    <TableHead>{{ t('teams.show.role') }}</TableHead>
                                    <TableHead>{{ t('teams.show.invitations.table.sent') }}</TableHead>
                                    <TableHead class="text-end">{{ t('teams.show.actions') }}</TableHead>
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
                                    <TableCell class="text-end">
                                        <div class="flex items-center justify-end gap-1">
                                            <Button
                                                v-if="canManageMembers"
                                                variant="ghost"
                                                size="sm"
                                                @click="resendInvitation(invitation.id)"
                                                :disabled="resendInvitationForm.processing"
                                            >
                                                <Loader2 v-if="resendingInvitationId === invitation.id" class="me-1 size-4 animate-spin" />
                                                <Send v-else class="me-1 size-4" />
                                                {{ t('teams.show.invitations.resend') }}
                                            </Button>
                                            <Button
                                                v-if="canManageMembers"
                                                variant="ghost"
                                                size="sm"
                                                class="text-destructive hover:text-destructive"
                                                @click="cancelInvitation(invitation.id)"
                                                :disabled="cancelInvitationForm.processing"
                                            >
                                                <X class="me-1 size-4" />
                                                {{ t('teams.common.cancel') }}
                                            </Button>
                                        </div>
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
                        <DialogTitle>{{ t('teams.show.invite.title') }}</DialogTitle>
                        <DialogDescription>{{ t('teams.show.invite.description') }}</DialogDescription>
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
                                    <Users class="me-2 size-4" />
                                    {{ t('teams.show.invite.select_existing') }}
                                </Button>
                                <Button
                                    type="button"
                                    :variant="inviteType === 'email' ? 'default' : 'ghost'"
                                    size="sm"
                                    @click="inviteType = 'email'"
                                    class="flex-1"
                                >
                                    <Mail class="me-2 size-4" />
                                    {{ t('teams.show.invite.invite_by_email') }}
                                </Button>
                            </div>

                            <!-- Existing users dropdown -->
                            <div v-if="inviteType === 'existing' && managedUsers.length > 0" class="grid gap-2">
                                <Label>{{ t('teams.show.invite.select_user_label') }}</Label>
                                <Select v-model="inviteForm.user_id">
                                    <SelectTrigger>
                                        <SelectValue :placeholder="t('teams.show.invite.select_user_placeholder')" />
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
                                <Label for="invite-email">{{ t('teams.show.invite.email_label') }}</Label>
                                <div class="relative">
                                    <div class="text-muted-foreground pointer-events-none absolute inset-y-0 start-0 flex items-center ps-3">
                                        <Mail class="size-4" />
                                    </div>
                                    <Input
                                        id="invite-email"
                                        v-model="inviteForm.email"
                                        type="email"
                                        :placeholder="t('teams.show.invite.email_placeholder')"
                                        :required="inviteType === 'email'"
                                        class="ps-9"
                                    />
                                </div>
                                <InputError :message="inviteForm.errors.email" />
                            </div>

                            <!-- Role display -->
                            <div class="grid gap-2">
                                <Label>{{ t('teams.show.role') }}</Label>
                                <Badge :variant="getRoleBadgeVariant(inviteForm.role)" class="w-fit">
                                    {{ roles.find((r) => r.code === inviteForm.role)?.name || inviteForm.role }}
                                </Badge>
                            </div>
                        </div>
                        <DialogFooter>
                            <Button type="button" variant="outline" @click="isInviteDialogOpen = false">
                                <X class="me-2 size-4" />
                                {{ t('teams.common.cancel') }}
                            </Button>
                            <Button type="submit" :disabled="inviteForm.processing || (inviteType === 'existing' && !inviteForm.user_id)">
                                <Loader2 v-if="inviteForm.processing" class="me-2 size-4 animate-spin" />
                                <component :is="inviteType === 'existing' ? UserPlus : Send" v-else class="me-2 size-4" />
                                {{ inviteType === 'existing' ? t('teams.show.add_member') : t('teams.show.invite.send_invitation') }}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            <!-- Role Members Dialog -->
            <Dialog v-model:open="isRoleMembersDialogOpen">
                <DialogContent class="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle v-if="viewingRoleMembers">{{
                            t('teams.show.role_members.title', { name: viewingRoleMembers.name })
                        }}</DialogTitle>
                        <DialogDescription>{{ t('teams.show.role_members.description') }}</DialogDescription>
                    </DialogHeader>
                    <div v-if="viewingRoleMembers" class="py-4">
                        <div v-if="getRoleMembers(viewingRoleMembers.code).length === 0" class="text-muted-foreground py-8 text-center">
                            <p>{{ t('teams.show.role_members.empty') }}</p>
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
                        <Button type="button" variant="outline" @click="isRoleMembersDialogOpen = false">{{
                            t('teams.show.role_members.close')
                        }}</Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <!-- Edit Member Role Dialog -->
            <Dialog :open="!!memberToEdit" @update:open="(open) => !open && (memberToEdit = null)">
                <DialogContent class="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>{{ t('teams.show.members.change_role_title') }}</DialogTitle>
                        <DialogDescription>{{ t('teams.show.members.update_role_description', { name: memberToEdit?.name }) }}</DialogDescription>
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
                                        <SelectItem v-for="role in selectableRoles" :key="role.code" :value="role.code">
                                            {{ role.name }}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                                <InputError :message="updateMemberForm.errors.role" />
                            </div>
                        </div>
                        <DialogFooter>
                            <Button type="button" variant="outline" @click="memberToEdit = null">{{ t('teams.common.cancel') }}</Button>
                            <Button type="submit" :disabled="updateMemberForm.processing">
                                <Loader2 v-if="updateMemberForm.processing" class="me-2 size-4 animate-spin" />
                                {{ t('teams.show.members.update_role') }}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            <!-- Edit Role now uses dedicated page at /my/teams/{team}/roles/{roleId}/edit -->

            <!-- Remove Member Confirmation -->
            <AlertDialog :open="!!memberToRemove" @update:open="(open) => !open && (memberToRemove = null)">
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>{{ t('teams.show.members.remove_title') }}</AlertDialogTitle>
                        <AlertDialogDescription>
                            {{ t('teams.show.members.remove_description', { name: memberToRemove?.name }) }}
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel>{{ t('teams.common.cancel') }}</AlertDialogCancel>
                        <Button variant="destructive" @click="removeMember" :disabled="removeMemberForm.processing">
                            <Loader2 v-if="removeMemberForm.processing" class="me-2 size-4 animate-spin" />
                            {{ t('teams.show.members.remove_confirm') }}
                        </Button>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>

            <!-- Delete Teammate From Workspace Confirmation -->
            <AlertDialog :open="!!memberToDeleteFromWorkspace" @update:open="(open) => !open && (memberToDeleteFromWorkspace = null)">
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>{{ t('teams.show.members.delete_from_workspace_title') }}</AlertDialogTitle>
                        <AlertDialogDescription>
                            {{ t('teams.show.members.delete_from_workspace_description', { name: memberToDeleteFromWorkspace?.name }) }}
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel>{{ t('teams.common.cancel') }}</AlertDialogCancel>
                        <Button variant="destructive" @click="removeFromWorkspace" :disabled="removeFromWorkspaceForm.processing">
                            <Loader2 v-if="removeFromWorkspaceForm.processing" class="me-2 size-4 animate-spin" />
                            {{ t('teams.show.members.delete_from_workspace_confirm') }}
                        </Button>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>

            <!-- Delete Role Confirmation -->
            <AlertDialog :open="!!roleToDelete" @update:open="(open) => !open && (roleToDelete = null)">
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>{{ t('teams.show.roles.delete_role') }}</AlertDialogTitle>
                        <AlertDialogDescription>
                            {{ t('teams.show.roles.delete_description', { name: roleToDelete?.name }) }}
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel>{{ t('teams.common.cancel') }}</AlertDialogCancel>
                        <Button variant="destructive" @click="deleteRole" :disabled="deleteRoleForm.processing">
                            <Loader2 v-if="deleteRoleForm.processing" class="me-2 size-4 animate-spin" />
                            {{ t('teams.show.roles.delete_role') }}
                        </Button>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </div>
    </AppLayout>
</template>
