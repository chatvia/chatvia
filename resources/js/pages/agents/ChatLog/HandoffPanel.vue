<script lang="ts" setup>
import { ref, computed, watch, nextTick, onMounted } from 'vue';
import { Button } from '@/components/ui/button';
import { Textarea } from '@/components/ui/textarea';
import { ScrollArea } from '@/components/ui/scroll-area';
import { Badge } from '@/components/ui/badge';
import { 
  LoaderCircle, 
  Send, 
  PhoneOff, 
  Phone,
  User,
  Bot,
  Circle,
  MessageSquare
} from 'lucide-vue-next';
import MessageCard from '@/components/chat/MessageCard.vue';
import { useChatLog } from './useChatLog';

const {
  currentConversationId,
  messagesData,
  messages,
  handoff,
  fetchMessages,
  appendMessage,
  formatTimeAgo,
} = useChatLog();

const messageInput = ref('');
const messagesContainer = ref<any>(null);
const scrollAnchor = ref<HTMLElement | null>(null);
const isTyping = ref(false);
let typingDebounce: ReturnType<typeof setTimeout> | null = null;

// Handle message input typing
function handleTyping() {
  if (!handoff.isHandedOff.value) return;
  
  if (!isTyping.value) {
    isTyping.value = true;
    handoff.sendTyping(true);
  }
  
  if (typingDebounce) clearTimeout(typingDebounce);
  typingDebounce = setTimeout(() => {
    isTyping.value = false;
    handoff.sendTyping(false);
  }, 1000);
}

// Send message
async function sendMessage() {
  if (!messageInput.value.trim() || handoff.isSendingMessage.value) return;
  
  const content = messageInput.value.trim();
  messageInput.value = '';
  
  try {
    await handoff.sendMessage(content);
    // Refresh messages to show the new one
    if (currentConversationId.value) {
      await fetchMessages(currentConversationId.value);
      scrollToBottom();
    }
  } catch (err) {
    // Error already handled in composable
    messageInput.value = content; // Restore message on error
  }
}

// Take over conversation
async function handleTakeOver() {
  try {
    await handoff.takeOver();
    // Refresh messages
    if (currentConversationId.value) {
      await fetchMessages(currentConversationId.value);
      scrollToBottom();
    }
  } catch (err) {
    // Error handled in composable
  }
}

// Return to AI
async function handleReturn() {
  try {
    await handoff.returnToAi();
  } catch (err) {
    // Error handled in composable
  }
}

function scrollToBottom() {
  nextTick(() => {
    // Use scroll anchor - most reliable method
    if (scrollAnchor.value) {
      scrollAnchor.value.scrollIntoView({ behavior: 'smooth', block: 'end' });
    }
  });
}

// Watch for new messages from WebSocket
watch(() => handoff.newMessageReceived.value, (newMsg) => {
  if (newMsg && currentConversationId.value) {
    console.log('[HandoffPanel] New message received via WebSocket:', newMsg);
    // Append message directly to the local state (no server refetch needed)
    appendMessage(newMsg);
    scrollToBottom();
    
    // Mark user messages as read when we receive them
    if (newMsg.role === 'user') {
      handoff.markMessagesAsRead();
    }
  }
});

// Watch for messages count change
watch(() => messages.value.length, () => {
  scrollToBottom();
});

// Watch for typing state changes to scroll
watch(() => handoff.userIsTyping.value, (isTyping) => {
  if (isTyping) {
    scrollToBottom();
  }
});

// Scroll to bottom on mount
onMounted(() => {
  setTimeout(scrollToBottom, 100);
});
</script>

<template>
  <div class="flex flex-col h-full">
    <!-- Status Bar -->
    <div class="px-4 py-3 border-b bg-muted/30 flex items-center justify-between gap-3">
      <div class="flex items-center gap-3">
        <!-- Online Status -->
        <div class="flex items-center gap-2">
          <Circle 
            class="h-2.5 w-2.5 fill-current" 
            :class="handoff.userIsOnline.value ? 'text-green-500' : 'text-gray-400'"
          />
          <span class="text-sm font-medium">
            {{ handoff.onlineUser.value?.name || 'Visitor' }}
          </span>
          <Badge 
            :variant="handoff.userIsOnline.value ? 'default' : 'secondary'"
            class="text-[10px] px-1.5 py-0"
          >
            {{ handoff.userIsOnline.value ? 'Online' : 'Offline' }}
          </Badge>
        </div>

        <!-- Typing Indicator -->
        <div 
          v-if="handoff.userIsTyping.value" 
          class="flex items-center gap-1.5 text-xs text-muted-foreground animate-pulse"
        >
          <span class="flex gap-0.5">
            <span class="w-1.5 h-1.5 bg-current rounded-full animate-bounce" style="animation-delay: 0ms"></span>
            <span class="w-1.5 h-1.5 bg-current rounded-full animate-bounce" style="animation-delay: 150ms"></span>
            <span class="w-1.5 h-1.5 bg-current rounded-full animate-bounce" style="animation-delay: 300ms"></span>
          </span>
          <span>typing...</span>
        </div>
      </div>

      <!-- Handoff Status & Actions -->
      <div class="flex items-center gap-2">
        <Badge 
          :variant="handoff.isHandedOff.value ? 'default' : 'outline'"
          :class="handoff.isHandedOff.value ? 'bg-amber-500 hover:bg-amber-600' : ''"
        >
          {{ handoff.isHandedOff.value ? 'Live Chat Active' : 'AI Mode' }}
        </Badge>

        <Button
          v-if="!handoff.isHandedOff.value"
          size="sm"
          @click="handleTakeOver"
          :disabled="handoff.isLoading.value || !currentConversationId || !handoff.userIsOnline.value"
          class="gap-1.5"
          :title="!handoff.userIsOnline.value ? 'User is offline' : ''"
        >
          <LoaderCircle v-if="handoff.isLoading.value" class="h-3.5 w-3.5 animate-spin" />
          <Phone v-else class="h-3.5 w-3.5" />
          Take Over
        </Button>

        <Button
          v-else
          size="sm"
          variant="outline"
          @click="handleReturn"
          :disabled="handoff.isLoading.value"
          class="gap-1.5"
        >
          <LoaderCircle v-if="handoff.isLoading.value" class="h-3.5 w-3.5 animate-spin" />
          <PhoneOff v-else class="h-3.5 w-3.5" />
          Return to AI
        </Button>
      </div>
    </div>

    <!-- Error Display -->
    <div v-if="handoff.error.value" class="px-4 py-2 bg-destructive/10 text-destructive text-sm">
      {{ handoff.error.value }}
    </div>

    <!-- Empty State (not handed off) -->
    <div v-if="!handoff.isHandedOff.value" class="flex-1 flex flex-col items-center justify-center text-muted-foreground p-8">
      <div class="w-16 h-16 rounded-full bg-muted flex items-center justify-center mb-4">
        <Bot class="h-8 w-8" />
      </div>
      <h3 class="text-lg font-medium mb-2">AI is handling this conversation</h3>
      <p class="text-sm text-center max-w-md mb-4">
        Click "Take Over" to join the conversation and chat directly with the user. 
        The AI will stop responding while you're in control.
      </p>
      <div class="flex items-center gap-2 text-xs">
        <Circle 
          class="h-2 w-2 fill-current" 
          :class="handoff.userIsOnline.value ? 'text-green-500' : 'text-gray-400'"
        />
        <span>User is {{ handoff.userIsOnline.value ? 'currently online' : 'offline' }}</span>
      </div>
    </div>

    <!-- Chat Interface (handed off) -->
    <template v-else>
      <!-- Messages -->
      <ScrollArea ref="messagesContainer" class="flex-1 h-0">
        <div v-if="!currentConversationId" class="h-full flex flex-col items-center justify-center text-muted-foreground py-20">
          <MessageSquare class="h-10 w-10 mb-3 opacity-30" />
          <p class="text-sm">Select a conversation</p>
        </div>
        <div v-else class="px-6 py-5 space-y-5">
          <div v-for="m in messages" :key="m.id">
            <div 
              class="flex"
              :class="m.role === 'user' ? 'justify-end' : 'justify-start'"
            >
              <div 
                class="flex flex-col gap-2 max-w-[75%]"
                :class="m.role === 'user' ? 'items-end' : 'items-start'"
              >
                <!-- Role indicator -->
                <div class="flex items-center gap-1.5 text-[10px] text-muted-foreground px-1">
                  <User v-if="m.role === 'user'" class="h-3 w-3" />
                  <Bot v-else-if="m.role === 'assistant'" class="h-3 w-3" />
                  <User v-else class="h-3 w-3 text-amber-500" />
                  <span :class="m.role === 'agent' ? 'text-amber-600 font-medium' : ''">
                    {{ m.role === 'user' ? 'Visitor' : m.role === 'agent' ? 'You' : 'AI' }}
                  </span>
                </div>

                <!-- Message Bubble -->
                <div 
                  class="rounded-2xl px-4 py-3"
                  :class="{
                    'bg-primary text-primary-foreground rounded-br-md': m.role === 'user',
                    'bg-muted/60 rounded-bl-md': m.role === 'assistant',
                    'bg-amber-100 dark:bg-amber-900/30 rounded-bl-md border border-amber-200 dark:border-amber-800': m.role === 'agent'
                  }"
                >
                  <div v-if="m.role === 'user'" class="text-base whitespace-pre-wrap break-words leading-relaxed">{{ m.content }}</div>
                  <MessageCard v-else :message="m" :hide-feedback="true" />
                </div>

                <!-- Time and Read Status -->
                <div class="flex items-center gap-1.5 text-[10px] text-muted-foreground px-1">
                  <span>{{ formatTimeAgo(m.created_at) }}</span>
                  <!-- Read status for agent messages -->
                  <span v-if="m.role === 'agent'" class="flex items-center gap-0.5" :class="m.read_at ? 'text-primary' : ''">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                      <path d="M18 6L7 17l-5-5"/>
                      <path d="M22 6L11 17"/>
                    </svg>
                    <span class="text-[9px]">{{ m.read_at ? 'Read' : 'Sent' }}</span>
                  </span>
                </div>
              </div>
            </div>
          </div>

          <!-- User typing indicator (visitor is typing) -->
          <div v-if="handoff.userIsTyping.value" class="flex justify-start">
            <div class="flex items-center gap-2 text-sm text-muted-foreground bg-primary/10 rounded-full px-4 py-2">
              <span class="text-xs">Visitor is typing</span>
              <span class="flex gap-0.5">
                <span class="w-1.5 h-1.5 bg-current rounded-full animate-bounce" style="animation-delay: 0ms"></span>
                <span class="w-1.5 h-1.5 bg-current rounded-full animate-bounce" style="animation-delay: 150ms"></span>
                <span class="w-1.5 h-1.5 bg-current rounded-full animate-bounce" style="animation-delay: 300ms"></span>
              </span>
            </div>
          </div>

          <!-- Scroll anchor - always at the bottom -->
          <div ref="scrollAnchor" class="h-1" />
        </div>
      </ScrollArea>

      <!-- Message Input -->
      <div class="border-t p-4 flex-shrink-0">
        <form @submit.prevent="sendMessage" class="flex gap-2">
          <Textarea
            v-model="messageInput"
            placeholder="Type your message..."
            class="flex-1 min-h-[44px] max-h-32 resize-none"
            rows="1"
            @input="handleTyping"
            @keydown.enter.exact.prevent="sendMessage"
          />
          <Button 
            type="submit" 
            size="icon" 
            class="h-11 w-11 shrink-0"
            :disabled="!messageInput.trim() || handoff.isSendingMessage.value"
          >
            <LoaderCircle v-if="handoff.isSendingMessage.value" class="h-4 w-4 animate-spin" />
            <Send v-else class="h-4 w-4" />
          </Button>
        </form>
      </div>
    </template>
  </div>
</template>

