<script lang="ts" setup>
/**
 * Agent → Chat Log. Conversation list on the start side; on the other, the
 * selected conversation's search bar and tabs (messages, pulse, details, live
 * chat). Shared state lives in `useChatLog`; each panel is its own component.
 */
import LeadDetailsSheet from '@/components/LeadDetailsSheet.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { useTeamPermissions } from '@/composables/useTeamPermissions';
import AgentLayout from '@/layouts/AgentLayout.vue';
import { logs } from '@/routes/agents';
import { router, usePage } from '@inertiajs/vue3';
import { Activity, Info, MessageSquare, Phone, RefreshCcw, Search, Tag, Trash2, UserCheck, X } from 'lucide-vue-next';
import { computed, onMounted, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { toast } from 'vue-sonner';
import ChatLogFilters from './ChatLogFilters.vue';
import ChatMessages from './ChatMessages.vue';
import ConversationDetails from './ConversationDetails.vue';
import ConversationPulse from './ConversationPulse.vue';
import ConversationsSidebar from './ConversationsSidebar.vue';
import DeleteConversationDialog from './DeleteConversationDialog.vue';
import HandoffPanel from './HandoffPanel.vue';
import ReviseAnswerDialog from './ReviseAnswerDialog.vue';
import { useChatLog, type ChatLogTab } from './useChatLog';

type Message = Domain.Agent.Data.Resources.ChatLog.Messages.ChatLogMessageData;

const { t } = useI18n();
const page = usePage<{ handoffEnabled?: boolean; chatlogEnabled?: boolean }>();

const {
    agent,
    currentConversationId,
    conversationTitle,
    lead,
    activeTab,
    hasLeadForCurrentConversation,
    search,
    messages,
    messagesData,
    currentConversationData,
    fetchInitialData,
    fetchMessages,
    handoff,
} = useChatLog();

const { hasPermission } = useTeamPermissions();
const canViewPulse = computed(() => hasPermission('agent.chat.pulse'));
const canTakeover = computed(() => hasPermission('agent.chat.takeover'));
const canDeleteConversation = computed(() => hasPermission('agent.chat.delete'));

// Live chat needs the takeover permission and handoff switched on for the
// agent (one page prop). It only works for widget conversations: a person can stream replies
// into the embedded widget, but not into the hosted chat, the bar or Telegram.
const handoffEnabled = computed(() => Boolean(page.props.handoffEnabled));
const liveChatAvailable = computed(() => canTakeover.value && handoffEnabled.value && currentConversationData.value?.source === 'widget');

// When logging is off, a banner explains why no new chats appear (old ones still render).
const chatlogEnabled = computed(() => page.props.chatlogEnabled !== false);

const agentId = computed(() => agent.value?.external_id ?? '');

const title = computed(() =>
    conversationTitle.value ? t('agents.chatlog.title_with_conversation', { title: conversationTitle.value }) : t('agents.chatlog.title'),
);

const TAB_TRIGGER_CLASS =
    'text-muted-foreground hover:text-foreground data-[state=active]:text-foreground data-[state=active]:after:bg-primary relative cursor-pointer rounded-none bg-transparent px-4 py-2.5 text-sm font-medium transition-colors after:absolute after:start-0 after:end-0 after:bottom-0 after:h-0.5 after:bg-transparent data-[state=active]:bg-transparent data-[state=active]:shadow-none';

const showLeadPanel = ref(false);

function openLeadPanel() {
    if (hasLeadForCurrentConversation.value) showLeadPanel.value = true;
}

const isRefreshingMessages = ref(false);

async function refreshMessages() {
    if (!currentConversationId.value) return;

    isRefreshingMessages.value = true;
    try {
        await fetchMessages(currentConversationId.value);
    } finally {
        isRefreshingMessages.value = false;
    }
}

const showDeleteConversationDialog = ref(false);

function confirmDeleteConversation() {
    if (!currentConversationId.value) return;

    if (!canDeleteConversation.value) {
        toast.error(t('agents.chatlog.delete.permission_denied_title'), {
            description: t('agents.chatlog.delete.permission_denied_description'),
        });
        return;
    }

    showDeleteConversationDialog.value = true;
}

// Clear the deleted conversation, then reload the log without a selection
// (replace history, drop page state).
function onConversationDeleted() {
    currentConversationId.value = null;
    messagesData.value = null;
    lead.value = null;

    router.visit(logs.url(agentId.value), {
        preserveState: false,
        preserveScroll: false,
        replace: true,
    });
}

const showReviseDialog = ref(false);
const revisedMessage = ref<{ id: string; userMessage: string; botResponse: string } | null>(null);

function openReviseDialog(message: Message) {
    const index = messages.value.findIndex((candidate) => candidate.id === message.id);
    const prompt = index > 0 ? messages.value[index - 1] : null;

    revisedMessage.value = { id: message.id, userMessage: prompt?.content || '', botResponse: message.content };
    showReviseDialog.value = true;
}

function onAnswerRevised(messageId: string, qaId: number) {
    const revised = messagesData.value?.data?.find((candidate) => candidate.id === messageId);
    if (revised) revised.agent_question_answer_id = qaId;
}

onMounted(() => {
    void fetchInitialData();
});

watch(
    () => currentConversationId.value,
    (newId, oldId) => {
        if (newId && newId !== oldId) void fetchMessages(newId);
    },
);

// Fall back to the Chat tab if Live Chat disappears while selected (e.g. on
// switching to a hosted-chat conversation), so no empty panel stays open.
watch(liveChatAvailable, (available) => {
    if (!available && activeTab.value === 'handoff') {
        activeTab.value = 'chat';
    }
});

function onTabChange(tab: string | number) {
    activeTab.value = tab as ChatLogTab;
}
</script>

<template>
    <AgentLayout v-if="agent" :agent="agent" :title="title" hide-header no-padding full-height>
        <div class="flex h-full w-full flex-col">
            <Alert v-if="!chatlogEnabled" class="text-foreground flex-shrink-0 rounded-none border-x-0 border-t-0">
                <Info class="h-4 w-4" />
                <AlertTitle>{{ t('agents.chatlog.banner_off_title') }}</AlertTitle>
                <AlertDescription>
                    {{ t('agents.chatlog.banner_off_description') }}
                </AlertDescription>
            </Alert>

            <div class="relative min-h-0 flex-1">
                <div class="absolute inset-0 flex gap-0 overflow-hidden">
                    <ConversationsSidebar />

                    <div class="bg-background flex h-full flex-1 flex-col overflow-hidden">
                        <div class="flex-shrink-0 space-y-3 border-b px-5 py-4">
                            <div class="flex items-center gap-3">
                                <div class="relative max-w-md flex-1">
                                    <Search class="text-muted-foreground absolute start-3 top-1/2 h-4 w-4 -translate-y-1/2" />
                                    <Input
                                        v-model="search"
                                        :placeholder="t('agents.chatlog.search_placeholder')"
                                        class="bg-muted/50 h-9 border-0 ps-9 pe-9 focus-visible:ring-1"
                                        :disabled="!currentConversationId"
                                    />
                                    <button
                                        v-if="search"
                                        @click="search = ''"
                                        class="text-muted-foreground hover:text-foreground absolute end-3 top-1/2 -translate-y-1/2 transition-colors"
                                        :aria-label="t('agents.chatlog.messages.clear_search')"
                                    >
                                        <X class="h-4 w-4" />
                                    </button>
                                </div>

                                <Button
                                    variant="ghost"
                                    size="icon"
                                    class="h-9 w-9"
                                    @click="refreshMessages"
                                    :disabled="isRefreshingMessages || !currentConversationId"
                                    :aria-label="t('agents.chatlog.refresh_messages')"
                                >
                                    <RefreshCcw class="h-4 w-4" :class="{ 'animate-spin': isRefreshingMessages }" />
                                </Button>

                                <Button
                                    v-if="canDeleteConversation"
                                    variant="ghost"
                                    size="icon"
                                    class="text-destructive hover:text-destructive/80 h-9 w-9"
                                    @click="confirmDeleteConversation"
                                    :disabled="!currentConversationId"
                                    :aria-label="t('agents.chatlog.delete_conversation')"
                                >
                                    <Trash2 class="h-4 w-4" />
                                </Button>

                                <!-- Only when this conversation captured a lead -->
                                <Button v-if="hasLeadForCurrentConversation" size="sm" class="ms-auto h-9" variant="default" @click="openLeadPanel">
                                    <UserCheck class="me-1.5 h-4 w-4" /> {{ t('agents.chatlog.lead') }}
                                </Button>
                            </div>
                        </div>

                        <Tabs :model-value="activeTab" class="flex min-h-0 flex-1 flex-col overflow-hidden" @update:model-value="onTabChange">
                            <div class="flex-shrink-0 px-5">
                                <TabsList class="h-11 w-full justify-start gap-0 border-b bg-transparent p-0">
                                    <TabsTrigger value="chat" :class="TAB_TRIGGER_CLASS">
                                        <MessageSquare class="me-2 h-4 w-4" />
                                        {{ t('agents.chatlog.tabs.chat') }}
                                    </TabsTrigger>
                                    <TabsTrigger v-if="canViewPulse" value="pulse" :class="TAB_TRIGGER_CLASS">
                                        <Activity class="me-2 h-4 w-4" />
                                        {{ t('agents.chatlog.tabs.pulse') }}
                                    </TabsTrigger>
                                    <TabsTrigger value="details" :class="TAB_TRIGGER_CLASS">
                                        <Tag class="me-2 h-4 w-4" />
                                        {{ t('agents.chatlog.tabs.details') }}
                                    </TabsTrigger>
                                    <TabsTrigger
                                        v-if="liveChatAvailable"
                                        value="handoff"
                                        :class="[TAB_TRIGGER_CLASS, { 'text-amber-600 dark:text-amber-400': handoff.isHandedOff.value }]"
                                    >
                                        <Phone class="me-2 h-4 w-4" />
                                        {{ t('agents.chatlog.tabs.live_chat') }}
                                        <span v-if="handoff.userIsOnline.value" class="ms-1.5 h-2 w-2 animate-pulse rounded-full bg-green-500" />
                                    </TabsTrigger>
                                </TabsList>
                            </div>

                            <TabsContent value="chat" class="mt-0 flex min-h-0 flex-1 flex-col overflow-hidden">
                                <ChatMessages @open-revise="openReviseDialog" />
                            </TabsContent>

                            <TabsContent v-if="canViewPulse" value="pulse" class="mt-0 min-h-0 flex-1 overflow-auto">
                                <ConversationPulse />
                            </TabsContent>

                            <TabsContent value="details" class="mt-0 min-h-0 flex-1 overflow-auto">
                                <ConversationDetails />
                            </TabsContent>

                            <TabsContent v-if="liveChatAvailable" value="handoff" class="mt-0 flex min-h-0 flex-1 flex-col overflow-hidden">
                                <HandoffPanel />
                            </TabsContent>
                        </Tabs>
                    </div>
                </div>
            </div>
        </div>

        <LeadDetailsSheet v-model:open="showLeadPanel" :lead="lead" />

        <ReviseAnswerDialog
            v-model:open="showReviseDialog"
            :agent-id="agentId"
            :message-id="revisedMessage?.id ?? null"
            :user-message="revisedMessage?.userMessage ?? ''"
            :bot-response="revisedMessage?.botResponse ?? ''"
            @saved="onAnswerRevised"
        />

        <DeleteConversationDialog
            v-model:open="showDeleteConversationDialog"
            :agent-id="agentId"
            :conversation-id="currentConversationId"
            @deleted="onConversationDeleted"
        />

        <ChatLogFilters />
    </AgentLayout>
</template>
