<script lang="ts" setup>
import { ref, onMounted } from 'vue';
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
import { Users, PlusCircle, Trash2, LoaderCircle } from 'lucide-vue-next';
import { toast } from 'vue-sonner';

interface Props {
    agent: any
}

const props = defineProps<Props>();

// States
const showInviteDialog = ref(false);
const showDeleteDialog = ref(false);
const isSubmitting = ref(false);
const selectedMember = ref<any>(null);

// Form for invitations
const invitationForm = useForm({
    name: '',
    email: ''
});

// Team members data
const teamMembers = ref<any[]>([]);

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

const cancelDelete = () => {
    showDeleteDialog.value = false;
    selectedMember.value = null;
};
</script>

<template>
    <AgentLayout :agent="agent" title="Team Management">
        <div>




        <Card>
            <CardHeader>
                <CardTitle class="flex items-center gap-2">
                    <Users class="h-5 w-5" />
                    Agent Members
                </CardTitle>
                <CardDescription>
                    Manage who has access to this agent
                </CardDescription>
            </CardHeader>
            <CardContent>
                <div class="space-y-6">
                    <div class="flex justify-between items-center">
                        <h3 class="text-lg font-medium">Agent Members ({{ teamMembers.length }})</h3>
                        <Button @click="openInviteDialog" variant="outline" class="gap-2">
                            <PlusCircle class="h-4 w-4" />
                            Invite Member
                        </Button>
                    </div>

                    <!-- Team members list -->
                    <div class="rounded-md border">
                        <div v-if="teamMembers.length === 0" class="p-6 text-center text-muted-foreground">
                            No team members yet. Invite someone to get started.
                        </div>
                        <div v-else class="divide-y">
                            <div
                                v-for="member in teamMembers"
                                :key="member.id"
                                class="flex items-center justify-between p-4"
                            >
                                <div>
                                    <div class="font-medium">{{ member.name }}</div>
                                    <div class="text-sm text-muted-foreground">{{ member.email }}</div>
                                    <div class="flex items-center gap-2 mt-1">
                                        <span
                                            class="text-xs px-2 py-0.5 rounded-full"
                                            :class="{
                                                'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400': member.invitation_accepted_at,
                                                'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400': !member.invitation_accepted_at
                                            }"
                                        >
                                            {{ member.invitation_accepted_at ? 'Active' : 'Pending' }}
                                        </span>
                                    </div>
                                </div>
                                <Button
                                    @click="openDeleteDialog(member)"
                                    variant="ghost"
                                    size="sm"
                                    class="text-destructive hover:text-destructive hover:bg-destructive/10"
                                >
                                    <Trash2 class="h-4 w-4" />
                                </Button>
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

        <!-- Delete Member Confirmation Dialog -->
        <Dialog v-model:open="showDeleteDialog">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Remove Team Member</DialogTitle>
                    <DialogDescription>
                        What would you like to do with <strong>{{ selectedMember?.name }}</strong>?
                    </DialogDescription>
                </DialogHeader>
                <div class="space-y-4 py-4">
                    <div class="space-y-3">
                        <div class="flex items-start space-x-3 p-3 rounded-lg border bg-muted/50">
                            <div class="flex-1">
                                <h4 class="font-medium text-sm">Remove from this agent only</h4>
                                <p class="text-xs text-muted-foreground mt-1">
                                    The member will be removed from this agent but can still access other agents they're part of.
                                </p>
                            </div>
                        </div>

                        <div class="flex items-start space-x-3 p-3 rounded-lg border bg-destructive/5 border-destructive/20">
                            <div class="flex-1">
                                <h4 class="font-medium text-sm text-destructive">Delete member entirely</h4>
                                <p class="text-xs text-muted-foreground mt-1">
                                    The member will be completely removed from the system and lose access to all agents.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
                <DialogFooter class="flex-col sm:flex-row gap-2">
                    <Button
                        variant="outline"
                        @click="cancelDelete"
                        class="w-full sm:w-auto"
                    >
                        Cancel
                    </Button>
                    <Button
                        variant="outline"
                        @click="removeFromAgent"
                        class="w-full sm:w-auto"
                    >
                        Remove from Agent
                    </Button>
                    <Button
                        variant="destructive"
                        @click="deleteMemberEntirely"
                        class="w-full sm:w-auto"
                    >
                        Delete Entirely
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
    </AgentLayout>
</template>
