<script lang="ts" setup>
import { ref, onMounted, computed } from 'vue';
import { useForm, Link } from '@inertiajs/vue3';
import axios from 'axios';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AgentLayout from '@/layouts/AgentLayout.vue';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle
} from '@/components/ui/dialog';
import { Users, PlusCircle, Trash2, LoaderCircle, Shield, ShieldOff, ExternalLink, Eye } from 'lucide-vue-next';
import { toast } from 'vue-sonner';

interface Props {
    agent: App.Data.Resources.Agent.AgentResourceData;
    team_collaboration?: boolean;
    members_limit?: number;
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
    email: ''
});

// Team members data
const teamMembers = ref<any[]>([]);

// Computed properties for team collaboration constraints
const canInviteMembers = computed(() => {
    return props.team_collaboration && (props.members_limit === 0 || props.members_limit === undefined || teamMembers.value.length < (props.members_limit || 0));
});

const hasReachedMemberLimit = computed(() => {
    return (props.members_limit || 0) > 0 && teamMembers.value.length >= (props.members_limit || 0);
});

const remainingMembers = computed(() => {
    if (!props.members_limit || props.members_limit === 0) return 'Unlimited';
    return Math.max(0, props.members_limit - teamMembers.value.length);
});

// Helper function to check if member has access to current agent
const memberHasAccess = (member: any) => {
    return member.agents && member.agents.hasOwnProperty(props.agent.id.toString());
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
        filtered = filtered.filter(member =>
            member.name.toLowerCase().includes(query) ||
            member.email.toLowerCase().includes(query)
        );
    }

    // Apply access filter
    if (accessFilter.value === 'has_access') {
        filtered = filtered.filter(member => memberHasAccess(member));
    } else if (accessFilter.value === 'no_access') {
        filtered = filtered.filter(member => !memberHasAccess(member));
    }

    return filtered;
});

// Fetch team members on component mount
onMounted(() => {
    fetchTeamMembers();
});

const fetchTeamMembers = async () => {
    try {
        const response = await axios.get(route('agents.team.list', props.agent.id));
        teamMembers.value = response.data;
    } catch (error) {
        console.error('Failed to fetch team members:', error);
    }
};

const sendInvitation = () => {
    isSubmitting.value = true;

    // Store values before form reset
    const invitedName = invitationForm.name;
    const invitedEmail = invitationForm.email;

    invitationForm.post(route('agents.team.invite', props.agent.id), {
        preserveScroll: true,
        onSuccess: () => {
            isSubmitting.value = false;
            showInviteDialog.value = false;
            invitationForm.reset();

            // Show success toast
            toast.success(`Invitation sent successfully to ${invitedEmail}`);

            // Refresh the team members list
            fetchTeamMembers();
        },
        onError: () => {
            isSubmitting.value = false;
        }
    });
};

const openInviteDialog = () => {
    if (!canInviteMembers.value) {
        if (!props.team_collaboration) {
            toast.error('Team collaboration is not available on your current plan');
        } else if (hasReachedMemberLimit.value) {
            toast.error(`You've reached your member limit of ${props.members_limit || 0} members`);
        }
        return;
    }

    showInviteDialog.value = true;
    invitationForm.clearErrors();
};

const openDeleteDialog = (member: any) => {
    selectedMember.value = member;
    showDeleteDialog.value = true;
};

const removeFromAgent = async () => {
    if (!selectedMember.value) return;

    try {
        await axios.delete(route('agents.team.member.delete', {
            agent: props.agent,
            member: selectedMember.value.id,
            complete: 0,
        }));

        toast.success(`${selectedMember.value.name} has been removed from this agent`);
        fetchTeamMembers(); // Refresh the list
    } catch (error) {
        toast.error('Failed to remove member from agent');
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
        await axios.delete(route('agents.team.member.delete', {
            agent: props.agent,
            member: selectedMember.value.id,
            complete: 1,
        }));

        toast.success(`${selectedMember.value.name} has been deleted entirely`);
        fetchTeamMembers(); // Refresh the list
    } catch (error) {
        toast.error('Failed to delete member entirely');
        console.error('Error deleting member entirely:', error);
    } finally {
        showDeleteDialog.value = false;
        selectedMember.value = null;
    }
};

const revokeAccess = async (member: any) => {
    try {
        await axios.delete(route('agents.team.member.delete', {
            agent: props.agent,
            member: member.id,
            complete: 0,
        }));

        toast.success(`Access revoked for ${member.name}`);
        fetchTeamMembers(); // Refresh the list
    } catch (error) {
        toast.error('Failed to revoke access');
        console.error('Error revoking access:', error);
    }
};

const grantAccess = async (member: any) => {
    try {
        await axios.post(route('agents.team.member.grant', {
            agent: props.agent.id,
            member: member.id,
        }));

        toast.success(`Access granted to ${member.name}`);
        fetchTeamMembers(); // Refresh the list
    } catch (error) {
        toast.error('Failed to grant access');
        console.error('Error granting access:', error);
    }
};

const deleteMember = async (member: any) => {
    try {
        await axios.delete(route('agents.team.member.delete', {
            agent: props.agent,
            member: member.id,
            complete: 1,
        }));

        toast.success(`${member.name} has been deleted entirely`);
        fetchTeamMembers(); // Refresh the list
    } catch (error) {
        toast.error('Failed to delete member');
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
    <AgentLayout :agent="agent" title="Team Management">
        <div class="space-y-6">
            <div class="flex justify-center">
                <Card class="w-full max-w-6xl">
            <CardHeader>
                <CardTitle class="flex items-center gap-2">
                    <Users class="h-5 w-5" />
                    Members
                </CardTitle>
                <CardDescription>
                    Manage team members and their agent access
                    <span v-if="(members_limit || 0) > 0" class="block mt-2 text-muted-foreground">
                        {{ teamMembers.length }} of {{ members_limit || 0 }} members used ({{ remainingMembers }} remaining)
                    </span>
                </CardDescription>
            </CardHeader>
            <CardContent>
                <div class="space-y-6">
                    <!-- Team Collaboration Disabled Warning -->
                    <div v-if="!team_collaboration" class="p-4 rounded-lg border border-amber-200 bg-amber-50 dark:border-amber-800 dark:bg-amber-950/50">
                        <div class="flex items-start gap-3">
                            <div class="flex-shrink-0">
                                <ShieldOff class="h-5 w-5 text-amber-600 dark:text-amber-400" />
                            </div>
                            <div>
                                <h3 class="text-sm font-medium text-amber-800 dark:text-amber-200">
                                    Team Collaboration Disabled
                                </h3>
                                <p class="mt-1 text-sm text-amber-700 dark:text-amber-300">
                                    Your current plan doesn't include team collaboration features.
                                    <Link :href="route('billing.packages')" class="font-medium underline hover:no-underline">
                                        Upgrade your plan
                                    </Link>
                                    to invite team members and collaborate on agents.
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Member Limit Reached Warning -->
                    <div v-else-if="hasReachedMemberLimit" class="p-4 rounded-lg border border-orange-200 bg-orange-50 dark:border-orange-800 dark:bg-orange-950/50">
                        <div class="flex items-start gap-3">
                            <div class="flex-shrink-0">
                                <Users class="h-5 w-5 text-orange-600 dark:text-orange-400" />
                            </div>
                            <div>
                                <h3 class="text-sm font-medium text-orange-800 dark:text-orange-200">
                                    Member Limit Reached
                                </h3>
                                <p class="mt-1 text-sm text-orange-700 dark:text-orange-300">
                                    You've reached your member limit of {{ members_limit || 0 }} members.
                                    <a href="/billing/plans" class="font-medium underline hover:no-underline">
                                        Upgrade your plan
                                    </a>
                                    to add more team members.
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Search and Filter Section -->
                    <div class="flex flex-col sm:flex-row gap-4 justify-between items-start sm:items-center">
                        <div class="flex flex-col sm:flex-row gap-3 w-full sm:w-auto">
                            <!-- Search Input -->
                            <div class="relative">
                                <Input
                                    v-model="searchQuery"
                                    placeholder="Search members..."
                                    class="w-full sm:w-64"
                                />
                            </div>

                            <!-- Access Filter -->
                            <Select v-model="accessFilter">
                                <SelectTrigger class="w-full sm:w-48">
                                    <SelectValue placeholder="Filter by access" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">All Members</SelectItem>
                                    <SelectItem value="has_access">Has Access</SelectItem>
                                    <SelectItem value="no_access">No Access</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        <Button
                            @click="openInviteDialog"
                            variant="outline"
                            class="gap-2 w-full sm:w-auto"
                            :disabled="!canInviteMembers"
                            :title="!team_collaboration ? 'Team collaboration is not available on your current plan' : hasReachedMemberLimit ? `You've reached your member limit of ${members_limit || 0} members` : ''"
                        >
                            <PlusCircle class="h-4 w-4" />
                            Invite Member
                        </Button>
                    </div>

                    <!-- Results Counter -->
                    <div v-if="teamMembers.length > 0" class="flex justify-between items-center text-sm text-muted-foreground">
                        <span>
                            Showing {{ filteredMembers.length }} of {{ teamMembers.length }} members
                            <span v-if="searchQuery.trim() || accessFilter !== 'all'">
                                (filtered)
                            </span>
                        </span>
                    </div>

                    <!-- Members list -->
                    <div class="rounded-md border">
                        <div v-if="teamMembers.length === 0" class="p-6 text-center text-muted-foreground">
                            No team members yet. Invite someone to get started.
                        </div>
                        <div v-else-if="filteredMembers.length === 0" class="p-6 text-center text-muted-foreground">
                            <div class="space-y-2">
                                <p>No members found matching your criteria.</p>
                                <p class="text-xs">Try adjusting your search or filter settings.</p>
                            </div>
                        </div>
                        <div v-else class="divide-y">
                            <div
                                v-for="member in filteredMembers"
                                :key="member.id"
                                class="flex items-center justify-between p-4"
                            >
                                <div class="flex-1">
                                    <div class="flex items-center gap-3">
                                        <div>
                                            <div class="font-medium">{{ member.name }}</div>
                                            <div class="text-sm text-muted-foreground">{{ member.email }}</div>
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <!-- Status badge -->
                                            <span
                                                class="text-xs px-2 py-0.5 rounded-full"
                                                :class="{
                                                    'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400': member.email_verified_at,
                                                    'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400': !member.email_verified_at
                                                }"
                                            >
                                                {{ member.email_verified_at ? 'Active' : 'Pending' }}
                                            </span>
                                            <!-- Access indicator -->
                                            <span
                                                class="text-xs px-2 py-0.5 rounded-full flex items-center gap-1"
                                                :class="{
                                                    'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400': memberHasAccess(member),
                                                    'bg-gray-100 text-gray-700 dark:bg-gray-900/30 dark:text-gray-400': !memberHasAccess(member)
                                                }"
                                            >
                                                <Shield v-if="memberHasAccess(member)" class="h-3 w-3" />
                                                <ShieldOff v-else class="h-3 w-3" />
                                                {{ memberHasAccess(member) ? 'Has Access' : 'No Access' }}
                                            </span>
                                            <!-- Agent access link -->
                                            <Button
                                                @click="showAgentAccess(member)"
                                                variant="ghost"
                                                size="sm"
                                                class="text-blue-600 hover:text-blue-700 p-1"
                                                title="View agent access"
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
                                        <ShieldOff class="h-4 w-4 mr-1" />
                                        Revoke
                                    </Button>
                                    <!-- Grant access button (only if member does not have access AND has verified their account) -->
                                    <Button
                                        v-else-if="member.email_verified_at"
                                        @click="grantAccess(member)"
                                        variant="outline"
                                        size="sm"
                                        class="text-green-600 hover:text-green-700"
                                    >
                                        <Shield class="h-4 w-4 mr-1" />
                                        Grant Access
                                    </Button>
                                    <!-- Delete member button -->
                                    <Button
                                        @click="confirmDeleteMember(member)"
                                        variant="outline"
                                        size="sm"
                                        class="text-destructive hover:text-destructive"
                                    >
                                        <Trash2 class="h-4 w-4 mr-1" />
                                        Delete
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
                    <DialogTitle>Invite a Member</DialogTitle>
                    <DialogDescription>
                        Send an invitation to collaborate on this agent.
                        <span v-if="members_limit && members_limit > 0" class="block mt-1 text-sm text-muted-foreground">
                            {{ teamMembers.length }} of {{ members_limit }} members used ({{ remainingMembers }} remaining)
                        </span>
                    </DialogDescription>
                </DialogHeader>
                <div class="space-y-4 py-4">
                    <div class="grid gap-2">
                        <Label for="name" required>Name</Label>
                        <Input
                            id="name"
                            v-model="invitationForm.name"
                            type="text"
                            placeholder="John Doe"
                            :error="invitationForm.errors.name"
                        />
                        <p v-if="invitationForm.errors.name" class="text-sm text-destructive">
                            {{ invitationForm.errors.name }}
                        </p>
                    </div>

                    <div class="grid gap-2">
                        <Label for="email" required>Email Address</Label>
                        <Input
                            id="email"
                            v-model="invitationForm.email"
                            type="email"
                            placeholder="colleague@example.com"
                            :error="invitationForm.errors.email"
                        />
                        <p v-if="invitationForm.errors.email" class="text-sm text-destructive">
                            {{ invitationForm.errors.email }}
                        </p>
                    </div>

                    <!-- Display any other errors that aren't field-specific -->
                    <div v-for="(error, key) in invitationForm.errors" :key="key">
                        <p v-if="!['name', 'email'].includes(key)" class="text-sm text-destructive">
                            {{ error }}
                        </p>
                    </div>
                </div>
                <DialogFooter>
                    <Button variant="outline" @click="showInviteDialog = false">
                        Cancel
                    </Button>
                    <Button
                        :disabled="!invitationForm.name || !invitationForm.email || isSubmitting || !canInviteMembers"
                        @click="sendInvitation"
                    >
                        <LoaderCircle v-if="isSubmitting" class="mr-2 h-4 w-4 animate-spin" />
                        {{ isSubmitting ? 'Sending...' : 'Send Invitation' }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Agent Access Dialog -->
        <Dialog v-model:open="showAgentAccessDialog">
            <DialogContent class="max-w-2xl">
                <DialogHeader>
                    <DialogTitle>Agent Access Details</DialogTitle>
                    <DialogDescription>
                        View all agents that <strong>{{ selectedMember?.name }}</strong> has access to
                    </DialogDescription>
                </DialogHeader>
                <div class="space-y-4 py-4">
                    <div v-if="selectedMember && getMemberAgentAccess(selectedMember).length === 0" class="p-6 text-center text-muted-foreground">
                        This member doesn't have access to any agents yet.
                    </div>
                    <div v-else class="space-y-3">
                        <div
                            v-for="agentAccess in getMemberAgentAccess(selectedMember)"
                            :key="agentAccess.id"
                            class="flex items-center justify-between p-3 rounded-lg border bg-muted/50"
                        >
                            <div class="flex-1">
                                <div class="flex items-center gap-2">
                                    <h4 class="font-medium text-sm">Agent ID: {{ agentAccess.id }}</h4>
                                    <span
                                        v-if="agentAccess.id === props.agent.id.toString()"
                                        class="text-xs px-2 py-0.5 rounded-full bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400"
                                    >
                                        Current Agent
                                    </span>
                                </div>
                                <p class="text-xs text-muted-foreground mt-1">
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
                    <Button variant="outline" @click="showAgentAccessDialog = false">
                        Close
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Delete Confirmation Dialog -->
        <Dialog v-model:open="showDeleteDialog">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Delete Member Permanently</DialogTitle>
                    <DialogDescription>
                        Are you sure you want to permanently delete <strong>{{ selectedMember?.name }}</strong>? This action cannot be undone and will remove them from the entire system and all agents they have access to.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <Button variant="outline" @click="cancelDelete">Cancel</Button>
                    <Button variant="destructive" @click="deleteMemberEntirely">
                        <Trash2 class="h-4 w-4 mr-2" />
                        Delete Permanently
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AgentLayout>
</template>
