<script lang="ts" setup>
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
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AgentLayout from '@/layouts/AgentLayout.vue';
import { router, useForm } from '@inertiajs/vue3';
import axios from 'axios';
import { Check, Copy, Globe, LoaderCircle, Mail, PlusCircle, Search, Trash2, UserPlus, Users } from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';
import { toast } from 'vue-sonner';

interface Props {
    agent: Domain.Agent.Data.Resources.Account.AgentResourceData;
}

const props = defineProps<Props>();

type ChatterRow = Domain.Member.Data.Resources.AgentMemberRowData;

const members = ref<ChatterRow[]>([]);
const isLoading = ref(true);

const fetchMembers = async () => {
    if (props.agent.is_public) {
        // Nothing to show for a public agent — the notice block in the
        // template stands in for the table.
        isLoading.value = false;
        members.value = [];
        return;
    }
    isLoading.value = true;
    try {
        const response = await axios.get(route('agents.members.index', { agent: props.agent.external_id }));
        members.value = response.data.data ?? [];
    } catch (error) {
        console.error('Failed to fetch members:', error);
        toast.error('Failed to load members');
    } finally {
        isLoading.value = false;
    }
};

onMounted(() => {
    fetchMembers();
});

// Search
const searchQuery = ref('');
const filteredMembers = computed(() => {
    const q = searchQuery.value.trim().toLowerCase();
    if (!q) return members.value;
    return members.value.filter((m) => m.name.toLowerCase().includes(q) || (m.email ?? '').toLowerCase().includes(q));
});

// Invite dialog
const inviteOpen = ref(false);
const inviteForm = useForm({ email: '' });
const lastInvitation = ref<{ email: string; accept_url: string; expires_at: string } | null>(null);
const copied = ref(false);

const openInvite = () => {
    inviteForm.reset();
    inviteForm.clearErrors();
    lastInvitation.value = null;
    inviteOpen.value = true;
};

const submitInvite = () => {
    inviteForm.post(route('agents.members.store', { agent: props.agent.external_id }), {
        preserveScroll: true,
        onSuccess: (page) => {
            const invitation = (page.props as any)?.flash?.invitation ?? null;
            if (invitation) {
                lastInvitation.value = invitation;
                toast.success(`Invitation sent to ${invitation.email}`);
            } else {
                toast.success('Invitation sent');
                inviteOpen.value = false;
            }
            inviteForm.reset();
            // Pending row now exists in the pivot — refresh so the owner
            // can see it without leaving the page.
            fetchMembers();
        },
    });
};

const copyLink = async () => {
    if (!lastInvitation.value) return;
    await navigator.clipboard.writeText(lastInvitation.value.accept_url);
    copied.value = true;
    setTimeout(() => (copied.value = false), 2000);
};

// Detach confirmation
const detachOpen = ref(false);
const memberToDetach = ref<ChatterRow | null>(null);
const isDetaching = ref(false);

const askDetach = (member: ChatterRow) => {
    memberToDetach.value = member;
    detachOpen.value = true;
};

const confirmDetach = () => {
    if (!memberToDetach.value) return;
    const target = memberToDetach.value;
    isDetaching.value = true;
    router.delete(
        route('agents.members.destroy', {
            agent: props.agent.external_id,
            chatter: target.external_id,
        }),
        {
            preserveScroll: true,
            onSuccess: () => {
                // Keep the row visible but flip it to "no access" so it
                // moves into the lower group and the Grant action shows up.
                members.value = members.value.map((m) => (m.external_id === target.external_id ? { ...m, has_access: false } : m));
                resort();
                toast.success(`${target.name} no longer has access to this agent`);
                detachOpen.value = false;
                memberToDetach.value = null;
            },
            onError: () => toast.error('Could not revoke access'),
            onFinish: () => {
                isDetaching.value = false;
            },
        },
    );
};

// Grant access to an existing chatter (idempotent server-side).
const grantingId = ref<string | null>(null);

const grantAccess = (member: ChatterRow) => {
    grantingId.value = member.external_id;
    router.post(
        route('agents.members.grant', {
            agent: props.agent.external_id,
            chatter: member.external_id,
        }),
        {},
        {
            preserveScroll: true,
            onSuccess: () => {
                members.value = members.value.map((m) => (m.external_id === member.external_id ? { ...m, has_access: true } : m));
                resort();
                toast.success(`${member.name} now has access to this agent`);
            },
            onError: () => toast.error('Could not grant access'),
            onFinish: () => {
                grantingId.value = null;
            },
        },
    );
};

// Mirror the server-side sort: granted first, then alphabetical within each group.
const resort = () => {
    members.value = [...members.value].sort((a, b) => {
        if (a.has_access !== b.has_access) return a.has_access ? -1 : 1;
        return a.name.toLowerCase().localeCompare(b.name.toLowerCase());
    });
};

const formatDate = (iso: string) => {
    return new Date(iso).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
};
</script>

<template>
    <AgentLayout :agent="agent" title="Members">
        <div class="flex justify-center">
            <Card class="w-full max-w-6xl">
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <Users class="h-5 w-5" />
                        Members
                    </CardTitle>
                    <CardDescription>
                        Everyone in your workspace. Members with access to this agent are listed first; grant any other member access in one click.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <!-- Public-agent notice — no members for a public agent
                         since anyone with the URL can chat. The owner can
                         still see the workspace roster, but invite + grant
                         are off the table. -->
                    <div
                        v-if="agent.is_public"
                        class="flex items-start gap-3 rounded-md border border-blue-200 bg-blue-50 p-4 text-sm dark:border-blue-900/50 dark:bg-blue-950/30"
                    >
                        <Globe class="mt-0.5 h-5 w-5 shrink-0 text-blue-600 dark:text-blue-400" />
                        <div class="space-y-1">
                            <p class="font-medium text-blue-900 dark:text-blue-100">This agent is public</p>
                            <p class="text-blue-800/80 dark:text-blue-200/80">
                                Anyone with the link can chat with this agent, so member grants don't apply. To use members, make the agent protected
                                from its Setup tab.
                            </p>
                        </div>
                    </div>

                    <div v-else class="space-y-4">
                        <!-- Search + invite -->
                        <div class="flex flex-col items-start justify-between gap-4 sm:flex-row sm:items-center">
                            <div class="relative w-full sm:w-64">
                                <Search class="text-muted-foreground absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 transform" />
                                <Input v-model="searchQuery" placeholder="Search members..." class="pl-10" />
                            </div>

                            <div class="flex w-full items-center justify-between gap-3 sm:w-auto sm:justify-end">
                                <div v-if="members.length > 0" class="text-muted-foreground text-sm">
                                    {{ filteredMembers.length }} of {{ members.length }} member{{ members.length === 1 ? '' : 's' }}
                                </div>
                                <Button size="sm" @click="openInvite">
                                    <UserPlus class="mr-2 h-4 w-4" />
                                    Invite member
                                </Button>
                            </div>
                        </div>

                        <!-- Members Table -->
                        <div class="rounded-md border">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Name</TableHead>
                                        <TableHead>Email</TableHead>
                                        <TableHead>Status</TableHead>
                                        <TableHead>Access</TableHead>
                                        <TableHead class="w-32 text-right">Actions</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    <TableRow v-if="isLoading">
                                        <TableCell colspan="5" class="py-8 text-center">
                                            <LoaderCircle class="text-muted-foreground mx-auto h-8 w-8 animate-spin" />
                                        </TableCell>
                                    </TableRow>
                                    <TableRow v-else-if="filteredMembers.length === 0">
                                        <TableCell colspan="5" class="text-muted-foreground py-8 text-center">
                                            <div v-if="members.length === 0">
                                                <Users class="text-muted-foreground/50 mx-auto mb-4 h-12 w-12" />
                                                <p>No members in your workspace yet.</p>
                                                <p class="text-sm">Invite someone to get started — they'll show up here.</p>
                                            </div>
                                            <div v-else>
                                                <p>No members found matching your search.</p>
                                                <p class="text-sm">Try adjusting your search terms.</p>
                                            </div>
                                        </TableCell>
                                    </TableRow>
                                    <TableRow
                                        v-for="member in filteredMembers"
                                        :key="member.external_id"
                                        :class="{ 'opacity-70': !member.has_access }"
                                    >
                                        <TableCell class="font-medium">
                                            {{ member.name }}
                                        </TableCell>
                                        <TableCell>
                                            {{ member.email || 'No email' }}
                                        </TableCell>
                                        <TableCell>
                                            <span
                                                v-if="member.status === 'pending'"
                                                class="inline-flex items-center rounded-full border border-amber-200 bg-amber-50 px-2 py-0.5 text-xs font-medium text-amber-900 dark:border-amber-900/50 dark:bg-amber-950/30 dark:text-amber-100"
                                            >
                                                Pending
                                            </span>
                                            <span
                                                v-else
                                                class="inline-flex items-center rounded-full border border-green-200 bg-green-50 px-2 py-0.5 text-xs font-medium text-green-900 dark:border-green-900/50 dark:bg-green-950/30 dark:text-green-100"
                                            >
                                                Active
                                            </span>
                                        </TableCell>
                                        <TableCell>
                                            <span
                                                v-if="member.has_access"
                                                class="inline-flex items-center rounded-full bg-neutral-900 px-2 py-0.5 text-xs font-medium text-white dark:bg-neutral-100 dark:text-neutral-900"
                                            >
                                                Granted
                                            </span>
                                            <span v-else class="text-muted-foreground text-xs">No access</span>
                                        </TableCell>
                                        <TableCell class="text-right">
                                            <Button
                                                v-if="!member.has_access"
                                                variant="outline"
                                                size="sm"
                                                class="h-8"
                                                :disabled="grantingId === member.external_id"
                                                :title="`Grant ${member.name} access to this agent`"
                                                @click="grantAccess(member)"
                                            >
                                                <LoaderCircle v-if="grantingId === member.external_id" class="mr-1 h-3.5 w-3.5 animate-spin" />
                                                <PlusCircle v-else class="mr-1 h-3.5 w-3.5" />
                                                Grant
                                            </Button>
                                            <Button
                                                v-else
                                                variant="ghost"
                                                size="sm"
                                                class="text-destructive hover:text-destructive h-8 w-8 p-0"
                                                :title="`Revoke access for ${member.name}`"
                                                @click="askDetach(member)"
                                            >
                                                <Trash2 class="h-4 w-4" />
                                            </Button>
                                        </TableCell>
                                    </TableRow>
                                </TableBody>
                            </Table>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- Invite dialog -->
        <Dialog v-model:open="inviteOpen">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle class="flex items-center gap-2">
                        <UserPlus class="h-5 w-5" />
                        Invite a member
                    </DialogTitle>
                    <DialogDescription>
                        We'll email a sign-up link to this address. They can set a password or sign in with Google or Slack.
                    </DialogDescription>
                </DialogHeader>

                <form v-if="!lastInvitation" @submit.prevent="submitInvite" class="space-y-4">
                    <div class="space-y-2">
                        <Label for="invite-email">Email address</Label>
                        <div class="relative">
                            <div class="text-muted-foreground pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                                <Mail class="h-4 w-4" />
                            </div>
                            <Input
                                id="invite-email"
                                v-model="inviteForm.email"
                                type="email"
                                required
                                autofocus
                                placeholder="person@example.com"
                                class="pl-9"
                                :class="{ 'border-red-500': inviteForm.errors.email }"
                            />
                        </div>
                        <p v-if="inviteForm.errors.email" class="text-xs text-red-500">{{ inviteForm.errors.email }}</p>
                    </div>

                    <DialogFooter>
                        <Button type="button" variant="outline" @click="inviteOpen = false" :disabled="inviteForm.processing">Cancel</Button>
                        <Button type="submit" :disabled="inviteForm.processing || !inviteForm.email">
                            <LoaderCircle v-if="inviteForm.processing" class="mr-2 h-4 w-4 animate-spin" />
                            Send invite
                        </Button>
                    </DialogFooter>
                </form>

                <!-- After-send view: lets the owner copy the link manually -->
                <div v-else class="space-y-4">
                    <div
                        class="rounded-md border border-green-200 bg-green-50 p-3 text-sm text-green-900 dark:border-green-900/50 dark:bg-green-950/30 dark:text-green-100"
                    >
                        Invitation emailed to <span class="font-medium">{{ lastInvitation.email }}</span
                        >. You can also copy the link below.
                    </div>

                    <div class="space-y-2">
                        <Label>Sign-up link</Label>
                        <div class="flex items-center gap-2">
                            <Input :model-value="lastInvitation.accept_url" readonly class="font-mono text-xs" />
                            <Button type="button" variant="outline" size="icon" title="Copy" @click="copyLink">
                                <Check v-if="copied" class="h-4 w-4 text-green-600" />
                                <Copy v-else class="h-4 w-4" />
                            </Button>
                        </div>
                        <p class="text-muted-foreground text-xs">Expires {{ formatDate(lastInvitation.expires_at) }}.</p>
                    </div>

                    <DialogFooter>
                        <Button type="button" variant="outline" @click="lastInvitation = null">Invite another</Button>
                        <Button type="button" @click="inviteOpen = false">Done</Button>
                    </DialogFooter>
                </div>
            </DialogContent>
        </Dialog>

        <!-- Revoke confirmation -->
        <AlertDialog v-model:open="detachOpen">
            <AlertDialogContent>
                <AlertDialogHeader>
                    <AlertDialogTitle>Revoke access</AlertDialogTitle>
                    <AlertDialogDescription>
                        <span class="font-medium">{{ memberToDetach?.name }}</span> will lose access to this agent immediately. They stay in your
                        workspace and you can grant access again at any time.
                    </AlertDialogDescription>
                </AlertDialogHeader>
                <AlertDialogFooter>
                    <AlertDialogCancel :disabled="isDetaching">Cancel</AlertDialogCancel>
                    <AlertDialogAction
                        class="bg-destructive text-destructive-foreground hover:bg-destructive/90"
                        :disabled="isDetaching"
                        @click="confirmDetach"
                    >
                        <LoaderCircle v-if="isDetaching" class="mr-2 h-4 w-4 animate-spin" />
                        Revoke
                    </AlertDialogAction>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    </AgentLayout>
</template>
