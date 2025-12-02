<script lang="ts" setup>
import { ref, computed } from 'vue';
import { Button } from '@/components/ui/button';
import { ScrollArea } from '@/components/ui/scroll-area';
import { LoaderCircle, Search, MessageSquare, RotateCcw, ThumbsUp, ThumbsDown } from 'lucide-vue-next';
import MessageCard from '@/components/chat/MessageCard.vue';
import { useChatLog } from './useChatLog';

type Message = App.Data.Resources.Agent.ChatLog.ChatLogMessageData;

const emit = defineEmits<{
  (e: 'open-revise', message: Message): void;
}>();

const {
  currentConversationId,
  messagesData,
  messages,
  isLoadingMessages,
  search,
  fetchMessages,
  formatTimeAgo,
  formatFullDateTime,
  highlightText,
  getToneDisplayName,
  getToneBadgeColor,
  getConfidenceColor,
} = useChatLog();

function clearSearch() {
  search.value = '';
}

function getConfidenceScore(message: Message): number {
  if (message.role === 'assistant') {
    const messageWithScore = message as any;
    if (messageWithScore.confidence_score) {
      return messageWithScore.confidence_score;
    }
  }
  return 1.0;
}

function formatConfidenceScore(score: number): string {
  return score.toFixed(2);
}

function getFeedbackInfo(feedback: string | null): { color: string; label: string } | null {
  if (!feedback) return null;
  if (feedback === 'positive') {
    return { color: 'text-green-600 dark:text-green-400', label: 'Positive feedback' };
  }
  return { color: 'text-red-600 dark:text-red-400', label: 'Negative feedback' };
}

function openReviseDialog(message: Message) {
  emit('open-revise', message);
}
</script>

<template>
  <ScrollArea class="flex-1 h-0">
    <!-- Loading State -->
    <div v-if="isLoadingMessages" class="h-full flex items-center justify-center py-20">
      <LoaderCircle class="h-6 w-6 animate-spin text-muted-foreground" />
    </div>
    
    <!-- Empty States -->
    <div v-else-if="!currentConversationId" class="h-full flex flex-col items-center justify-center text-muted-foreground py-20">
      <MessageSquare class="h-10 w-10 mb-3 opacity-30" />
      <p class="text-sm">Select a conversation</p>
    </div>
    <div v-else-if="messages.length === 0 && !search" class="h-full flex flex-col items-center justify-center text-muted-foreground py-20">
      <MessageSquare class="h-10 w-10 mb-3 opacity-30" />
      <p class="text-sm">No messages yet</p>
    </div>
    <div v-else-if="messages.length === 0 && search" class="h-full flex flex-col items-center justify-center text-muted-foreground py-20">
      <Search class="h-10 w-10 mb-3 opacity-30" />
      <p class="text-sm">No results found</p>
      <Button variant="link" size="sm" @click="clearSearch" class="mt-1 h-auto p-0">
        Clear search
      </Button>
    </div>
    
    <!-- Messages List -->
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
            <!-- Message Bubble -->
            <div 
              class="rounded-2xl px-4 py-3"
              :class="m.role === 'user' 
                ? 'bg-primary text-primary-foreground rounded-br-md' 
                : 'bg-muted/60 rounded-bl-md'"
            >
              <div v-if="search" v-html="highlightText(m.content, search)" class="prose dark:prose-invert max-w-none"></div>
              <div v-else-if="m.role === 'user'" class="text-base whitespace-pre-wrap break-words leading-relaxed">{{ m.content }}</div>
              <MessageCard v-else :message="m" :hide-feedback="true" />
            </div>

            <!-- Meta Information -->
            <div class="flex items-center gap-2 text-xs text-muted-foreground px-1 flex-wrap">
              <!-- Tone (for user messages) -->
              <span 
                v-if="m.role === 'user' && m.tone"
                :class="getToneBadgeColor(m.tone)"
                class="px-1.5 py-0.5 rounded text-[10px] font-medium"
              >
                {{ getToneDisplayName(m.tone) }}
              </span>

              <span v-if="m.role === 'user' && m.tone && m.sentiment" class="text-muted-foreground/30">•</span>

              <!-- Sentiment (for user messages) -->
              <span 
                v-if="m.role === 'user' && m.sentiment"
                class="capitalize"
                :class="{
                  'text-green-600 dark:text-green-400': m.sentiment === 'positive',
                  'text-red-600 dark:text-red-400': m.sentiment === 'negative',
                  'text-muted-foreground': m.sentiment === 'neutral'
                }"
              >
                {{ m.sentiment }}
              </span>

              <span v-if="m.role === 'user' && (m.tone || m.sentiment)" class="text-muted-foreground/30">•</span>

              <!-- Confidence (for assistant) -->
              <template v-if="m.role === 'assistant'">
                <span 
                  class="group relative cursor-help tabular-nums"
                  :class="getConfidenceColor(getConfidenceScore(m))"
                >
                  {{ formatConfidenceScore(getConfidenceScore(m)) }}
                  <span class="absolute bottom-full left-0 mb-1.5 px-2 py-1 bg-gray-900 text-white text-[10px] rounded opacity-0 pointer-events-none group-hover:opacity-100 transition-opacity z-50 shadow-lg whitespace-nowrap">
                    Confidence: {{ (getConfidenceScore(m) * 100).toFixed(0) }}%
                  </span>
                </span>
                <span class="text-muted-foreground/30">•</span>
              </template>

              <!-- Playground indicator -->
              <template v-if="m.source === 'playground'">
                <span class="text-amber-600 dark:text-amber-400 font-medium">Playground</span>
                <span class="text-muted-foreground/30">•</span>
              </template>

              <!-- Time -->
              <span class="group relative cursor-help">
                {{ formatTimeAgo(m.created_at) }}
                <span 
                  class="absolute bottom-full mb-1.5 px-2 py-1 bg-gray-900 text-white text-[10px] rounded opacity-0 pointer-events-none group-hover:opacity-100 transition-opacity z-50 shadow-lg whitespace-nowrap"
                  :class="m.role === 'user' ? 'right-0' : 'left-0'"
                >
                  {{ formatFullDateTime(m.created_at) }}
                </span>
              </span>

              <!-- Model (for assistant) -->
              <template v-if="m.role === 'assistant' && m.llm_name">
                <span class="text-muted-foreground/30">•</span>
                <div class="group relative cursor-help">
                  <img 
                    :src="m.llm_icon ? `/images/llms/${m.llm_icon}` : '/images/llms/gpt.svg'" 
                    :alt="m.llm_name"
                    class="w-3.5 h-3.5 rounded"
                  />
                  <span class="absolute bottom-full left-0 mb-1.5 px-2 py-1 bg-gray-900 text-white text-[10px] rounded opacity-0 pointer-events-none group-hover:opacity-100 transition-opacity z-50 shadow-lg whitespace-nowrap">
                    {{ m.llm_name }}
                  </span>
                </div>
              </template>

              <!-- Feedback (for assistant) -->
              <template v-if="m.role === 'assistant' && m.feedback">
                <span class="text-muted-foreground/30">•</span>
                <div 
                  class="group relative cursor-help"
                  :class="getFeedbackInfo(m.feedback)?.color"
                >
                  <ThumbsUp v-if="m.feedback === 'positive'" class="w-3 h-3" />
                  <ThumbsDown v-else class="w-3 h-3" />
                  <span class="absolute bottom-full left-0 mb-1.5 px-2 py-1 bg-gray-900 text-white text-[10px] rounded opacity-0 pointer-events-none group-hover:opacity-100 transition-opacity z-50 shadow-lg whitespace-nowrap">
                    {{ getFeedbackInfo(m.feedback)?.label }}
                  </span>
                </div>
              </template>

              <!-- Q&A Status (for assistant) -->
              <template v-if="m.role === 'assistant'">
                <span class="text-muted-foreground/30">•</span>
                <span 
                  v-if="m.is_direct_response"
                  class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300 font-medium"
                >
                  ✓ Q&A Matched
                </span>
                <span 
                  v-else-if="m.agent_question_answer_id"
                  class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300 font-medium"
                >
                  ✓ Revised
                </span>
                <button
                  v-else
                  class="text-muted-foreground hover:text-foreground transition-colors flex items-center gap-0.5 cursor-pointer"
                  @click="openReviseDialog(m)"
                >
                  <RotateCcw class="w-2.5 h-2.5" />
                  <span>Revise</span>
                </button>
              </template>
            </div>
          </div>
        </div>
      </div>
    </div>
  </ScrollArea>

  <!-- Pagination -->
  <div v-if="currentConversationId && messagesData?.last_page > 1" class="border-t p-3 flex-shrink-0">
    <div class="flex justify-between items-center">
      <Button
        variant="ghost"
        size="sm"
        class="h-8 text-xs"
        :disabled="!messagesData?.prev_page_url || isLoadingMessages"
        @click="currentConversationId && fetchMessages(currentConversationId, messagesData.current_page - 1)"
      >
        <LoaderCircle v-if="isLoadingMessages" class="h-3 w-3 mr-1 animate-spin" />
        Prev
      </Button>
      <span class="text-[11px] text-muted-foreground tabular-nums">
        {{ messagesData?.current_page }} / {{ messagesData?.last_page }}
      </span>
      <Button
        variant="ghost"
        size="sm"
        class="h-8 text-xs"
        :disabled="!messagesData?.next_page_url || isLoadingMessages"
        @click="currentConversationId && fetchMessages(currentConversationId, messagesData.current_page + 1)"
      >
        <LoaderCircle v-if="isLoadingMessages" class="h-3 w-3 mr-1 animate-spin" />
        Next
      </Button>
    </div>
  </div>
</template>

