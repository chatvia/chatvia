<script lang="ts" setup>
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
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

interface Props {
    agent: any
}

const props = defineProps<Props>();

// States
const showInviteDialog = ref(false);
const isSubmitting = ref(false);

// Form for invitations
const invitationForm = useForm({
    email: '',
    role: 'viewer'
});

// Mock data for team members
const teamMembers = ref([
    { id: 1, email: 'john@example.com', role: 'admin', status: 'active' },
    { id: 2, email: 'sarah@example.com', role: 'editor', status: 'pending' }
]);

const sendInvitation = () => {
    invitationForm.post(route('agents.invitations.store', props.agent.id), {
        preserveScroll: true,
        onSuccess: () => {
            showInviteDialog.value = false;
            invitationForm.reset();
            // In a real app, you would update the team members list
            teamMembers.value.push({
                id: Date.now(),
                email: invitationForm.email,
                role: invitationForm.role,
                status: 'pending'
            });
        }
    });
};

const removeTeamMember = (id) => {
    // In a real app, you would call an API to remove the team member
    teamMembers.value = teamMembers.value.filter(member => member.id !== id);
};
</script>

<template>
    <AgentLayout :agent="agent" title="Team Management">
        <<div>
        <Card>
            <CardHeader>
                <CardTitle class="flex items-center gap-2">
                    <Users class="h-5 w-5" />
                    Team Members
                </CardTitle>
                <CardDescription>
                    Manage who has access to this agent
                </CardDescription>
            </CardHeader>
            <CardContent>
                <div class="space-y-6">
                    <div class="flex justify-between items-center">
                        <h3 class="text-lg font-medium">Team Members ({{ teamMembers.length }})</h3>
                        <Button @click="showInviteDialog = true" variant="outline" class="gap-2">
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
                                    <div class="font-medium">{{ member.email }}</div>
                                    <div class="flex items-center gap-2 mt-1">
                                        <span class="text-xs capitalize px-2 py-0.5 rounded-full bg-primary/10 text-primary">
                                            {{ member.role }}
                                        </span>
                                        <span
                                            class="text-xs px-2 py-0.5 rounded-full"
                                            :class="{
                                                'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400': member.status === 'active',
                                                'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400': member.status === 'pending'
                                            }"
                                        >
                                            {{ member.status }}
                                        </span>
                                    </div>
                                </div>
                                <Button
                                    @click="removeTeamMember(member.id)"
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
                </div>
                <DialogFooter>
                    <Button variant="outline" @click="showInviteDialog = false">
                        Cancel
                    </Button>
                    <Button
                        :disabled="!invitationForm.email || isSubmitting"
                        @click="sendInvitation"
                    >
                        <LoaderCircle v-if="isSubmitting" class="mr-2 h-4 w-4 animate-spin" />
                        Send Invitation
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
    </AgentLayout>
</template>
