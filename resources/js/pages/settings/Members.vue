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
import { Input } from '@/components/ui/input';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { getInitials } from '@/composables/useInitials';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import axios from 'axios';
import { KeyRound, LoaderCircle, Search, Trash2, Users } from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';
import { toast } from 'vue-sonner';

type ChatterRow = App.Identity.Chatter.Data.WorkspaceChatterRowData;

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Members', href: '/settings/members' }];

const members = ref<ChatterRow[]>([]);
const isLoading = ref(true);

const fetchMembers = async () => {
    isLoading.value = true;
    try {
        const response = await axios.get(route('settings.members.index'));
        members.value = response.data.data ?? [];
    } catch (error) {
        console.error('Failed to fetch members:', error);
        toast.error('Failed to load members');
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
        route('settings.members.reset-password', { chatter: target.external_id }),
        {},
        {
            preserveScroll: true,
            onSuccess: () => {
                toast.success(`Password reset link sent to ${target.email}`);
                resetOpen.value = false;
                memberToReset.value = null;
            },
            onError: () => toast.error('Could not send the reset link'),
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
    router.delete(route('settings.members.destroy', { chatter: target.external_id }), {
        preserveScroll: true,
        onSuccess: () => {
            members.value = members.value.filter((m) => m.external_id !== target.external_id);
            toast.success(`${target.name} removed`);
            deleteOpen.value = false;
            memberToDelete.value = null;
        },
        onError: () => toast.error('Could not remove this member'),
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
        <Head title="Members" />

        <SettingsLayout width="wide">
            <div class="w-full space-y-6">
                <div>
                    <h1 class="flex items-center gap-2 text-2xl font-bold tracking-tight">
                        <Users class="size-6" />
                        Members
                    </h1>
                    <p class="text-muted-foreground mt-1 text-sm">
                        Everyone you've granted access to chat with your agents. Send a password reset link or remove a member entirely.
                    </p>
                </div>

                <div class="flex flex-col items-start justify-between gap-4 sm:flex-row sm:items-center">
                    <div class="relative w-full sm:w-72">
                        <Search class="text-muted-foreground absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2" />
                        <Input v-model="searchQuery" placeholder="Search members..." class="pl-10" />
                    </div>

                    <div v-if="members.length > 0" class="text-muted-foreground text-sm">
                        {{ filteredMembers.length }} of {{ members.length }} member{{ members.length === 1 ? '' : 's' }}
                    </div>
                </div>

                <div class="rounded-md border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Name</TableHead>
                                <TableHead>Email</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead>Agents</TableHead>
                                <TableHead>Messages</TableHead>
                                <TableHead>Created</TableHead>
                                <TableHead class="w-32 text-right">Actions</TableHead>
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
                                        <p>No members yet.</p>
                                        <p class="text-sm">Invite people from any agent's Members tab and they'll show up here.</p>
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
                                class="hover:bg-muted/40 cursor-pointer"
                                @click="openSheet(member)"
                            >
                                <TableCell class="font-medium">
                                    <div class="flex items-center gap-3">
                                        <Avatar class="size-8 shrink-0">
                                            <AvatarImage v-if="member.avatar" :src="member.avatar" :alt="member.name" />
                                            <AvatarFallback class="bg-primary/10 text-primary text-xs font-semibold">
                                                {{ getInitials(member.name) }}
                                            </AvatarFallback>
                                        </Avatar>
                                        <span class="truncate">{{ member.name }}</span>
                                    </div>
                                </TableCell>
                                <TableCell>
                                    {{ member.email || 'No email' }}
                                </TableCell>
                                <TableCell>
                                    <span
                                        v-if="member.is_deactivated"
                                        class="inline-flex items-center rounded-full border border-red-200 bg-red-50 px-2 py-0.5 text-xs font-medium text-red-900 dark:border-red-900/50 dark:bg-red-950/30 dark:text-red-100"
                                    >
                                        Deactivated
                                    </span>
                                    <span
                                        v-else-if="member.status === 'pending'"
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
                                <TableCell class="text-right" @click.stop>
                                    <div class="flex items-center justify-end gap-1">
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            class="h-8 w-8 p-0"
                                            :title="`Send password reset link to ${member.name}`"
                                            :disabled="!member.email || member.status === 'pending'"
                                            @click="askReset(member)"
                                        >
                                            <KeyRound class="h-4 w-4" />
                                        </Button>
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            class="text-destructive hover:text-destructive h-8 w-8 p-0"
                                            :title="`Remove ${member.name}`"
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

            <!-- Reset password confirmation -->
            <AlertDialog v-model:open="resetOpen">
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>Send a password reset link?</AlertDialogTitle>
                        <AlertDialogDescription>
                            We'll email a reset link to
                            <span class="font-medium">{{ memberToReset?.email }}</span
                            >. They can set a new password from the link — you won't see the new value.
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel :disabled="isResetting">Cancel</AlertDialogCancel>
                        <AlertDialogAction :disabled="isResetting" @click="confirmReset">
                            <LoaderCircle v-if="isResetting" class="mr-2 h-4 w-4 animate-spin" />
                            Send link
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
                        <AlertDialogTitle>Remove member</AlertDialogTitle>
                        <AlertDialogDescription>
                            <span class="font-medium">{{ memberToDelete?.name }}</span> will be permanently deleted along with their access to every
                            agent in your workspace. This cannot be undone.
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel :disabled="isDeleting">Cancel</AlertDialogCancel>
                        <AlertDialogAction
                            class="bg-destructive text-destructive-foreground hover:bg-destructive/90"
                            :disabled="isDeleting"
                            @click="confirmDelete"
                        >
                            <LoaderCircle v-if="isDeleting" class="mr-2 h-4 w-4 animate-spin" />
                            Remove
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </SettingsLayout>
    </AppLayout>
</template>
