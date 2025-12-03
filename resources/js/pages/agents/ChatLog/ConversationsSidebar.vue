<script lang="ts" setup>
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import { LoaderCircle, RefreshCcw, Download, Filter, X, ThumbsUp, ThumbsDown, Globe, Calendar, Circle } from 'lucide-vue-next';
import { useChatLog } from './useChatLog';

const {
  agent,
  conversations,
  currentConversationId,
  leadConversationIds,
  isLoadingConversations,
  totalConversations,
  currentConversationsPage,
  hasActiveFilters,
  activeFilterCount,
  showFiltersDialog,
  selectedTopic,
  selectedSentiment,
  selectedTone,
  selectedCountry,
  selectedConfidenceScore,
  selectedFeedback,
  selectedSource,
  dateRange,
  onlineOnly,
  fetchConversations,
  selectConversation,
  formatTimeAgo,
  getFlagPath,
  getTopicName,
  getCountryName,
  getSentimentDisplayName,
  getSentimentBadgeColor,
  getToneDisplayName,
  getToneEmoji,
  getToneBadgeColor,
  getConfidenceScoreDisplayName,
  getFeedbackDisplayName,
  getSourceDisplayName,
  formatDateRangeDisplay,
} = useChatLog();

function toggleOnlineOnly() {
  onlineOnly.value = !onlineOnly.value;
  fetchConversations(1);
}

const isRefreshingConversations = ref(false);

async function refreshConversations() {
  isRefreshingConversations.value = true;
  try {
    await fetchConversations(currentConversationsPage.value);
  } finally {
    isRefreshingConversations.value = false;
  }
}

function clearFilter(filter: string) {
  switch (filter) {
    case 'topic': selectedTopic.value = 'all'; break;
    case 'sentiment': selectedSentiment.value = 'all'; break;
    case 'tone': selectedTone.value = 'all'; break;
    case 'country': selectedCountry.value = 'all'; break;
    case 'confidence': selectedConfidenceScore.value = 'all'; break;
    case 'feedback': selectedFeedback.value = 'all'; break;
    case 'source': selectedSource.value = 'all'; break;
    case 'dateRange': dateRange.value = undefined; break;
  }
  fetchConversations(1);
}

// Export functions (placeholders)
function exportAsJSON() { console.log('Export JSON'); }
function exportAsCSV() { console.log('Export CSV'); }
function exportAsPDF() { console.log('Export PDF'); }
</script>

<script lang="ts">
import { ref } from 'vue';
</script>

<template>
  <div class="w-[300px] flex-shrink-0 flex flex-col h-full overflow-hidden bg-muted/30 border-r">
    <div class="p-0 flex-1 overflow-y-auto min-h-0">
      <div class="px-4 pb-3 pt-4 flex-shrink-0 border-b border-border/50 sticky top-0 bg-muted/30 backdrop-blur-sm z-10">
        <div class="flex items-center justify-between mb-3">
          <div class="flex items-center gap-1">
            <!-- Online Only Toggle -->
            <TooltipProvider>
              <Tooltip>
                <TooltipTrigger as-child>
                  <Button
                    variant="ghost"
                    size="sm"
                    @click="toggleOnlineOnly"
                    class="h-8 px-2.5 gap-1.5"
                    :class="onlineOnly ? 'bg-green-100 text-green-700 hover:bg-green-200 dark:bg-green-900/30 dark:text-green-400 dark:hover:bg-green-900/50' : ''"
                  >
                    <Circle class="h-2.5 w-2.5 fill-current" :class="onlineOnly ? 'text-green-500' : 'text-muted-foreground'" />
                    <span class="text-xs font-medium">Online</span>
                  </Button>
                </TooltipTrigger>
                <TooltipContent side="bottom">
                  <p>Show only online users</p>
                </TooltipContent>
              </Tooltip>
            </TooltipProvider>
          </div>
          <div class="flex items-center gap-1">

            <!-- Filters Button -->
            <Button 
              variant="ghost" 
              size="icon" 
              class="h-8 w-8 relative cursor-pointer"
              :class="{ 'text-primary': hasActiveFilters }"
              @click="showFiltersDialog = true"
            >
              <Filter class="h-4 w-4" />
              <span v-if="hasActiveFilters" class="absolute -top-0.5 -right-0.5 h-4 w-4 bg-primary rounded-full text-[10px] text-primary-foreground flex items-center justify-center font-medium">
                {{ activeFilterCount }}
              </span>
            </Button>
            
            <!-- Refresh Conversations Button -->
            <Button 
              variant="ghost" 
              size="icon" 
              class="h-8 w-8"
              @click="refreshConversations"
              :disabled="isRefreshingConversations"
            >
              <RefreshCcw class="h-4 w-4" :class="{ 'animate-spin': isRefreshingConversations }" />
            </Button>
            
            <!-- Export Dropdown -->
            <DropdownMenu>
              <DropdownMenuTrigger as-child>
                <Button variant="default" size="icon" class="h-8 w-8">
                  <Download class="h-4 w-4" />
                </Button>
              </DropdownMenuTrigger>
              <DropdownMenuContent align="end">
                <DropdownMenuItem @click="exportAsJSON">Export JSON</DropdownMenuItem>
                <DropdownMenuItem @click="exportAsCSV">Export CSV</DropdownMenuItem>
                <DropdownMenuItem @click="exportAsPDF">Export PDF</DropdownMenuItem>
              </DropdownMenuContent>
            </DropdownMenu>
          </div>
        </div>
        
        <!-- Active Filters Display -->
        <div v-if="hasActiveFilters" class="mb-3 flex flex-wrap gap-1.5">
          <Badge v-if="dateRange" variant="secondary" class="text-xs px-2 py-1 flex items-center gap-1">
            <Calendar class="h-3 w-3" />
            {{ formatDateRangeDisplay() }}
            <button @click="clearFilter('dateRange')" class="ml-1 hover:text-foreground cursor-pointer"><X class="h-3 w-3" /></button>
          </Badge>
          
          <Badge v-if="selectedCountry !== 'all'" variant="secondary" class="text-xs px-2 py-1 flex items-center gap-1">
            <Globe class="h-3 w-3" />
            {{ getCountryName(selectedCountry) }}
            <button @click="clearFilter('country')" class="ml-1 hover:text-foreground cursor-pointer"><X class="h-3 w-3" /></button>
          </Badge>
          
          <Badge 
            v-if="selectedConfidenceScore !== 'all'" 
            variant="secondary" 
            class="text-xs px-2 py-1 flex items-center gap-1"
            :class="{
              'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300': selectedConfidenceScore === 'high',
              'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-300': selectedConfidenceScore === 'medium',
              'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300': selectedConfidenceScore === 'low'
            }"
          >
            {{ getConfidenceScoreDisplayName(selectedConfidenceScore) }}
            <button @click="clearFilter('confidence')" class="ml-1 hover:opacity-70 cursor-pointer"><X class="h-3 w-3" /></button>
          </Badge>
          
          <Badge 
            v-if="selectedFeedback !== 'all'" 
            variant="secondary" 
            class="text-xs px-2 py-1 flex items-center gap-1"
            :class="{
              'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300': selectedFeedback === 'positive',
              'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300': selectedFeedback === 'negative'
            }"
          >
            <ThumbsUp v-if="selectedFeedback === 'positive'" class="h-3 w-3" />
            <ThumbsDown v-else class="h-3 w-3" />
            {{ getFeedbackDisplayName(selectedFeedback) }}
            <button @click="clearFilter('feedback')" class="ml-1 hover:opacity-70 cursor-pointer"><X class="h-3 w-3" /></button>
          </Badge>
          
          <Badge v-if="selectedSource !== 'all'" variant="secondary" class="text-xs px-2 py-1 flex items-center gap-1">
            {{ getSourceDisplayName(selectedSource) }}
            <button @click="clearFilter('source')" class="ml-1 hover:text-foreground cursor-pointer"><X class="h-3 w-3" /></button>
          </Badge>
          
          <Badge v-if="selectedTopic !== 'all'" variant="secondary" class="text-xs px-2 py-1 flex items-center gap-1">
            {{ getTopicName(Number(selectedTopic)) }}
            <button @click="clearFilter('topic')" class="ml-1 hover:text-foreground cursor-pointer"><X class="h-3 w-3" /></button>
          </Badge>
          
          <Badge 
            v-if="selectedSentiment !== 'all'" 
            :class="getSentimentBadgeColor(selectedSentiment)"
            class="text-xs px-2 py-1 flex items-center gap-1"
          >
            {{ getSentimentDisplayName(selectedSentiment) }}
            <button @click="clearFilter('sentiment')" class="ml-1 hover:opacity-70 cursor-pointer"><X class="h-3 w-3" /></button>
          </Badge>
          
          <Badge 
            v-if="selectedTone !== 'all'" 
            :class="getToneBadgeColor(selectedTone)"
            class="text-xs px-2 py-1 flex items-center gap-1"
          >
            {{ getToneEmoji(selectedTone) }} {{ getToneDisplayName(selectedTone) }}
            <button @click="clearFilter('tone')" class="ml-1 hover:opacity-70 cursor-pointer"><X class="h-3 w-3" /></button>
          </Badge>
        </div>
        
        <div class="flex items-center justify-between text-xs text-muted-foreground">
          <span>{{ totalConversations }} conversations</span>
        </div>
      </div>
      
      <!-- Loading State for Conversations -->
      <div v-if="isLoadingConversations" class="flex items-center justify-center py-12">
        <LoaderCircle class="h-8 w-8 animate-spin text-muted-foreground" />
      </div>
      
      <!-- Conversations List -->
      <ul v-else class="p-2 space-y-0.5">
        <li v-for="c in conversations?.data" :key="c.conversation_id">
          <button
            @click="selectConversation(c.conversation_id, c)"
            class="w-full text-left px-3 py-2.5 rounded-lg text-sm flex flex-col gap-1.5 transition-all duration-200 cursor-pointer group"
            :class="currentConversationId === c.conversation_id 
              ? 'bg-primary text-primary-foreground shadow-md' 
              : 'hover:bg-background/80'"
          >
            <div class="flex items-center justify-between gap-2">
              <span class="truncate flex-1 font-medium text-[13px]" :title="c.first_message_excerpt || c.conversation_id">
                {{ c.first_message_excerpt || c.conversation_id.slice(0,8) }}
              </span>
              <div class="flex items-center gap-1.5 flex-shrink-0">
                <span 
                  class="text-[10px] tabular-nums"
                  :class="currentConversationId === c.conversation_id ? 'opacity-80' : 'opacity-50'"
                >
                  {{ c.messages_count }}
                </span>
              </div>
            </div>
            
            <div class="flex items-center gap-2 text-[11px]" :class="currentConversationId === c.conversation_id ? 'opacity-70' : 'opacity-50'">
              <img 
                v-if="c.country && getFlagPath(c.country)" 
                :src="getFlagPath(c.country)!" 
                :alt="c.country"
                class="w-3 h-3 rounded-sm object-cover"
                :title="c.country.toUpperCase()"
              />
              <span v-if="c.topic" class="truncate max-w-[100px]">{{ c.topic }}</span>
              <span v-if="c.topic" class="opacity-50">•</span>
              <span>{{ formatTimeAgo(c.last_at) }}</span>
            </div>
          </button>
        </li>
      </ul>
    </div>

    <!-- Pagination Controls -->
    <div v-if="conversations && conversations.last_page > 1" class="border-t border-border/50 p-3 flex-shrink-0 bg-muted/30">
      <div class="flex justify-between items-center gap-2">
        <Button
          variant="ghost"
          size="sm"
          class="h-7 text-xs"
          :disabled="!conversations?.prev_page_url || isLoadingConversations"
          @click="fetchConversations(conversations.current_page - 1)"
        >
          <LoaderCircle v-if="isLoadingConversations" class="h-3 w-3 mr-1 animate-spin" />
          Prev
        </Button>
        <span class="text-[10px] text-muted-foreground tabular-nums">
          {{ conversations?.current_page }} / {{ conversations?.last_page }}
        </span>
        <Button
          variant="ghost"
          size="sm"
          class="h-7 text-xs"
          :disabled="!conversations?.next_page_url || isLoadingConversations"
          @click="fetchConversations(conversations.current_page + 1)"
        >
          <LoaderCircle v-if="isLoadingConversations" class="h-3 w-3 mr-1 animate-spin" />
          Next
        </Button>
      </div>
    </div>
  </div>
</template>

