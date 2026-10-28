<script setup lang="ts">
// Agent → Chatters tab. Lists every chatter in the workspace with their access
// to this agent: invite new chatters, grant or revoke access in one click, and
// open a chatter's sheet for the invitation link. Public agents have no
// per-chatter access, so they only get a notice.
import type { AgentChatterRow } from '@/dashboard/api/chatters';
import AgentLayout from '@/dashboard/agents/layouts/AgentLayout.vue';
import { Globe } from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import ChatterSheet from './Chatters/ChatterSheet.vue';
import ChattersTable from './Chatters/ChattersTable.vue';
import ChattersToolbar from './Chatters/ChattersToolbar.vue';
import InviteChatterDialog from './Chatters/InviteChatterDialog.vue';
import RevokeAccessDialog from './Chatters/RevokeAccessDialog.vue';
import { useAgentChatters } from './Chatters/useAgentChatters';

const { t } = useI18n();

interface Props {
    agent: Domain.Agent.Data.Resources.Account.AgentResourceData;
}

const props = defineProps<Props>();

const { members, isLoading, fetchMembers, searchQuery, filteredMembers, setAccess, grantingId, grantAccess } = useAgentChatters(() => props.agent);

onMounted(() => {
    fetchMembers();
});

// Invite dialog
const inviteOpen = ref(false);

// Revoke confirmation
const detachOpen = ref(false);
const memberToDetach = ref<AgentChatterRow | null>(null);

const askDetach = (member: AgentChatterRow) => {
    memberToDetach.value = member;
    detachOpen.value = true;
};

const onRevoked = (member: AgentChatterRow) => {
    // Keep the row visible but flip it to "no access" so it moves into the
    // lower group and the Grant action shows up.
    setAccess(member.external_id, false);
    memberToDetach.value = null;
};

// Member detail sheet. We track the id (not a snapshot) so the panel always
// reflects the latest row after a grant/revoke/resend refresh.
const memberSheetOpen = ref(false);
const selectedMemberId = ref<string | null>(null);
const selectedMember = computed<AgentChatterRow | null>(() => members.value.find((m) => m.external_id === selectedMemberId.value) ?? null);

const openMember = (member: AgentChatterRow) => {
    selectedMemberId.value = member.external_id;
    memberSheetOpen.value = true;
};

const revokeFromSheet = (member: AgentChatterRow) => {
    // Close the sheet before opening the confirm dialog so the two overlays
    // don't fight over focus.
    memberSheetOpen.value = false;
    askDetach(member);
};
</script>

<template>
    <AgentLayout :agent="agent" :title="t('agents.chatters.title')" :description="t('agents.chatters.description')">
        <div class="flex justify-center">
            <div class="w-full max-w-6xl">
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
                        <p class="font-medium text-blue-900 dark:text-blue-100">{{ t('agents.chatters.public_title') }}</p>
                        <p class="text-blue-800/80 dark:text-blue-200/80">
                            {{ t('agents.chatters.public_description') }}
                        </p>
                    </div>
                </div>

                <div v-else class="space-y-4">
                    <ChattersToolbar
                        v-model:search="searchQuery"
                        :shown="filteredMembers.length"
                        :total="members.length"
                        @invite="inviteOpen = true"
                    />

                    <ChattersTable
                        :members="filteredMembers"
                        :total="members.length"
                        :is-loading="isLoading"
                        :granting-id="grantingId"
                        @open="openMember"
                        @grant="grantAccess"
                        @revoke="askDetach"
                    />
                </div>
            </div>
        </div>

        <InviteChatterDialog v-model:open="inviteOpen" :agent-id="agent.external_id" @invited="fetchMembers" />

        <ChatterSheet
            v-model:open="memberSheetOpen"
            :agent-id="agent.external_id"
            :member="selectedMember"
            :granting-id="grantingId"
            @grant="grantAccess"
            @revoke="revokeFromSheet"
            @resent="fetchMembers"
        />

        <RevokeAccessDialog v-model:open="detachOpen" :agent-id="agent.external_id" :member="memberToDetach" @revoked="onRevoked" />
    </AgentLayout>
</template>
