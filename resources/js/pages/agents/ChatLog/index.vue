<script lang="ts" setup>
import LeadDetailsSheet from '@/components/LeadDetailsSheet.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Textarea } from '@/components/ui/textarea';
import { useTeamPermissions } from '@/composables/useTeamPermissions';
import AgentLayout from '@/layouts/AgentLayout.vue';
import { router, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import { Activity, Info, LoaderCircle, MessageSquare, Phone, RefreshCcw, Search, Tag, Trash2, UserCheck, X } from 'lucide-vue-next';
import { computed, onMounted, ref, watch } from 'vue';
import { toast } from 'vue-sonner';

// Sub-components
import ChatLogFilters from './ChatLogFilters.vue';
import ChatMessages from './ChatMessages.vue';
import ConversationDetails from './ConversationDetails.vue';
import ConversationPulse from './ConversationPulse.vue';
import ConversationsSidebar from './ConversationsSidebar.vue';
import HandoffPanel from './HandoffPanel.vue';

// Composable
import { useChatLog } from './useChatLog';

type Message = Domain.Agent.Data.Resources.ChatLog.Messages.ChatLogMessageData;

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
    fetchInitialData,
    fetchMessages,
    handoff,
} = useChatLog();

// Permission checks
const { hasPermission } = useTeamPermissions();
const canViewPulse = computed(() => hasPermission('agent.chat.pulse'));
const canTakeover = computed(() => hasPermission('agent.chat.takeover'));
const canDeleteConversation = computed(() => hasPermission('agent.chat.delete'));

// Live Chat (handoff) is widget-only + gated by the handoff capability; the
// controller resolves both into a single flag passed as a page prop.
const handoffEnabled = computed(() => Boolean(usePage().props.handoffEnabled));

// Whether conversations are still being logged for this agent. When off we show
// a banner so the user understands why no new chats are appearing (existing
// ones still render).
const chatlogEnabled = computed(() => usePage().props.chatlogEnabled !== false);

// Page title
const title = computed(() => (conversationTitle.value ? `Chat Log – ${conversationTitle.value}` : 'Chat Log'));

// Lead capture is an opt-in MCP server (slug `leads`) on the agent. When it's
// off, no leads are ever captured, so the Lead button is hidden entirely.
const leadsEnabled = computed(() => Boolean(agent.value?.leads_mcp_enabled));

// Lead panel
const showLeadPanel = ref(false);
function openLeadPanel() {
    if (!hasLeadForCurrentConversation.value) return;
    showLeadPanel.value = true;
}

// Refresh messages
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

// Delete conversation
const showDeleteConversationDialog = ref(false);
const isDeletingConversation = ref(false);

function confirmDeleteConversation() {
    if (!currentConversationId.value) return;

    if (!canDeleteConversation.value) {
        toast.error('Permission denied', {
            description: 'You do not have permission to delete conversations.',
        });
        return;
    }

    showDeleteConversationDialog.value = true;
}

async function deleteConversation() {
    if (!currentConversationId.value || isDeletingConversation.value) return;

    isDeletingConversation.value = true;
    try {
        await axios.delete(
            route('agents.logs.delete-conversation', {
                agent: agent.value.external_id,
                conversation: currentConversationId.value,
            }),
        );

        toast.success('Conversation deleted successfully');
        showDeleteConversationDialog.value = false;

        // Clear the current conversation state before redirecting
        currentConversationId.value = null;
        messagesData.value = null;
        lead.value = null;

        // Redirect to logs page without conversation selected
        // Use replace: true to replace history and preserveState: false to clear state
        router.visit(route('agents.logs', { agent: agent.value.external_id }), {
            preserveState: false,
            preserveScroll: false,
            replace: true,
        });
    } catch (error: any) {
        console.error('Failed to delete conversation:', error);
        toast.error('Failed to delete conversation', {
            description: error.response?.data?.message || 'An error occurred while deleting the conversation.',
        });
    } finally {
        isDeletingConversation.value = false;
    }
}

// Search
function clearSearch() {
    search.value = '';
}

// Revise answer dialog
const showReviseDialog = ref(false);
const reviseUserMessage = ref('');
const reviseBotResponse = ref('');
const reviseExpectedResponse = ref('');
const currentReviseMessageId = ref<string | null>(null);
const isUpdatingAnswer = ref(false);

function openReviseDialog(message: Message) {
    const messageIndex = messages.value.findIndex((m) => m.id === message.id);
    const userMessage = messageIndex > 0 ? messages.value[messageIndex - 1] : null;

    reviseUserMessage.value = userMessage?.content || '';
    reviseBotResponse.value = message.content;
    reviseExpectedResponse.value = '';
    currentReviseMessageId.value = message.id;
    showReviseDialog.value = true;
}

async function updateAnswer() {
    if (!reviseExpectedResponse.value.trim() || !reviseUserMessage.value.trim() || !currentReviseMessageId.value) return;

    isUpdatingAnswer.value = true;
    try {
        const response = await axios.post(route('agents.logs.revise-answer', { agent: agent.value.external_id }), {
            message_id: currentReviseMessageId.value,
            user_message: reviseUserMessage.value,
            expected_response: reviseExpectedResponse.value,
        });

        if (messagesData.value?.data) {
            const originalIndex = messagesData.value.data.findIndex((m: Message) => m.id === currentReviseMessageId.value);
            if (originalIndex !== -1) {
                messagesData.value.data[originalIndex].agent_question_answer_id = response.data.qa_id;
            }
        }

        showReviseDialog.value = false;
        reviseUserMessage.value = '';
        reviseBotResponse.value = '';
        reviseExpectedResponse.value = '';
        currentReviseMessageId.value = null;
    } catch (error: any) {
        console.error('Failed to save Q&A:', error);
        toast.error(error.response?.data?.message || 'Failed to save Q&A entry');
    } finally {
        isUpdatingAnswer.value = false;
    }
}

function cancelRevise() {
    showReviseDialog.value = false;
    reviseUserMessage.value = '';
    reviseBotResponse.value = '';
    reviseExpectedResponse.value = '';
    currentReviseMessageId.value = null;
}

// Initialize data
onMounted(() => {
    fetchInitialData();
});

// Watch for conversation changes to update URL
watch(
    () => currentConversationId.value,
    (newId, oldId) => {
        if (newId && newId !== oldId) {
            fetchMessages(newId);
        }
    },
);
</script>

<template>
    <AgentLayout :agent="agent" :title="title" hide-header no-padding full-height>
        <div class="flex h-full w-full flex-col">
            <!-- Chat log disabled banner — shown when the owner switched logging
                 off (Setup → Settings). Existing conversations still render below. -->
            <Alert v-if="!chatlogEnabled" class="text-foreground flex-shrink-0 rounded-none border-x-0 border-t-0">
                <Info class="h-4 w-4" />
                <AlertTitle>Chat log is off</AlertTitle>
                <AlertDescription>
                    New conversations aren't being saved. Turn the chat log back on in Setup → Settings to resume logging — existing conversations are
                    still shown below.
                </AlertDescription>
            </Alert>

            <div class="relative min-h-0 flex-1">
                <div class="absolute inset-0 flex gap-0 overflow-hidden">
                    <!-- Conversations Sidebar -->
                    <ConversationsSidebar />

                    <!-- Messages Column -->
                    <div class="bg-background flex h-full flex-1 flex-col overflow-hidden">
                        <!-- Header -->
                        <div class="flex-shrink-0 space-y-3 border-b px-5 py-4">
                            <div class="flex items-center gap-3">
                                <!-- Search Bar -->
                                <div class="relative max-w-md flex-1">
                                    <Search class="text-muted-foreground absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2" />
                                    <Input
                                        v-model="search"
                                        placeholder="Search messages..."
                                        class="bg-muted/50 h-9 border-0 pr-9 pl-9 focus-visible:ring-1"
                                        :disabled="!currentConversationId"
                                    />
                                    <button
                                        v-if="search"
                                        @click="clearSearch"
                                        class="text-muted-foreground hover:text-foreground absolute top-1/2 right-3 -translate-y-1/2 transition-colors"
                                    >
                                        <X class="h-4 w-4" />
                                    </button>
                                </div>

                                <!-- Refresh Messages -->
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    class="h-9 w-9"
                                    @click="refreshMessages"
                                    :disabled="isRefreshingMessages || !currentConversationId"
                                >
                                    <RefreshCcw class="h-4 w-4" :class="{ 'animate-spin': isRefreshingMessages }" />
                                </Button>

                                <!-- Delete Conversation -->
                                <Button
                                    v-if="canDeleteConversation"
                                    variant="ghost"
                                    size="icon"
                                    class="text-destructive hover:text-destructive/80 h-9 w-9"
                                    @click="confirmDeleteConversation"
                                    :disabled="!currentConversationId"
                                >
                                    <Trash2 class="h-4 w-4" />
                                </Button>

                                <!-- Lead Button -->
                                <Button
                                    v-if="leadsEnabled"
                                    size="sm"
                                    class="ml-auto h-9"
                                    :variant="hasLeadForCurrentConversation ? 'default' : 'outline'"
                                    :disabled="!hasLeadForCurrentConversation"
                                    @click="openLeadPanel"
                                >
                                    <UserCheck class="mr-1.5 h-4 w-4" /> Lead
                                </Button>
                            </div>
                        </div>

                        <!-- Tabs -->
                        <Tabs v-model="activeTab" class="flex min-h-0 flex-1 flex-col overflow-hidden">
                            <div class="flex-shrink-0 px-5">
                                <TabsList class="h-11 w-full justify-start gap-0 border-b bg-transparent p-0">
                                    <TabsTrigger
                                        value="chat"
                                        class="text-muted-foreground hover:text-foreground data-[state=active]:text-foreground data-[state=active]:after:bg-primary relative cursor-pointer rounded-none bg-transparent px-4 py-2.5 text-sm font-medium transition-colors after:absolute after:right-0 after:bottom-0 after:left-0 after:h-0.5 after:bg-transparent data-[state=active]:bg-transparent data-[state=active]:shadow-none"
                                    >
                                        <MessageSquare class="mr-2 h-4 w-4" />
                                        Chat
                                    </TabsTrigger>
                                    <TabsTrigger
                                        v-if="canViewPulse"
                                        value="pulse"
                                        class="text-muted-foreground hover:text-foreground data-[state=active]:text-foreground data-[state=active]:after:bg-primary relative cursor-pointer rounded-none bg-transparent px-4 py-2.5 text-sm font-medium transition-colors after:absolute after:right-0 after:bottom-0 after:left-0 after:h-0.5 after:bg-transparent data-[state=active]:bg-transparent data-[state=active]:shadow-none"
                                    >
                                        <Activity class="mr-2 h-4 w-4" />
                                        Pulse
                                    </TabsTrigger>
                                    <TabsTrigger
                                        value="details"
                                        class="text-muted-foreground hover:text-foreground data-[state=active]:text-foreground data-[state=active]:after:bg-primary relative cursor-pointer rounded-none bg-transparent px-4 py-2.5 text-sm font-medium transition-colors after:absolute after:right-0 after:bottom-0 after:left-0 after:h-0.5 after:bg-transparent data-[state=active]:bg-transparent data-[state=active]:shadow-none"
                                    >
                                        <Tag class="mr-2 h-4 w-4" />
                                        Details
                                    </TabsTrigger>
                                    <TabsTrigger
                                        v-if="canTakeover && handoffEnabled"
                                        value="handoff"
                                        class="text-muted-foreground hover:text-foreground data-[state=active]:text-foreground data-[state=active]:after:bg-primary relative cursor-pointer rounded-none bg-transparent px-4 py-2.5 text-sm font-medium transition-colors after:absolute after:right-0 after:bottom-0 after:left-0 after:h-0.5 after:bg-transparent data-[state=active]:bg-transparent data-[state=active]:shadow-none"
                                        :class="{ 'text-amber-600 dark:text-amber-400': handoff.isHandedOff.value }"
                                    >
                                        <Phone class="mr-2 h-4 w-4" />
                                        Live Chat
                                        <span v-if="handoff.userIsOnline.value" class="ml-1.5 h-2 w-2 animate-pulse rounded-full bg-green-500" />
                                    </TabsTrigger>
                                </TabsList>
                            </div>

                            <!-- Chat Tab Content -->
                            <TabsContent value="chat" class="mt-0 flex min-h-0 flex-1 flex-col overflow-hidden">
                                <ChatMessages @open-revise="openReviseDialog" />
                            </TabsContent>

                            <!-- Pulse Tab Content -->
                            <TabsContent v-if="canViewPulse" value="pulse" class="mt-0 min-h-0 flex-1 overflow-auto">
                                <ConversationPulse />
                            </TabsContent>

                            <!-- Details Tab Content -->
                            <TabsContent value="details" class="mt-0 min-h-0 flex-1 overflow-auto">
                                <ConversationDetails />
                            </TabsContent>

                            <!-- Handoff/Live Chat Tab Content -->
                            <TabsContent
                                v-if="canTakeover && handoffEnabled"
                                value="handoff"
                                class="mt-0 flex min-h-0 flex-1 flex-col overflow-hidden"
                            >
                                <HandoffPanel />
                            </TabsContent>
                        </Tabs>
                    </div>
                </div>
            </div>
        </div>

        <!-- Lead Details Sheet -->
        <LeadDetailsSheet v-model:open="showLeadPanel" :lead="lead" />

        <!-- Revise Answer Dialog -->
        <Dialog v-model:open="showReviseDialog">
            <DialogContent class="max-w-xl">
                <DialogHeader>
                    <DialogTitle>Improve answer</DialogTitle>
                </DialogHeader>

                <div class="space-y-4 py-4">
                    <div class="space-y-2">
                        <Label for="user-message" class="text-sm font-medium">User message</Label>
                        <Textarea
                            id="user-message"
                            v-model="reviseUserMessage"
                            placeholder="Enter user message..."
                            class="min-h-[100px] resize-none"
                            disabled
                        />
                    </div>

                    <div class="space-y-2">
                        <Label for="bot-response" class="text-sm font-medium">Bot response</Label>
                        <Textarea
                            id="bot-response"
                            v-model="reviseBotResponse"
                            placeholder="Enter bot response..."
                            class="min-h-[120px] resize-none"
                            disabled
                        />
                    </div>

                    <div class="space-y-2">
                        <Label for="expected-response" class="text-sm font-medium">Expected response</Label>
                        <Textarea
                            id="expected-response"
                            v-model="reviseExpectedResponse"
                            placeholder="Enter the expected response..."
                            class="min-h-[150px] resize-none"
                        />
                    </div>
                </div>

                <DialogFooter>
                    <Button variant="outline" @click="cancelRevise" :disabled="isUpdatingAnswer"> Cancel </Button>
                    <Button @click="updateAnswer" :disabled="!reviseExpectedResponse.trim() || isUpdatingAnswer">
                        <LoaderCircle v-if="isUpdatingAnswer" class="mr-2 h-4 w-4 animate-spin" />
                        {{ isUpdatingAnswer ? 'Saving...' : 'Save as Q&A' }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Delete Conversation Dialog -->
        <Dialog v-model:open="showDeleteConversationDialog">
            <DialogContent class="max-w-md">
                <DialogHeader>
                    <DialogTitle>Delete Conversation</DialogTitle>
                    <DialogDescription> Are you sure you want to delete this conversation? This action cannot be undone. </DialogDescription>
                </DialogHeader>

                <DialogFooter>
                    <Button variant="outline" @click="showDeleteConversationDialog = false" :disabled="isDeletingConversation"> Cancel </Button>
                    <Button variant="destructive" @click="deleteConversation" :disabled="isDeletingConversation">
                        <LoaderCircle v-if="isDeletingConversation" class="mr-2 h-4 w-4 animate-spin" />
                        {{ isDeletingConversation ? 'Deleting...' : 'Delete' }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Filters Dialog -->
        <ChatLogFilters />
    </AgentLayout>
</template>
