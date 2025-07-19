<script lang="ts" setup>
import { ref, onMounted, computed } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
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
    agent: any
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
            <Card>
            <CardHeader>
                <CardTitle class="flex items-center gap-2">
                    <Users class="h-5 w-5" />
                    Members
                </CardTitle>
                <CardDescription>
                    Manage team members and their agent access
                </CardDescription>
            </CardHeader>
            <CardContent>
                <div class="space-y-6">
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
                        
                        <Button @click="openInviteDialog" variant="outline" class="gap-2 w-full sm:w-auto">
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
                                                    'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400': member.invitation_accepted_at,
                                                    'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400': !member.invitation_accepted_at
                                                }"
                                            >
                                                {{ member.invitation_accepted_at ? 'Active' : 'Pending' }}
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
                                        v-else-if="member.invitation_accepted_at"
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



        <!-- Invite Team Member Dialog -->
        <Dialog v-model:open="showInviteDialog">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Invite a Member</DialogTitle>
                    <DialogDescription>
                        Send an invitation to collaborate on this agent.
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
                        :disabled="!invitationForm.name || !invitationForm.email || isSubmitting"
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
    </div>
    </AgentLayout>
</template>
