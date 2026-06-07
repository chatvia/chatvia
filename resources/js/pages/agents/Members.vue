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
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Sheet, SheetContent, SheetDescription, SheetFooter, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { getInitials } from '@/composables/useInitials';
import AgentLayout from '@/layouts/AgentLayout.vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import axios from 'axios';
import {
    Check,
    CircleAlert,
    Clock,
    Copy,
    Globe,
    LoaderCircle,
    Mail,
    PlusCircle,
    RefreshCw,
    Search,
    Send,
    Trash2,
    UserPlus,
    Users,
    X,
} from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';
import { toast } from 'vue-sonner';

interface Props {
    agent: Domain.Agent.Data.Resources.Account.AgentResourceData;
}

const props = defineProps<Props>();

type ChatterRow = Domain.Membership.Agent.Data.Resources.AgentMemberRowData;

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

// Member detail sheet. We track the id (not a snapshot) so the panel always
// reflects the latest row after a grant/revoke/resend refresh.
const memberSheetOpen = ref(false);
const selectedMemberId = ref<string | null>(null);
const selectedMember = computed<ChatterRow | null>(() => members.value.find((m) => m.external_id === selectedMemberId.value) ?? null);
const sheetCopied = ref(false);
const idCopied = ref(false);
const isResending = ref(false);

// Up-to-two-letter avatar initials from the member's name.
const memberInitials = computed(() => {
    const name = selectedMember.value?.name?.trim();
    if (!name) return '?';
    return name
        .split(/\s+/)
        .slice(0, 2)
        .map((p) => p[0]?.toUpperCase() ?? '')
        .join('');
});

const openMember = (member: ChatterRow) => {
    selectedMemberId.value = member.external_id;
    sheetCopied.value = false;
    idCopied.value = false;
    memberSheetOpen.value = true;
};

const copyId = async () => {
    const id = selectedMember.value?.external_id;
    if (!id) return;
    await navigator.clipboard.writeText(id);
    idCopied.value = true;
    setTimeout(() => (idCopied.value = false), 2000);
};

const copyInvitationLink = async () => {
    const url = selectedMember.value?.invitation_url;
    if (!url) return;
    await navigator.clipboard.writeText(url);
    sheetCopied.value = true;
    setTimeout(() => (sheetCopied.value = false), 2000);
};

// Re-invite an existing pending member: mints a fresh link + emails it. After
// the refresh the row carries the new `invitation_url`, so the sheet (driven by
// the computed above) shows the copyable link without any manual patching.
const resendInvitation = () => {
    const member = selectedMember.value;
    if (!member?.email) return;
    isResending.value = true;
    router.post(
        route('agents.members.store', { agent: props.agent.external_id }),
        { email: member.email },
        {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                toast.success(`Fresh invitation link generated for ${member.name}`);
                fetchMembers();
            },
            onError: () => toast.error('Could not generate an invitation link'),
            onFinish: () => {
                isResending.value = false;
            },
        },
    );
};

const revokeFromSheet = () => {
    const member = selectedMember.value;
    if (!member) return;
    // Close the sheet before opening the confirm dialog so the two overlays
    // don't fight over focus.
    memberSheetOpen.value = false;
    askDetach(member);
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
                                        class="hover:bg-muted/50 cursor-pointer transition-colors"
                                        :class="{ 'opacity-70': !member.has_access }"
                                        @click="openMember(member)"
                                    >
                                        <TableCell class="font-medium">
                                            <div class="flex items-center gap-3">
                                                <Avatar class="size-8">
                                                    <AvatarImage v-if="member.avatar" :src="member.avatar" :alt="member.name" />
                                                    <AvatarFallback class="text-xs">{{ getInitials(member.name) }}</AvatarFallback>
                                                </Avatar>
                                                <span>{{ member.name }}</span>
                                            </div>
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
                                                @click.stop="grantAccess(member)"
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
                                                @click.stop="askDetach(member)"
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
                        <Button type="button" variant="outline" @click="inviteOpen = false" :disabled="inviteForm.processing">
                            <X class="mr-2 h-4 w-4" />
                            Cancel
                        </Button>
                        <Button type="submit" :disabled="inviteForm.processing || !inviteForm.email">
                            <LoaderCircle v-if="inviteForm.processing" class="mr-2 h-4 w-4 animate-spin" />
                            <Send v-else class="mr-2 h-4 w-4" />
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
                        <Button type="button" variant="outline" @click="lastInvitation = null">
                            <UserPlus class="mr-2 h-4 w-4" />
                            Invite another
                        </Button>
                        <Button type="button" @click="inviteOpen = false">
                            <Check class="mr-2 h-4 w-4" />
                            Done
                        </Button>
                    </DialogFooter>
                </div>
            </DialogContent>
        </Dialog>

        <!-- Member detail sheet -->
        <Sheet v-model:open="memberSheetOpen">
            <SheetContent v-if="selectedMember" class="w-full overflow-y-auto p-0 sm:max-w-md">
                <SheetHeader class="space-y-4 border-b px-6 pt-7 pb-6">
                    <div class="flex items-start gap-4">
                        <Avatar class="size-14 shrink-0">
                            <AvatarImage v-if="selectedMember.avatar" :src="selectedMember.avatar" :alt="selectedMember.name" />
                            <AvatarFallback class="text-base font-semibold">{{ memberInitials }}</AvatarFallback>
                        </Avatar>
                        <div class="min-w-0 flex-1 pt-1">
                            <SheetTitle class="truncate text-lg">{{ selectedMember.name }}</SheetTitle>
                            <div class="mt-1.5 flex items-center gap-2 text-sm">
                                <span
                                    v-if="selectedMember.status === 'pending'"
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
                                <span class="text-muted-foreground truncate">{{ selectedMember.email || 'No email' }}</span>
                            </div>
                        </div>
                    </div>
                    <SheetDescription class="sr-only">Member details and access for this agent.</SheetDescription>
                    <div class="space-y-1.5">
                        <div class="flex items-center gap-1.5 text-xs">
                            <span class="text-muted-foreground">ID</span>
                            <code class="min-w-0 flex-1 truncate font-mono" :title="selectedMember.external_id">{{
                                selectedMember.external_id
                            }}</code>
                            <Button variant="ghost" size="sm" class="h-6 shrink-0 px-1.5" title="Copy member ID" @click="copyId">
                                <Check v-if="idCopied" class="size-3.5 text-green-600" />
                                <Copy v-else class="size-3.5" />
                            </Button>
                        </div>
                        <p class="text-muted-foreground text-xs">Joined {{ formatDate(selectedMember.created_at) }}</p>
                    </div>
                </SheetHeader>

                <div class="space-y-6 px-6 py-6">
                    <!-- Access -->
                    <div class="space-y-1.5">
                        <p class="text-muted-foreground text-xs font-medium uppercase">Access</p>
                        <div>
                            <span
                                v-if="selectedMember.has_access"
                                class="inline-flex items-center rounded-full bg-neutral-900 px-2 py-0.5 text-xs font-medium text-white dark:bg-neutral-100 dark:text-neutral-900"
                            >
                                Granted
                            </span>
                            <span v-else class="text-muted-foreground text-xs">No access</span>
                        </div>
                    </div>

                    <!-- Pending invitation -->
                    <div v-if="selectedMember.status === 'pending'" class="space-y-3 rounded-md border p-4">
                        <div class="flex items-start gap-2">
                            <Clock class="mt-0.5 h-4 w-4 shrink-0 text-amber-600 dark:text-amber-400" />
                            <div class="space-y-0.5">
                                <p class="text-sm font-medium">Invitation pending</p>
                                <p class="text-muted-foreground text-xs">This member hasn't accepted yet. Share their personal sign-up link below.</p>
                            </div>
                        </div>

                        <div v-if="selectedMember.invitation_url" class="space-y-2">
                            <Label>Sign-up link</Label>
                            <div class="flex items-center gap-2">
                                <Input :model-value="selectedMember.invitation_url" readonly class="font-mono text-xs" />
                                <Button type="button" variant="outline" size="icon" title="Copy" @click="copyInvitationLink">
                                    <Check v-if="sheetCopied" class="h-4 w-4 text-green-600" />
                                    <Copy v-else class="h-4 w-4" />
                                </Button>
                            </div>
                            <p v-if="selectedMember.invitation_expires_at" class="text-muted-foreground text-xs">
                                Expires {{ formatDate(selectedMember.invitation_expires_at) }}.
                            </p>
                        </div>

                        <div v-else class="text-muted-foreground flex items-start gap-2 text-xs">
                            <CircleAlert class="mt-0.5 h-4 w-4 shrink-0" />
                            <p>The invitation link has expired or is no longer available. Generate a fresh one below.</p>
                        </div>

                        <Button type="button" variant="outline" size="sm" class="w-full" :disabled="isResending" @click="resendInvitation">
                            <LoaderCircle v-if="isResending" class="mr-2 h-4 w-4 animate-spin" />
                            <RefreshCw v-else class="mr-2 h-4 w-4" />
                            {{ selectedMember.invitation_url ? 'Resend invitation' : 'Generate new link' }}
                        </Button>
                    </div>

                    <!-- Manage hint -->
                    <p class="text-muted-foreground text-sm">
                        To manage this member, go to the
                        <Link
                            :href="route('settings.members.index')"
                            class="text-foreground font-medium underline underline-offset-4 hover:no-underline"
                        >
                            members page </Link
                        >.
                    </p>
                </div>

                <SheetFooter class="border-t px-6 py-4">
                    <Button
                        v-if="selectedMember?.has_access"
                        variant="outline"
                        class="text-destructive hover:text-destructive"
                        @click="revokeFromSheet"
                    >
                        <Trash2 class="mr-2 h-4 w-4" />
                        Revoke access
                    </Button>
                    <Button
                        v-else-if="selectedMember"
                        variant="outline"
                        :disabled="grantingId === selectedMember.external_id"
                        @click="grantAccess(selectedMember)"
                    >
                        <LoaderCircle v-if="grantingId === selectedMember.external_id" class="mr-2 h-4 w-4 animate-spin" />
                        <PlusCircle v-else class="mr-2 h-4 w-4" />
                        Grant access
                    </Button>
                </SheetFooter>
            </SheetContent>
        </Sheet>

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
