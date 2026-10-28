<script setup lang="ts">
// Workspace People → Chatters. Lists every chatter in the workspace: invite new
// ones (with access to chosen protected agents), open a chatter's detail sheet
// (limits, agent access, deactivation), send a password reset or delete them.
import type { WorkspaceChatterRow } from '@/dashboard/api/chatters';
import ChatterDetailSheet from '@/dashboard/workspace/components/chatters/ChatterDetailSheet.vue';
import type { InvitableAgent } from '@/dashboard/components/chatters/types';
import AppLayout from '@/dashboard/layouts/AppLayout.vue';
import WorkspaceLayout from '@/dashboard/workspace/layouts/Layout.vue';
import { type BreadcrumbItem } from '@/shared/types';
import { Head } from '@inertiajs/vue3';
import { Users } from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import ChattersTable from './Chatters/ChattersTable.vue';
import ChattersToolbar from './Chatters/ChattersToolbar.vue';
import DeleteChatterDialog from './Chatters/DeleteChatterDialog.vue';
import InviteChatterDialog from './Chatters/InviteChatterDialog.vue';
import ResetPasswordDialog from './Chatters/ResetPasswordDialog.vue';
import { useWorkspaceChatters } from './Chatters/useWorkspaceChatters';

// Protected agents the owner can grant a new member access to (public agents
// have no per-member access, so they're excluded server-side).
const props = defineProps<{ agents: InvitableAgent[] }>();

const { t } = useI18n();

const breadcrumbs: BreadcrumbItem[] = [{ title: t('workspace.members.breadcrumb'), href: '/my/workspace/people/members' }];

const { members, isLoading, fetchMembers, searchQuery, filteredMembers, replaceMember, setAgentsCount, removeMember } = useWorkspaceChatters();

onMounted(fetchMembers);

// Detail sheet
const sheetOpen = ref(false);
const selectedMember = ref<WorkspaceChatterRow | null>(null);

const openSheet = (member: WorkspaceChatterRow) => {
    selectedMember.value = member;
    sheetOpen.value = true;
};

const closeSheet = (open: boolean) => {
    sheetOpen.value = open;
    if (!open) selectedMember.value = null;
};

// Keep the row's `agents_count` in sync as the sheet's toggles fire so the
// table reflects access changes immediately, no refetch needed.
const onAccessChanged = (member: WorkspaceChatterRow, attachedCount: number) => {
    setAgentsCount(member.external_id, attachedCount);
    if (selectedMember.value?.external_id === member.external_id) {
        selectedMember.value = { ...selectedMember.value, agents_count: attachedCount };
    }
};

// The sheet saved the message limit / deactivation and returned the fresh row.
const onUpdated = (updated: WorkspaceChatterRow) => {
    replaceMember(updated);
    if (selectedMember.value?.external_id === updated.external_id) {
        selectedMember.value = updated;
    }
};

// Invite dialog — with no protected agents, inviting is impossible (a member
// with no agent access can't chat with anything), so the button is disabled.
const inviteOpen = ref(false);
const hasInvitableAgents = computed(() => props.agents.length > 0);

// Reset password confirmation
const resetOpen = ref(false);
const memberToReset = ref<WorkspaceChatterRow | null>(null);

const askReset = (member: WorkspaceChatterRow) => {
    // Close the detail sheet first so the AlertDialog isn't stacked under
    // the sheet's overlay — both use z-50 and the sheet wins by mount order.
    sheetOpen.value = false;
    memberToReset.value = member;
    resetOpen.value = true;
};

// Delete confirmation
const deleteOpen = ref(false);
const memberToDelete = ref<WorkspaceChatterRow | null>(null);

const askDelete = (member: WorkspaceChatterRow) => {
    // Same z-index reason as askReset — close the sheet first.
    sheetOpen.value = false;
    memberToDelete.value = member;
    deleteOpen.value = true;
};

const onDeleted = (member: WorkspaceChatterRow) => {
    removeMember(member.external_id);
    memberToDelete.value = null;
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

                <ChattersToolbar
                    v-model:search="searchQuery"
                    :shown="filteredMembers.length"
                    :total="members.length"
                    :can-invite="hasInvitableAgents"
                    @invite="inviteOpen = true"
                />

                <ChattersTable
                    :members="filteredMembers"
                    :total="members.length"
                    :is-loading="isLoading"
                    :can-invite="hasInvitableAgents"
                    @open="openSheet"
                    @reset="askReset"
                    @delete="askDelete"
                    @invite="inviteOpen = true"
                />
            </div>

            <InviteChatterDialog v-model:open="inviteOpen" :agents="props.agents" @invited="fetchMembers" />

            <ResetPasswordDialog v-model:open="resetOpen" :member="memberToReset" @sent="memberToReset = null" />

            <!-- Detail sheet -->
            <ChatterDetailSheet
                :open="sheetOpen"
                :member="selectedMember"
                @update:open="closeSheet"
                @reset="askReset"
                @delete="askDelete"
                @access-changed="onAccessChanged"
                @updated="onUpdated"
            />

            <DeleteChatterDialog v-model:open="deleteOpen" :member="memberToDelete" @deleted="onDeleted" />
        </WorkspaceLayout>
    </AppLayout>
</template>
