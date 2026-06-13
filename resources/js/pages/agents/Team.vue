<script lang="ts" setup>
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AgentLayout from '@/layouts/AgentLayout.vue';
import { Link, useForm } from '@inertiajs/vue3';
import axios from 'axios';
import { ExternalLink, Eye, LoaderCircle, PlusCircle, Shield, ShieldOff, Trash2, Users } from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { toast } from 'vue-sonner';

const { t } = useI18n();

interface Props {
    agent: Domain.Agent.Data.Resources.Account.AgentResourceData;
    team_collaboration?: boolean;
    workspace_users_limit?: number;
    workspace_users_count?: number;
}

const props = defineProps<Props>();

// States
const showInviteDialog = ref(false);
const showDeleteDialog = ref(false);
const showAgentAccessDialog = ref(false);
const isSubmitting = ref(false);
const selectedMember = ref<any>(null);

// Search and filter states
const searchQuery = ref('');
const accessFilter = ref('all'); // 'all', 'has_access', 'no_access'

// Form for invitations
const invitationForm = useForm({
    name: '',
    email: '',
});

// Team members data
const teamMembers = ref<any[]>([]);

// Workspace users = team members + chatters; they share one quota bucket.
const workspaceUsersCount = computed(() => props.workspace_users_count ?? 0);
const workspaceUsersLimit = computed(() => props.workspace_users_limit ?? 0);

const canInviteMembers = computed(() => {
    return props.team_collaboration && (workspaceUsersLimit.value === 0 || workspaceUsersCount.value < workspaceUsersLimit.value);
});

const hasReachedMemberLimit = computed(() => {
    return workspaceUsersLimit.value > 0 && workspaceUsersCount.value >= workspaceUsersLimit.value;
});

const remainingMembers = computed(() => {
    if (workspaceUsersLimit.value === 0) return t('agents.team.unlimited');
    return Math.max(0, workspaceUsersLimit.value - workspaceUsersCount.value);
});

// Helper function to check if member has access to current agent
const memberHasAccess = (member: any) => {
    return member.agents && member.agents.hasOwnProperty(props.agent.external_id.toString());
};

// Helper function to get agent access details
const getMemberAgentAccess = (member: any) => {
    if (!member.agents) return [];
    return Object.entries(member.agents).map(([id, url]) => ({ id, url }));
};

// Computed property to filter and search members
const filteredMembers = computed(() => {
    let filtered = teamMembers.value;

    // Apply search filter
    if (searchQuery.value.trim()) {
        const query = searchQuery.value.toLowerCase();
        filtered = filtered.filter((member) => member.name.toLowerCase().includes(query) || member.email.toLowerCase().includes(query));
    }

    // Apply access filter
    if (accessFilter.value === 'has_access') {
        filtered = filtered.filter((member) => memberHasAccess(member));
    } else if (accessFilter.value === 'no_access') {
        filtered = filtered.filter((member) => !memberHasAccess(member));
    }

    return filtered;
});

// Fetch team members on component mount
onMounted(() => {
    fetchTeamMembers();
});

const fetchTeamMembers = async () => {
    try {
        const response = await axios.get(route('agents.team.list', props.agent.external_id));
        teamMembers.value = response.data;
    } catch (error) {
        console.error('Failed to fetch team members:', error);
    }
};

const sendInvitation = () => {
    isSubmitting.value = true;

    // Store values before form reset
    const _invitedName = invitationForm.name;
    const invitedEmail = invitationForm.email;

    invitationForm.post(route('agents.team.invite', props.agent.external_id), {
        preserveScroll: true,
        onSuccess: () => {
            isSubmitting.value = false;
            showInviteDialog.value = false;
            invitationForm.reset();

            // Show success toast
            toast.success(t('agents.team.invitation_sent', { email: invitedEmail }));

            // Refresh the team members list
            fetchTeamMembers();
        },
        onError: () => {
            isSubmitting.value = false;
        },
    });
};

const openInviteDialog = () => {
    if (!canInviteMembers.value) {
        if (!props.team_collaboration) {
            toast.error(t('agents.team.collaboration_error'));
        } else if (hasReachedMemberLimit.value) {
            toast.error(t('agents.team.limit_error', { limit: workspaceUsersLimit.value }));
        }
        return;
    }

    showInviteDialog.value = true;
    invitationForm.clearErrors();
};

const _openDeleteDialog = (member: any) => {
    selectedMember.value = member;
    showDeleteDialog.value = true;
};

const _removeFromAgent = async () => {
    if (!selectedMember.value) return;

    try {
        await axios.delete(
            route('agents.team.member.delete', {
                agent: props.agent,
                member: selectedMember.value.id,
                complete: 0,
            }),
        );

        toast.success(t('agents.team.removed_from_agent', { name: selectedMember.value.name }));
        fetchTeamMembers(); // Refresh the list
    } catch (error) {
        toast.error(t('agents.team.remove_error'));
        console.error('Error removing member from agent:', error);
    } finally {
        showDeleteDialog.value = false;
        selectedMember.value = null;
    }
};

const confirmDeleteMember = (member: any) => {
    selectedMember.value = member;
    showDeleteDialog.value = true;
};

const deleteMemberEntirely = async () => {
    if (!selectedMember.value) return;

    try {
        await axios.delete(
            route('agents.team.member.delete', {
                agent: props.agent,
                member: selectedMember.value.id,
                complete: 1,
            }),
        );

        toast.success(t('agents.team.deleted_entirely', { name: selectedMember.value.name }));
        fetchTeamMembers(); // Refresh the list
    } catch (error) {
        toast.error(t('agents.team.delete_error'));
        console.error('Error deleting member entirely:', error);
    } finally {
        showDeleteDialog.value = false;
        selectedMember.value = null;
    }
};

const revokeAccess = async (member: any) => {
    try {
        await axios.delete(
            route('agents.team.member.delete', {
                agent: props.agent,
                member: member.id,
                complete: 0,
            }),
        );

        toast.success(t('agents.team.access_revoked', { name: member.name }));
        fetchTeamMembers(); // Refresh the list
    } catch (error) {
        toast.error(t('agents.team.revoke_error'));
        console.error('Error revoking access:', error);
    }
};

const grantAccess = async (member: any) => {
    try {
        await axios.post(
            route('agents.team.member.grant', {
                agent: props.agent.external_id,
                member: member.id,
            }),
        );

        toast.success(t('agents.team.access_granted', { name: member.name }));
        fetchTeamMembers(); // Refresh the list
    } catch (error) {
        toast.error(t('agents.team.grant_error'));
        console.error('Error granting access:', error);
    }
};

const _deleteMember = async (member: any) => {
    try {
        await axios.delete(
            route('agents.team.member.delete', {
                agent: props.agent,
                member: member.id,
                complete: 1,
            }),
        );

        toast.success(t('agents.team.deleted_entirely', { name: member.name }));
        fetchTeamMembers(); // Refresh the list
    } catch (error) {
        toast.error(t('agents.team.delete_error'));
        console.error('Error deleting member:', error);
    }
};

const showAgentAccess = (member: any) => {
    selectedMember.value = member;
    showAgentAccessDialog.value = true;
};

const openAgentUrl = (url: string) => {
    window.open(url, '_blank');
};

const cancelDelete = () => {
    showDeleteDialog.value = false;
    selectedMember.value = null;
};
</script>

<template>
    <AgentLayout :agent="agent" :title="t('agents.team.title')">
        <div class="space-y-6">
            <div class="flex justify-center">
                <Card class="w-full max-w-6xl">
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2">
                            <Users class="h-5 w-5" />
                            {{ t('agents.team.heading') }}
                        </CardTitle>
                        <CardDescription>
                            {{ t('agents.team.description') }}
                            <span v-if="workspaceUsersLimit > 0" class="text-muted-foreground mt-2 block">
                                {{
                                    t('agents.team.quota', {
                                        used: workspaceUsersCount,
                                        limit: workspaceUsersLimit,
                                        remaining: remainingMembers,
                                    })
                                }}
                            </span>
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div class="space-y-6">
                            <!-- Team Collaboration Disabled Warning -->
                            <div
                                v-if="!team_collaboration"
                                class="rounded-lg border border-amber-200 bg-amber-50 p-4 dark:border-amber-800 dark:bg-amber-950/50"
                            >
                                <div class="flex items-start gap-3">
                                    <div class="flex-shrink-0">
                                        <ShieldOff class="h-5 w-5 text-amber-600 dark:text-amber-400" />
                                    </div>
                                    <div>
                                        <h3 class="text-sm font-medium text-amber-800 dark:text-amber-200">
                                            {{ t('agents.team.collaboration_disabled_title') }}
                                        </h3>
                                        <p class="mt-1 text-sm text-amber-700 dark:text-amber-300">
                                            <i18n-t keypath="agents.team.collaboration_disabled_description" tag="span">
                                                <template #link>
                                                    <Link :href="route('billing.packages')" class="font-medium underline hover:no-underline">
                                                        {{ t('agents.team.upgrade_plan') }}
                                                    </Link>
                                                </template>
                                            </i18n-t>
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <!-- Member Limit Reached Warning -->
                            <div
                                v-else-if="hasReachedMemberLimit"
                                class="rounded-lg border border-orange-200 bg-orange-50 p-4 dark:border-orange-800 dark:bg-orange-950/50"
                            >
                                <div class="flex items-start gap-3">
                                    <div class="flex-shrink-0">
                                        <Users class="h-5 w-5 text-orange-600 dark:text-orange-400" />
                                    </div>
                                    <div>
                                        <h3 class="text-sm font-medium text-orange-800 dark:text-orange-200">
                                            {{ t('agents.team.limit_reached_title') }}
                                        </h3>
                                        <p class="mt-1 text-sm text-orange-700 dark:text-orange-300">
                                            <i18n-t keypath="agents.team.limit_reached_description" tag="span" :limit="workspaceUsersLimit">
                                                <template #link>
                                                    <Link :href="route('billing.packages')" class="font-medium underline hover:no-underline">
                                                        {{ t('agents.team.upgrade_plan') }}
                                                    </Link>
                                                </template>
                                            </i18n-t>
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <!-- Search and Filter Section -->
                            <div class="flex flex-col items-start justify-between gap-4 sm:flex-row sm:items-center">
                                <div class="flex w-full flex-col gap-3 sm:w-auto sm:flex-row">
                                    <!-- Search Input -->
                                    <div class="relative">
                                        <Input v-model="searchQuery" :placeholder="t('agents.team.search_placeholder')" class="w-full sm:w-64" />
                                    </div>

                                    <!-- Access Filter -->
                                    <Select v-model="accessFilter">
                                        <SelectTrigger class="w-full sm:w-48">
                                            <SelectValue :placeholder="t('agents.team.filter_placeholder')" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="all">{{ t('agents.team.filter_all') }}</SelectItem>
                                            <SelectItem value="has_access">{{ t('agents.team.filter_has_access') }}</SelectItem>
                                            <SelectItem value="no_access">{{ t('agents.team.filter_no_access') }}</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>

                                <Button
                                    @click="openInviteDialog"
                                    variant="outline"
                                    class="w-full gap-2 sm:w-auto"
                                    :disabled="!canInviteMembers"
                                    :title="
                                        !team_collaboration
                                            ? t('agents.team.collaboration_unavailable')
                                            : hasReachedMemberLimit
                                              ? t('agents.team.limit_reached_tooltip', { limit: workspaceUsersLimit })
                                              : ''
                                    "
                                >
                                    <PlusCircle class="h-4 w-4" />
                                    {{ t('agents.team.invite_member') }}
                                </Button>
                            </div>

                            <!-- Results Counter -->
                            <div v-if="teamMembers.length > 0" class="text-muted-foreground flex items-center justify-between text-sm">
                                <span>
                                    {{ t('agents.team.showing_count', { filtered: filteredMembers.length, total: teamMembers.length }) }}
                                    <span v-if="searchQuery.trim() || accessFilter !== 'all'">{{ t('agents.team.filtered') }}</span>
                                </span>
                            </div>

                            <!-- Members list -->
                            <div class="rounded-md border">
                                <div v-if="teamMembers.length === 0" class="text-muted-foreground p-6 text-center">
                                    {{ t('agents.team.empty') }}
                                </div>
                                <div v-else-if="filteredMembers.length === 0" class="text-muted-foreground p-6 text-center">
                                    <div class="space-y-2">
                                        <p>{{ t('agents.team.no_results_title') }}</p>
                                        <p class="text-xs">{{ t('agents.team.no_results_description') }}</p>
                                    </div>
                                </div>
                                <div v-else class="divide-y">
                                    <div v-for="member in filteredMembers" :key="member.id" class="flex items-center justify-between p-4">
                                        <div class="flex-1">
                                            <div class="flex items-center gap-3">
                                                <div>
                                                    <div class="font-medium">{{ member.name }}</div>
                                                    <div class="text-muted-foreground text-sm">{{ member.email }}</div>
                                                </div>
                                                <div class="flex items-center gap-2">
                                                    <!-- Status badge -->
                                                    <span
                                                        class="rounded-full px-2 py-0.5 text-xs"
                                                        :class="{
                                                            'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400':
                                                                member.email_verified_at,
                                                            'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400':
                                                                !member.email_verified_at,
                                                        }"
                                                    >
                                                        {{
                                                            member.email_verified_at
                                                                ? t('agents.team.status_active')
                                                                : t('agents.team.status_pending')
                                                        }}
                                                    </span>
                                                    <!-- Access indicator -->
                                                    <span
                                                        class="flex items-center gap-1 rounded-full px-2 py-0.5 text-xs"
                                                        :class="{
                                                            'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400':
                                                                memberHasAccess(member),
                                                            'bg-gray-100 text-gray-700 dark:bg-gray-900/30 dark:text-gray-400':
                                                                !memberHasAccess(member),
                                                        }"
                                                    >
                                                        <Shield v-if="memberHasAccess(member)" class="h-3 w-3" />
                                                        <ShieldOff v-else class="h-3 w-3" />
                                                        {{ memberHasAccess(member) ? t('agents.team.has_access') : t('agents.team.no_access') }}
                                                    </span>
                                                    <!-- Agent access link -->
                                                    <Button
                                                        @click="showAgentAccess(member)"
                                                        variant="ghost"
                                                        size="sm"
                                                        class="p-1 text-blue-600 hover:text-blue-700"
                                                        :title="t('agents.team.view_agent_access')"
                                                    >
                                                        <Eye class="h-4 w-4" />
                                                    </Button>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <!-- Revoke access button (only if member has access) -->
                                            <Button
                                                v-if="memberHasAccess(member)"
                                                @click="revokeAccess(member)"
                                                variant="outline"
                                                size="sm"
                                                class="text-orange-600 hover:text-orange-700"
                                            >
                                                <ShieldOff class="me-1 h-4 w-4" />
                                                {{ t('agents.team.revoke') }}
                                            </Button>
                                            <!-- Grant access button (only if member does not have access AND has verified their account) -->
                                            <Button
                                                v-else-if="member.email_verified_at"
                                                @click="grantAccess(member)"
                                                variant="outline"
                                                size="sm"
                                                class="text-green-600 hover:text-green-700"
                                            >
                                                <Shield class="me-1 h-4 w-4" />
                                                {{ t('agents.team.grant_access') }}
                                            </Button>
                                            <!-- Delete member button -->
                                            <Button
                                                @click="confirmDeleteMember(member)"
                                                variant="outline"
                                                size="sm"
                                                class="text-destructive hover:text-destructive"
                                            >
                                                <Trash2 class="me-1 h-4 w-4" />
                                                {{ t('agents.team.delete') }}
                                            </Button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>

        <!-- Invite Team Member Dialog -->
        <Dialog v-model:open="showInviteDialog">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{{ t('agents.team.invite_dialog_title') }}</DialogTitle>
                    <DialogDescription>
                        {{ t('agents.team.invite_dialog_description') }}
                        <span v-if="workspaceUsersLimit > 0" class="text-muted-foreground mt-1 block text-sm">
                            {{
                                t('agents.team.quota_dialog', {
                                    used: workspaceUsersCount,
                                    limit: workspaceUsersLimit,
                                    remaining: remainingMembers,
                                })
                            }}
                        </span>
                    </DialogDescription>
                </DialogHeader>
                <div class="space-y-4 py-4">
                    <div class="grid gap-2">
                        <Label for="name" required>{{ t('agents.team.name_label') }}</Label>
                        <Input
                            id="name"
                            v-model="invitationForm.name"
                            type="text"
                            :placeholder="t('agents.team.name_placeholder')"
                            :error="invitationForm.errors.name"
                        />
                        <p v-if="invitationForm.errors.name" class="text-destructive text-sm">
                            {{ invitationForm.errors.name }}
                        </p>
                    </div>

                    <div class="grid gap-2">
                        <Label for="email" required>{{ t('agents.team.email_label') }}</Label>
                        <Input
                            id="email"
                            v-model="invitationForm.email"
                            type="email"
                            :placeholder="t('agents.team.email_placeholder')"
                            :error="invitationForm.errors.email"
                        />
                        <p v-if="invitationForm.errors.email" class="text-destructive text-sm">
                            {{ invitationForm.errors.email }}
                        </p>
                    </div>

                    <!-- Display any other errors that aren't field-specific -->
                    <div v-for="(error, key) in invitationForm.errors" :key="key">
                        <p v-if="!['name', 'email'].includes(key)" class="text-destructive text-sm">
                            {{ error }}
                        </p>
                    </div>
                </div>
                <DialogFooter>
                    <Button variant="outline" @click="showInviteDialog = false">{{ t('common.cancel') }}</Button>
                    <Button :disabled="!invitationForm.name || !invitationForm.email || isSubmitting || !canInviteMembers" @click="sendInvitation">
                        <LoaderCircle v-if="isSubmitting" class="me-2 h-4 w-4 animate-spin" />
                        {{ isSubmitting ? t('agents.team.sending') : t('agents.team.send_invitation') }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Agent Access Dialog -->
        <Dialog v-model:open="showAgentAccessDialog">
            <DialogContent class="max-w-2xl">
                <DialogHeader>
                    <DialogTitle>{{ t('agents.team.access_dialog_title') }}</DialogTitle>
                    <DialogDescription>
                        {{ t('agents.team.access_dialog_description', { name: selectedMember?.name }) }}
                    </DialogDescription>
                </DialogHeader>
                <div class="space-y-4 py-4">
                    <div v-if="selectedMember && getMemberAgentAccess(selectedMember).length === 0" class="text-muted-foreground p-6 text-center">
                        {{ t('agents.team.no_agent_access') }}
                    </div>
                    <div v-else class="space-y-3">
                        <div
                            v-for="agentAccess in getMemberAgentAccess(selectedMember)"
                            :key="agentAccess.id"
                            class="bg-muted/50 flex items-center justify-between rounded-lg border p-3"
                        >
                            <div class="flex-1">
                                <div class="flex items-center gap-2">
                                    <h4 class="text-sm font-medium">{{ t('agents.team.agent_id', { id: agentAccess.id }) }}</h4>
                                    <span
                                        v-if="agentAccess.id === props.agent.external_id.toString()"
                                        class="rounded-full bg-blue-100 px-2 py-0.5 text-xs text-blue-700 dark:bg-blue-900/30 dark:text-blue-400"
                                    >
                                        {{ t('agents.team.current_agent') }}
                                    </span>
                                </div>
                                <p class="text-muted-foreground mt-1 text-xs">
                                    {{ agentAccess.url }}
                                </p>
                            </div>
                            <Button
                                variant="ghost"
                                size="sm"
                                class="text-blue-600 hover:text-blue-700"
                                @click="openAgentUrl(agentAccess.url as string)"
                            >
                                <ExternalLink class="h-4 w-4" />
                            </Button>
                        </div>
                    </div>
                </div>
                <DialogFooter>
                    <Button variant="outline" @click="showAgentAccessDialog = false">{{ t('agents.team.close') }}</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Delete Confirmation Dialog -->
        <Dialog v-model:open="showDeleteDialog">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{{ t('agents.team.delete_dialog_title') }}</DialogTitle>
                    <DialogDescription>
                        {{ t('agents.team.delete_dialog_description', { name: selectedMember?.name }) }}
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <Button variant="outline" @click="cancelDelete">{{ t('common.cancel') }}</Button>
                    <Button variant="destructive" @click="deleteMemberEntirely">
                        <Trash2 class="me-2 h-4 w-4" />
                        {{ t('agents.team.delete_permanently') }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AgentLayout>
</template>
