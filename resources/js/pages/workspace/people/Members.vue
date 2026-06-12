<script lang="ts" setup>
import MemberDetailSheet from '@/components/members/MemberDetailSheet.vue';
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
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { getInitials } from '@/composables/useInitials';
import AppLayout from '@/layouts/AppLayout.vue';
import WorkspaceLayout from '@/layouts/workspace/Layout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/vue3';
import axios from 'axios';
import { Check, Copy, KeyRound, LoaderCircle, Mail, Search, Send, Trash2, UserPlus, Users, X } from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { toast } from 'vue-sonner';

type ChatterRow = App.Identity.Chatter.Data.WorkspaceChatterRowData;

interface AgentOption {
    external_id: string;
    name: string;
}

// Protected agents the owner can grant a new member access to (public agents
// have no per-member access, so they're excluded server-side).
const props = defineProps<{ agents: AgentOption[] }>();

const { t } = useI18n();

const breadcrumbs: BreadcrumbItem[] = [{ title: t('workspace.members.breadcrumb'), href: '/my/workspace/people/members' }];

const members = ref<ChatterRow[]>([]);
const isLoading = ref(true);

const fetchMembers = async () => {
    isLoading.value = true;
    try {
        const response = await axios.get(route('people.members.index'));
        members.value = response.data.data ?? [];
    } catch (error) {
        console.error('Failed to fetch members:', error);
        toast.error(t('workspace.members.toast.load_failed'));
    } finally {
        isLoading.value = false;
    }
};

onMounted(fetchMembers);

// Detail sheet
const sheetOpen = ref(false);
const selectedMember = ref<ChatterRow | null>(null);

const openSheet = (member: ChatterRow) => {
    selectedMember.value = member;
    sheetOpen.value = true;
};

const closeSheet = (open: boolean) => {
    sheetOpen.value = open;
    if (!open) selectedMember.value = null;
};

// Keep the row's `agents_count` in sync as the sheet's toggles fire so the
// table reflects access changes immediately, no refetch needed.
const onAccessChanged = (member: ChatterRow, attachedCount: number) => {
    members.value = members.value.map((m) => (m.external_id === member.external_id ? { ...m, agents_count: attachedCount } : m));
    if (selectedMember.value?.external_id === member.external_id) {
        selectedMember.value = { ...selectedMember.value, agents_count: attachedCount };
    }
};

// The sheet saved the message limit / deactivation and returned the fresh row.
const onUpdated = (updated: ChatterRow) => {
    members.value = members.value.map((m) => (m.external_id === updated.external_id ? updated : m));
    if (selectedMember.value?.external_id === updated.external_id) {
        selectedMember.value = updated;
    }
};

// Search
const searchQuery = ref('');
const filteredMembers = computed(() => {
    const q = searchQuery.value.trim().toLowerCase();
    if (!q) return members.value;
    return members.value.filter((m) => m.name.toLowerCase().includes(q) || (m.email ?? '').toLowerCase().includes(q));
});

// Invite dialog — provision a chatter and grant at least one protected agent in
// one step. Mirrors the per-agent Members tab, but lets the owner pick several
// agents up front. With no protected agents, inviting is impossible (a member
// with no agent access can't chat with anything), so the form is disabled.
const inviteOpen = ref(false);
const inviteForm = useForm<{ email: string; agents: string[] }>({ email: '', agents: [] });
const lastInvitation = ref<{ email: string; accept_url: string; expires_at: string } | null>(null);
const inviteCopied = ref(false);

const hasInvitableAgents = computed(() => props.agents.length > 0);

const openInvite = () => {
    inviteForm.reset();
    inviteForm.clearErrors();
    lastInvitation.value = null;
    inviteOpen.value = true;
};

const toggleInviteAgent = (externalId: string, checked: boolean) => {
    inviteForm.agents = checked ? [...inviteForm.agents, externalId] : inviteForm.agents.filter((id) => id !== externalId);
};

const submitInvite = () => {
    inviteForm.post(route('people.members.invite'), {
        preserveScroll: true,
        onSuccess: (page) => {
            const invitation = (page.props as { flash?: { invitation?: typeof lastInvitation.value } })?.flash?.invitation ?? null;
            if (invitation) {
                lastInvitation.value = invitation;
                toast.success(t('workspace.members.toast.invite_sent_to', { email: invitation.email }));
            } else {
                toast.success(t('workspace.members.toast.invite_sent'));
                inviteOpen.value = false;
            }
            inviteForm.reset();
            // The pending chatter now exists — refresh so it shows in the table.
            fetchMembers();
        },
        onError: () => toast.error(t('workspace.members.toast.invite_failed')),
    });
};

const copyInviteLink = async () => {
    if (!lastInvitation.value) return;
    await navigator.clipboard.writeText(lastInvitation.value.accept_url);
    inviteCopied.value = true;
    setTimeout(() => (inviteCopied.value = false), 2000);
};

// Reset password confirmation
const resetOpen = ref(false);
const memberToReset = ref<ChatterRow | null>(null);
const isResetting = ref(false);

const askReset = (member: ChatterRow) => {
    // Close the detail sheet first so the AlertDialog isn't stacked under
    // the sheet's overlay — both use z-50 and the sheet wins by mount order.
    sheetOpen.value = false;
    memberToReset.value = member;
    resetOpen.value = true;
};

const confirmReset = () => {
    if (!memberToReset.value) return;
    const target = memberToReset.value;
    isResetting.value = true;
    router.post(
        route('people.members.reset-password', { chatter: target.external_id }),
        {},
        {
            preserveScroll: true,
            onSuccess: () => {
                toast.success(t('workspace.members.toast.reset_sent', { email: target.email }));
                resetOpen.value = false;
                memberToReset.value = null;
            },
            onError: () => toast.error(t('workspace.members.toast.reset_failed')),
            onFinish: () => {
                isResetting.value = false;
            },
        },
    );
};

// Delete confirmation
const deleteOpen = ref(false);
const memberToDelete = ref<ChatterRow | null>(null);
const isDeleting = ref(false);

const askDelete = (member: ChatterRow) => {
    // Same z-index reason as askReset — close the sheet first.
    sheetOpen.value = false;
    memberToDelete.value = member;
    deleteOpen.value = true;
};

const confirmDelete = () => {
    if (!memberToDelete.value) return;
    const target = memberToDelete.value;
    isDeleting.value = true;
    router.delete(route('people.members.destroy', { chatter: target.external_id }), {
        preserveScroll: true,
        onSuccess: () => {
            members.value = members.value.filter((m) => m.external_id !== target.external_id);
            toast.success(t('workspace.members.toast.removed', { name: target.name }));
            deleteOpen.value = false;
            memberToDelete.value = null;
        },
        onError: () => toast.error(t('workspace.members.toast.remove_failed')),
        onFinish: () => {
            isDeleting.value = false;
        },
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
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="t('workspace.members.title')" />

        <WorkspaceLayout width="wide">
            <div class="w-full space-y-6">
                <div>
                    <h1 class="flex items-center gap-2 text-2xl font-bold tracking-tight">
                        <Users class="size-6" />
                        {{ t('workspace.members.title') }}
                    </h1>
                    <p class="text-muted-foreground mt-1 text-sm">
                        {{ t('workspace.members.description') }}
                    </p>
                </div>

                <div class="flex flex-col items-start justify-between gap-4 sm:flex-row sm:items-center">
                    <div class="relative w-full sm:w-72">
                        <Search class="text-muted-foreground absolute top-1/2 start-3 h-4 w-4 -translate-y-1/2" />
                        <Input v-model="searchQuery" :placeholder="t('workspace.members.search_placeholder')" class="ps-10" />
                    </div>

                    <div class="flex w-full items-center justify-between gap-4 sm:w-auto">
                        <div v-if="members.length > 0" class="text-muted-foreground text-sm">
                            {{ t('workspace.members.count', { shown: filteredMembers.length, total: members.length }, members.length) }}
                        </div>
                        <Button
                            size="sm"
                            :disabled="!hasInvitableAgents"
                            :title="hasInvitableAgents ? undefined : t('workspace.members.invite_disabled_hint')"
                            @click="openInvite"
                        >
                            <UserPlus class="me-2 h-4 w-4" />
                            {{ t('workspace.members.invite') }}
                        </Button>
                    </div>
                </div>

                <div class="rounded-md border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>{{ t('workspace.members.table.name') }}</TableHead>
                                <TableHead>{{ t('workspace.members.table.email') }}</TableHead>
                                <TableHead>{{ t('workspace.members.table.status') }}</TableHead>
                                <TableHead>{{ t('workspace.members.table.agents') }}</TableHead>
                                <TableHead>{{ t('workspace.members.table.messages') }}</TableHead>
                                <TableHead>{{ t('workspace.members.table.created') }}</TableHead>
                                <TableHead class="w-32 text-end">{{ t('workspace.members.table.actions') }}</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-if="isLoading">
                                <TableCell colspan="7" class="py-8 text-center">
                                    <LoaderCircle class="text-muted-foreground mx-auto h-8 w-8 animate-spin" />
                                </TableCell>
                            </TableRow>
                            <TableRow v-else-if="filteredMembers.length === 0">
                                <TableCell colspan="7" class="text-muted-foreground py-8 text-center">
                                    <div v-if="members.length === 0">
                                        <Users class="text-muted-foreground/50 mx-auto mb-4 h-12 w-12" />
                                        <p>{{ t('workspace.members.empty.title') }}</p>
                                        <p class="text-sm">{{ t('workspace.members.empty.description') }}</p>
                                        <Button v-if="hasInvitableAgents" size="sm" class="mt-4" @click="openInvite">
                                            <UserPlus class="me-2 h-4 w-4" />
                                            {{ t('workspace.members.empty.invite_first') }}
                                        </Button>
                                    </div>
                                    <div v-else>
                                        <p>{{ t('workspace.members.no_results.title') }}</p>
                                        <p class="text-sm">{{ t('workspace.members.no_results.description') }}</p>
                                    </div>
                                </TableCell>
                            </TableRow>
                            <TableRow
                                v-for="member in filteredMembers"
                                :key="member.external_id"
                                class="hover:bg-muted/40 cursor-pointer"
                                @click="openSheet(member)"
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
                                    {{ member.email || t('workspace.members.no_email') }}
                                </TableCell>
                                <TableCell>
                                    <span
                                        v-if="member.is_deactivated"
                                        class="inline-flex items-center rounded-full border border-red-200 bg-red-50 px-2 py-0.5 text-xs font-medium text-red-900 dark:border-red-900/50 dark:bg-red-950/30 dark:text-red-100"
                                    >
                                        {{ t('workspace.members.status.deactivated') }}
                                    </span>
                                    <span
                                        v-else-if="member.status === 'pending'"
                                        class="inline-flex items-center rounded-full border border-amber-200 bg-amber-50 px-2 py-0.5 text-xs font-medium text-amber-900 dark:border-amber-900/50 dark:bg-amber-950/30 dark:text-amber-100"
                                    >
                                        {{ t('workspace.members.status.pending') }}
                                    </span>
                                    <span
                                        v-else
                                        class="inline-flex items-center rounded-full border border-green-200 bg-green-50 px-2 py-0.5 text-xs font-medium text-green-900 dark:border-green-900/50 dark:bg-green-950/30 dark:text-green-100"
                                    >
                                        {{ t('workspace.members.status.active') }}
                                    </span>
                                </TableCell>
                                <TableCell>
                                    {{ member.agents_count }}
                                </TableCell>
                                <TableCell>
                                    <span
                                        v-if="member.messages_limit !== null"
                                        class="text-sm tabular-nums"
                                        :class="member.messages_used >= member.messages_limit ? 'text-destructive font-medium' : ''"
                                    >
                                        {{ member.messages_used }} / {{ member.messages_limit }}
                                    </span>
                                    <span v-else class="text-muted-foreground text-sm">—</span>
                                </TableCell>
                                <TableCell>
                                    {{ formatDate(member.created_at) }}
                                </TableCell>
                                <TableCell class="text-end" @click.stop>
                                    <div class="flex items-center justify-end gap-1">
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            class="h-8 w-8 p-0"
                                            :title="t('workspace.members.reset_title', { name: member.name })"
                                            :disabled="!member.email || member.status === 'pending'"
                                            @click="askReset(member)"
                                        >
                                            <KeyRound class="h-4 w-4" />
                                        </Button>
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            class="text-destructive hover:text-destructive h-8 w-8 p-0"
                                            :title="t('workspace.members.remove_title', { name: member.name })"
                                            @click="askDelete(member)"
                                        >
                                            <Trash2 class="h-4 w-4" />
                                        </Button>
                                    </div>
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </div>
            </div>

            <!-- Invite dialog -->
            <Dialog v-model:open="inviteOpen">
                <DialogContent class="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle class="flex items-center gap-2">
                            <UserPlus class="h-5 w-5" />
                            {{ t('workspace.members.invite_dialog.title') }}
                        </DialogTitle>
                        <DialogDescription>
                            {{ t('workspace.members.invite_dialog.description') }}
                        </DialogDescription>
                    </DialogHeader>

                    <form v-if="!lastInvitation" class="space-y-4" @submit.prevent="submitInvite">
                        <div class="space-y-2">
                            <Label for="invite-email">{{ t('workspace.members.invite_dialog.email') }}</Label>
                            <div class="relative">
                                <div class="text-muted-foreground pointer-events-none absolute inset-y-0 start-0 flex items-center ps-3">
                                    <Mail class="h-4 w-4" />
                                </div>
                                <Input
                                    id="invite-email"
                                    v-model="inviteForm.email"
                                    type="email"
                                    required
                                    autofocus
                                    placeholder="person@example.com"
                                    class="ps-9"
                                    :class="{ 'border-red-500': inviteForm.errors.email }"
                                />
                            </div>
                            <p v-if="inviteForm.errors.email" class="text-xs text-red-500">{{ inviteForm.errors.email }}</p>
                        </div>

                        <div class="space-y-2">
                            <Label>{{ t('workspace.members.invite_dialog.agents') }}</Label>
                            <div class="max-h-48 space-y-1 overflow-y-auto rounded-md border p-1">
                                <label
                                    v-for="agent in props.agents"
                                    :key="agent.external_id"
                                    class="hover:bg-muted flex cursor-pointer items-center gap-3 rounded-md px-2 py-2 text-sm"
                                >
                                    <Checkbox
                                        :model-value="inviteForm.agents.includes(agent.external_id)"
                                        @update:model-value="(checked) => toggleInviteAgent(agent.external_id, checked === true)"
                                    />
                                    <span class="truncate">{{ agent.name }}</span>
                                </label>
                            </div>
                            <p v-if="inviteForm.errors.agents" class="text-xs text-red-500">{{ inviteForm.errors.agents }}</p>
                            <p v-else class="text-muted-foreground text-xs">{{ t('workspace.members.invite_dialog.agents_hint') }}</p>
                        </div>

                        <DialogFooter>
                            <Button type="button" variant="outline" :disabled="inviteForm.processing" @click="inviteOpen = false">
                                <X class="me-2 h-4 w-4" />
                                {{ t('workspace.common.cancel') }}
                            </Button>
                            <Button type="submit" :disabled="inviteForm.processing || !inviteForm.email || inviteForm.agents.length === 0">
                                <LoaderCircle v-if="inviteForm.processing" class="me-2 h-4 w-4 animate-spin" />
                                <Send v-else class="me-2 h-4 w-4" />
                                {{ t('workspace.members.invite_dialog.send') }}
                            </Button>
                        </DialogFooter>
                    </form>

                    <!-- After-send view: lets the owner copy the link manually -->
                    <div v-else class="space-y-4">
                        <div
                            class="rounded-md border border-green-200 bg-green-50 p-3 text-sm text-green-900 dark:border-green-900/50 dark:bg-green-950/30 dark:text-green-100"
                        >
                            <i18n-t keypath="workspace.members.invite_dialog.emailed_to" tag="span">
                                <template #email><span class="font-medium">{{ lastInvitation.email }}</span></template>
                            </i18n-t>
                        </div>

                        <div class="space-y-2">
                            <Label>{{ t('workspace.members.invite_dialog.signup_link') }}</Label>
                            <div class="flex items-center gap-2">
                                <Input :model-value="lastInvitation.accept_url" readonly class="font-mono text-xs" />
                                <Button type="button" variant="outline" size="icon" :title="t('workspace.common.copy')" @click="copyInviteLink">
                                    <Check v-if="inviteCopied" class="h-4 w-4 text-green-600" />
                                    <Copy v-else class="h-4 w-4" />
                                </Button>
                            </div>
                            <p class="text-muted-foreground text-xs">{{ t('workspace.members.invite_dialog.expires', { date: formatDate(lastInvitation.expires_at) }) }}</p>
                        </div>

                        <DialogFooter>
                            <Button type="button" variant="outline" @click="openInvite">
                                <UserPlus class="me-2 h-4 w-4" />
                                {{ t('workspace.members.invite_dialog.invite_another') }}
                            </Button>
                            <Button type="button" @click="inviteOpen = false">
                                <Check class="me-2 h-4 w-4" />
                                {{ t('workspace.members.invite_dialog.done') }}
                            </Button>
                        </DialogFooter>
                    </div>
                </DialogContent>
            </Dialog>

            <!-- Reset password confirmation -->
            <AlertDialog v-model:open="resetOpen">
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>{{ t('workspace.members.reset_dialog.title') }}</AlertDialogTitle>
                        <AlertDialogDescription>
                            <i18n-t keypath="workspace.members.reset_dialog.description" tag="span">
                                <template #email><span class="font-medium">{{ memberToReset?.email }}</span></template>
                            </i18n-t>
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel :disabled="isResetting">{{ t('workspace.common.cancel') }}</AlertDialogCancel>
                        <AlertDialogAction :disabled="isResetting" @click="confirmReset">
                            <LoaderCircle v-if="isResetting" class="me-2 h-4 w-4 animate-spin" />
                            {{ t('workspace.members.reset_dialog.confirm') }}
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>

            <!-- Detail sheet -->
            <MemberDetailSheet
                :open="sheetOpen"
                :member="selectedMember"
                @update:open="closeSheet"
                @reset="askReset"
                @delete="askDelete"
                @access-changed="onAccessChanged"
                @updated="onUpdated"
            />

            <!-- Delete confirmation -->
            <AlertDialog v-model:open="deleteOpen">
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>{{ t('workspace.members.delete_dialog.title') }}</AlertDialogTitle>
                        <AlertDialogDescription>
                            <i18n-t keypath="workspace.members.delete_dialog.description" tag="span">
                                <template #name><span class="font-medium">{{ memberToDelete?.name }}</span></template>
                            </i18n-t>
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel :disabled="isDeleting">{{ t('workspace.common.cancel') }}</AlertDialogCancel>
                        <AlertDialogAction
                            class="bg-destructive text-destructive-foreground hover:bg-destructive/90"
                            :disabled="isDeleting"
                            @click="confirmDelete"
                        >
                            <LoaderCircle v-if="isDeleting" class="me-2 h-4 w-4 animate-spin" />
                            {{ t('workspace.members.delete_dialog.confirm') }}
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </WorkspaceLayout>
    </AppLayout>
</template>
