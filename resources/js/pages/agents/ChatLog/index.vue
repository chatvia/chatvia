<script lang="ts" setup>
import { ref, computed, onMounted, watch } from 'vue';
import AgentLayout from '@/layouts/AgentLayout.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter } from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { LoaderCircle, Search, MessageSquare, RefreshCcw, X, UserCheck, Tag, Activity, Phone } from 'lucide-vue-next';
import LeadDetailsSheet from '@/components/LeadDetailsSheet.vue';
import axios from 'axios';

// Sub-components
import ConversationsSidebar from './ConversationsSidebar.vue';
import ChatMessages from './ChatMessages.vue';
import ConversationDetails from './ConversationDetails.vue';
import ConversationPulse from './ConversationPulse.vue';
import ChatLogFilters from './ChatLogFilters.vue';
import HandoffPanel from './HandoffPanel.vue';

// Composable
import { useChatLog } from './useChatLog';

type Message = App.Data.Resources.Agent.ChatLog.ChatLogMessageData;

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

// Page title
const title = computed(() => conversationTitle.value ? `Chat Log – ${conversationTitle.value}` : 'Chat Log');

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
  const messageIndex = messages.value.findIndex(m => m.id === message.id);
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
      expected_response: reviseExpectedResponse.value
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
    alert(error.response?.data?.message || 'Failed to save Q&A entry');
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
watch(() => currentConversationId.value, (newId, oldId) => {
  if (newId && newId !== oldId) {
    fetchMessages(newId);
  }
});
</script>

<template>
  <AgentLayout :agent="agent" :title="title" hide-header no-padding full-height>
    <div class="relative h-full w-full">
      <div class="flex gap-0 absolute inset-0 overflow-hidden">
        <!-- Conversations Sidebar -->
        <ConversationsSidebar />

        <!-- Messages Column -->
        <div class="flex-1 flex flex-col h-full overflow-hidden bg-background">
          <!-- Header -->
          <div class="px-5 py-4 flex-shrink-0 border-b space-y-3">
            <div class="flex items-center gap-3">
              <!-- Search Bar -->
              <div class="relative flex-1 max-w-md">
                <Search class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
                <Input 
                  v-model="search" 
                  placeholder="Search messages..." 
                  class="pl-9 h-9 pr-9 bg-muted/50 border-0 focus-visible:ring-1" 
                  :disabled="!currentConversationId"
                />
                <button
                  v-if="search"
                  @click="clearSearch"
                  class="absolute right-3 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground transition-colors"
                >
                  <X class="h-4 w-4" />
                </button>
              </div>
              
              <!-- Refresh Messages -->
              <Button variant="ghost" size="icon" class="h-9 w-9" @click="refreshMessages" :disabled="isRefreshingMessages || !currentConversationId">
                <RefreshCcw class="h-4 w-4" :class="{ 'animate-spin': isRefreshingMessages }" />
              </Button>
              
              <!-- Lead Button -->
              <Button 
                size="sm" 
                class="h-9 ml-auto"
                :variant="hasLeadForCurrentConversation ? 'default' : 'outline'" 
                :disabled="!hasLeadForCurrentConversation" 
                @click="openLeadPanel"
              >
                <UserCheck class="h-4 w-4 mr-1.5" /> Lead
              </Button>
            </div>
          </div>

          <!-- Tabs -->
          <Tabs v-model="activeTab" class="flex-1 flex flex-col min-h-0 overflow-hidden">
            <div class="px-5 flex-shrink-0">
              <TabsList class="h-11 w-full justify-start bg-transparent p-0 gap-0 border-b">
                <TabsTrigger 
                  value="chat" 
                  class="relative px-4 py-2.5 rounded-none bg-transparent text-muted-foreground hover:text-foreground transition-colors data-[state=active]:text-foreground data-[state=active]:bg-transparent data-[state=active]:shadow-none font-medium text-sm cursor-pointer after:absolute after:bottom-0 after:left-0 after:right-0 after:h-0.5 after:bg-transparent data-[state=active]:after:bg-primary"
                >
                  <MessageSquare class="h-4 w-4 mr-2" />
                  Chat
                </TabsTrigger>
                <TabsTrigger 
                  value="pulse" 
                  class="relative px-4 py-2.5 rounded-none bg-transparent text-muted-foreground hover:text-foreground transition-colors data-[state=active]:text-foreground data-[state=active]:bg-transparent data-[state=active]:shadow-none font-medium text-sm cursor-pointer after:absolute after:bottom-0 after:left-0 after:right-0 after:h-0.5 after:bg-transparent data-[state=active]:after:bg-primary"
                >
                  <Activity class="h-4 w-4 mr-2" />
                  Pulse
                </TabsTrigger>
                <TabsTrigger 
                  value="details" 
                  class="relative px-4 py-2.5 rounded-none bg-transparent text-muted-foreground hover:text-foreground transition-colors data-[state=active]:text-foreground data-[state=active]:bg-transparent data-[state=active]:shadow-none font-medium text-sm cursor-pointer after:absolute after:bottom-0 after:left-0 after:right-0 after:h-0.5 after:bg-transparent data-[state=active]:after:bg-primary"
                >
                  <Tag class="h-4 w-4 mr-2" />
                  Details
                </TabsTrigger>
                <TabsTrigger 
                  value="handoff" 
                  class="relative px-4 py-2.5 rounded-none bg-transparent text-muted-foreground hover:text-foreground transition-colors data-[state=active]:text-foreground data-[state=active]:bg-transparent data-[state=active]:shadow-none font-medium text-sm cursor-pointer after:absolute after:bottom-0 after:left-0 after:right-0 after:h-0.5 after:bg-transparent data-[state=active]:after:bg-primary"
                  :class="{ 'text-amber-600 dark:text-amber-400': handoff.isHandedOff.value }"
                >
                  <Phone class="h-4 w-4 mr-2" />
                  Live Chat
                  <span 
                    v-if="handoff.userIsOnline.value" 
                    class="ml-1.5 w-2 h-2 rounded-full bg-green-500 animate-pulse"
                  />
                </TabsTrigger>
              </TabsList>
            </div>

            <!-- Chat Tab Content -->
            <TabsContent value="chat" class="flex-1 flex flex-col min-h-0 mt-0 overflow-hidden">
              <ChatMessages @open-revise="openReviseDialog" />
            </TabsContent>

            <!-- Pulse Tab Content -->
            <TabsContent value="pulse" class="flex-1 min-h-0 mt-0 overflow-auto">
              <ConversationPulse />
            </TabsContent>

            <!-- Details Tab Content -->
            <TabsContent value="details" class="flex-1 min-h-0 mt-0 overflow-auto">
              <ConversationDetails />
            </TabsContent>

            <!-- Handoff/Live Chat Tab Content -->
            <TabsContent value="handoff" class="flex-1 flex flex-col min-h-0 mt-0 overflow-hidden">
              <HandoffPanel />
            </TabsContent>
          </Tabs>
        </div>
      </div>
    </div>

    <!-- Lead Details Sheet -->
    <LeadDetailsSheet 
      v-model:open="showLeadPanel" 
      :lead="lead" 
    />

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
          <Button variant="outline" @click="cancelRevise" :disabled="isUpdatingAnswer">
            Cancel
          </Button>
          <Button @click="updateAnswer" :disabled="!reviseExpectedResponse.trim() || isUpdatingAnswer">
            <LoaderCircle v-if="isUpdatingAnswer" class="h-4 w-4 mr-2 animate-spin" />
            {{ isUpdatingAnswer ? 'Saving...' : 'Save as Q&A' }}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>

    <!-- Filters Dialog -->
    <ChatLogFilters />
  </AgentLayout>
</template>

