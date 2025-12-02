<script lang="ts" setup>
import AgentLayout from '@/layouts/AgentLayout.vue';
import { computed, ref, watch, onMounted } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter } from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { ScrollArea } from '@/components/ui/scroll-area';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { LoaderCircle, Search, MessageSquare, RefreshCcw, X, RotateCcw, ThumbsUp, ThumbsDown, Download, Filter, UserCheck, Globe, Monitor, Smartphone, Tablet, MapPin, Clock, Tag, Heart, Smile, Calendar } from 'lucide-vue-next';
import LeadDetailsSheet from '@/components/LeadDetailsSheet.vue';
import MessageCard from '@/components/chat/MessageCard.vue';
import DateRangePicker from '@/components/ui/DateRangePicker.vue';
import { useTimeAgo } from '@/composables/useTimeAgo';
import axios from 'axios';

type Message = App.Data.Resources.Agent.ChatLog.ChatLogMessageData;
type ConversationSummary = App.Data.Resources.Agent.ChatLog.ChatLogConversationData;
type Topic = App.Data.Resources.Agent.ChatLog.ChatLogTopicData;
type AIModel = App.Data.Chat.LlmModelData;

const page = usePage();
const agent = page.props.agent as any;
const currentConversationId = ref<string | null>(page.props.current_conversation_id as string | null);

// Data refs
const messagesData = ref<any>(null);
const conversations = ref<any>(null);
const conversationTitle = ref<string | null>(null);
const leadConversationIds = ref<string[]>([]);
const lead = ref<any | null>(null);
const currentConversationData = ref<any | null>(null);
const showLeadPanel = ref(false);
const hasLeadForCurrentConversation = computed(() => !!lead.value);

// Active tab (chat or details)
const activeTab = ref<'chat' | 'details'>('chat');

// Loading states
const isLoadingConversations = ref(false);
const isLoadingMessages = ref(false);

// Filters
const topics = ref<Topic[]>([]);
const countries = ref<{ id: number; code: string; name: string }[]>([]);
const selectedTopic = ref<string>('all');
const selectedSentiment = ref<string>('all');
const selectedTone = ref<string>('all');
const selectedCountry = ref<string>('all');
const selectedConfidenceScore = ref<string>('all');
const selectedFeedback = ref<string>('all');
const selectedSource = ref<string>('all');
const dateRange = ref<{ start: string; end: string } | undefined>(undefined);
const showFiltersDialog = ref(false);

// Use time ago composable
const { formatTimeAgo } = useTimeAgo();

// Models list
const models = ref<AIModel[]>([]);
const modelsMap = computed(() => {
  const map = new Map<string, AIModel>();
  models.value.forEach(model => {
    map.set(model.id, model);
  });
  return map;
});

// Revise answer dialog
const showReviseDialog = ref(false);
const reviseUserMessage = ref('');
const reviseBotResponse = ref('');
const reviseExpectedResponse = ref('');
const currentReviseMessageId = ref<string | null>(null);

// Total conversations count
const totalConversations = computed(() => conversations.value?.total || 0);

// Current conversations page
const currentConversationsPage = computed(() => conversations.value?.current_page || 1);

// Fetch conversations
async function fetchConversations(page = 1) {
  isLoadingConversations.value = true;
  try {
    const params: any = { page };
    if (selectedTopic.value && selectedTopic.value !== 'all') {
      params.topic = selectedTopic.value;
    }
    if (selectedSentiment.value && selectedSentiment.value !== 'all') {
      params.sentiment = selectedSentiment.value;
    }
    if (selectedTone.value && selectedTone.value !== 'all') {
      params.tone = selectedTone.value;
    }
    if (selectedCountry.value && selectedCountry.value !== 'all') {
      params.country = selectedCountry.value;
    }
    if (selectedConfidenceScore.value && selectedConfidenceScore.value !== 'all') {
      params.confidence_score = selectedConfidenceScore.value;
    }
    if (selectedFeedback.value && selectedFeedback.value !== 'all') {
      params.feedback = selectedFeedback.value;
    }
    if (selectedSource.value && selectedSource.value !== 'all') {
      params.source = selectedSource.value;
    }
    if (dateRange.value?.start && dateRange.value?.end) {
      params.start_date = dateRange.value.start;
      params.end_date = dateRange.value.end;
    }
    
    const response = await axios.get(route('agents.logs.conversations', { agent: agent.id }), {
      params
    });
    conversations.value = response.data.conversations;
    leadConversationIds.value = response.data.lead_conversation_ids;
    
    // Update URL to preserve page number when paginating
    if (page !== 1 || currentConversationId.value) {
      const params: any = { agent: agent.id };
      if (currentConversationId.value) {
        params.conversation_id = currentConversationId.value;
      }
      if (page > 1) {
        params.conversations = page;
      }
      const url = route('agents.logs', params);
      window.history.pushState({}, '', url);
    }
  } catch (error) {
    console.error('Failed to fetch conversations:', error);
  } finally {
    isLoadingConversations.value = false;
  }
}

// Fetch messages for a conversation
async function fetchMessages(conversationId: string, page = 1) {
  if (!conversationId) return;
  
  isLoadingMessages.value = true;
  try {
    const response = await axios.get(route('agents.logs.messages', { 
      agent: agent.id, 
      conversation: conversationId 
    }), {
      params: { page }
    });
    messagesData.value = response.data.messages;
    conversationTitle.value = response.data.conversation_title;
    lead.value = response.data.lead;
  } catch (error) {
    console.error('Failed to fetch messages:', error);
  } finally {
    isLoadingMessages.value = false;
  }
}

// Fetch models and initial data on mount
onMounted(async () => {
  try {
    // Fetch models, topics, countries, and conversations in parallel
    const [modelsResponse, topicsResponse, countriesResponse] = await Promise.all([
      axios.get(route('agents.models.list', { agent: agent.id })),
      axios.get(route('agents.logs.topics', { agent: agent.id })),
      axios.get(route('agents.logs.countries', { agent: agent.id }))
    ]);
    
    models.value = modelsResponse.data.models || [];
    topics.value = topicsResponse.data.topics || [];
    countries.value = countriesResponse.data.countries || [];
    
    // Fetch conversations
    await fetchConversations();
    
    // Fetch messages if there's a current conversation
    if (currentConversationId.value) {
      await fetchMessages(currentConversationId.value);
      // Set current conversation data (make a copy to avoid reactivity coupling)
      const found = conversations.value?.data?.find((c: any) => c.conversation_id === currentConversationId.value);
      currentConversationData.value = found ? { ...found } : null;
    }
  } catch (error) {
    console.error('Failed to fetch initial data:', error);
  }
});

// Watch for conversation changes
watch(() => currentConversationId.value, (newId, oldId) => {
  if (newId) {
    fetchMessages(newId);
    // Update current conversation data (make a copy to avoid reactivity coupling)
    const found = conversations.value?.data?.find((c: any) => c.conversation_id === newId);
    currentConversationData.value = found ? { ...found } : null;
    
    // Update URL without reloading the page, preserving the conversations page
    if (newId !== oldId) {
      const params: any = { agent: agent.id, conversation_id: newId };
      const currentPage = currentConversationsPage.value;
      if (currentPage > 1) {
        params.conversations = currentPage;
      }
      const url = route('agents.logs', params);
      window.history.pushState({}, '', url);
    }
  } else {
    messagesData.value = null;
    conversationTitle.value = null;
    lead.value = null;
    currentConversationData.value = null;
  }
});

// Apply filters function - called when user clicks "Close" or "Apply" in filter dialog
function applyFilters() {
  showFiltersDialog.value = false;
  fetchConversations(1);
}

function openLeadPanel() {
  if (!hasLeadForCurrentConversation.value) return;
  showLeadPanel.value = true;
}


// Page title (could include session short id)
const title = computed(() => conversationTitle.value ? `Chat Log – ${conversationTitle.value}` : 'Chat Log');

// Client-side search across loaded page messages (content + role + model)
const search = ref('');
const messages = computed(() => {
  const list: Message[] = (messagesData.value && messagesData.value.data) ? messagesData.value.data : [];
  if (!search.value.trim()) return list;
  const q = search.value.toLowerCase();
  return list.filter(m =>
    (m.content && m.content.toLowerCase().includes(q)) ||
    (m.role && m.role.toLowerCase().includes(q)) ||
    (m.llm_name && m.llm_name.toLowerCase().includes(q))
  );
});

const totalMessages = computed(() => messagesData.value?.data?.length || 0);
const filteredCount = computed(() => messages.value.length);

// Highlight search text in content
function highlightText(text: string, query: string): string {
  if (!query.trim()) return text;
  const regex = new RegExp(`(${query.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')})`, 'gi');
  return text.replace(regex, '<mark class="bg-yellow-200 dark:bg-yellow-900/50 rounded px-0.5">$1</mark>');
}

function clearSearch() {
  search.value = '';
}

// Watch for conversation changes (removed - handled by watch on currentConversationId.value below)

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

const isRefreshingConversations = ref(false);
async function refreshConversations() {
  isRefreshingConversations.value = true;
  try {
    await fetchConversations(currentConversationsPage.value);
  } finally {
    isRefreshingConversations.value = false;
  }
}

function formatDate(dt: string | null | undefined) {
  if (!dt) return '—';
  try {
    return new Intl.DateTimeFormat('en-US', { dateStyle: 'short', timeStyle: 'short' }).format(new Date(dt));
  } catch {
    return dt;
  }
}

// Format full datetime for tooltip
function formatFullDateTime(dt: string | null | undefined): string {
  if (!dt) return '';
  try {
    return new Intl.DateTimeFormat('en-US', { 
      weekday: 'long',
      year: 'numeric', 
      month: 'long', 
      day: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
      second: '2-digit'
    }).format(new Date(dt));
  } catch {
    return dt || '';
  }
}

// Get model icon path from models list
function getModelIcon(modelName: string | undefined): string {
  if (!modelName) return '';
  
  // Find model in the models list
  const model = modelsMap.value.get(modelName);
  if (model && model.icon) {
    return `/images/llms/${model.icon}`;
  }
  
  // Default to GPT icon if not found
  return '/images/llms/gpt.svg';
}

// Get model display name from models list
function getModelName(modelName: string | undefined): string {
  if (!modelName) return '';
  
  const model = modelsMap.value.get(modelName);
  return model?.name || modelName;
}

// Get confidence score for a message
function getConfidenceScore(message: Message): number {
  // For assistant messages, use the confidence score from the database
  if (message.role === 'assistant') {
    const messageWithScore = message as any;
    if (messageWithScore.confidence_score) {
      return messageWithScore.confidence_score;
    }
  }
  return 1.0; // Default confidence if not available
}

function formatConfidenceScore(score: number): string {
  return score.toFixed(2);
}

// Get color class based on confidence score
function getConfidenceColor(score: number): string {
  if (score >= 0.8) {
    return 'text-green-600 dark:text-green-400'; // High confidence - Green
  } else if (score >= 0.65) {
    return 'text-yellow-600 dark:text-yellow-400'; // Medium confidence - Yellow
  } else {
    return 'text-red-600 dark:text-red-400'; // Low confidence - Red
  }
}

// Get feedback display info
function getFeedbackInfo(feedback: string | null): { icon: string; color: string; label: string } | null {
  if (!feedback) return null;
  
  if (feedback === 'positive') {
    return {
      icon: 'thumbs-up',
      color: 'text-green-600 dark:text-green-400',
      label: 'Positive feedback'
    };
  } else {
    return {
      icon: 'thumbs-down',
      color: 'text-red-600 dark:text-red-400',
      label: 'Negative feedback'
    };
  }
}

// Open revise answer dialog
function openReviseDialog(message: Message) {
  // Find the user message that came before this assistant message
  const messageIndex = messages.value.findIndex(m => m.id === message.id);
  const userMessage = messageIndex > 0 ? messages.value[messageIndex - 1] : null;
  
  reviseUserMessage.value = userMessage?.content || '';
  reviseBotResponse.value = message.content;
  reviseExpectedResponse.value = '';
  currentReviseMessageId.value = message.id;
  showReviseDialog.value = true;
}

// Update answer - saves as Q&A entry
const isUpdatingAnswer = ref(false);
async function updateAnswer() {
  if (!reviseExpectedResponse.value.trim() || !reviseUserMessage.value.trim() || !currentReviseMessageId.value) return;
  
  isUpdatingAnswer.value = true;
  try {
    const response = await axios.post(route('agents.logs.revise-answer', { agent: agent.id }), {
      message_id: currentReviseMessageId.value,
      user_message: reviseUserMessage.value,
      expected_response: reviseExpectedResponse.value
    });
    
    // Update the message in the local state to show revision indicator
    if (messagesData.value?.data) {
      const originalIndex = messagesData.value.data.findIndex((m: Message) => m.id === currentReviseMessageId.value);
      if (originalIndex !== -1) {
        messagesData.value.data[originalIndex].agent_question_answer_id = response.data.qa_id;
      }
    }
    
    // Close dialog
    showReviseDialog.value = false;
    
    // Reset form
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

// Cancel revise
function cancelRevise() {
  showReviseDialog.value = false;
  reviseUserMessage.value = '';
  reviseBotResponse.value = '';
  reviseExpectedResponse.value = '';
  currentReviseMessageId.value = null;
}

// Get browser name from user agent
function getBrowserName(userAgent: string | null | undefined): string {
  if (!userAgent) return 'Unknown';
  
  const ua = userAgent.toLowerCase();
  
  if (ua.includes('edg/') || ua.includes('edge')) return 'Edge';
  if (ua.includes('opr/') || ua.includes('opera')) return 'Opera';
  if (ua.includes('chrome') && !ua.includes('edg')) return 'Chrome';
  if (ua.includes('safari') && !ua.includes('chrome')) return 'Safari';
  if (ua.includes('firefox')) return 'Firefox';
  if (ua.includes('msie') || ua.includes('trident')) return 'IE';
  
  return 'Unknown';
}

// Get flag image path
function getFlagPath(countryCode: string | null | undefined): string | null {
  if (!countryCode) return null;
  return `/images/flags/${countryCode.toLowerCase()}.svg`;
}

// Export functions (placeholders for backend implementation)
function exportAsJSON() {
  // TODO: Backend implementation
  console.log('Exporting conversations as JSON');
}

function exportAsCSV() {
  // TODO: Backend implementation
  console.log('Exporting conversations as CSV');
}

function exportAsPDF() {
  // TODO: Backend implementation
  console.log('Exporting conversations as PDF');
}

// Filter functions
function clearAllFilters() {
  selectedTopic.value = 'all';
  selectedSentiment.value = 'all';
  selectedTone.value = 'all';
  selectedCountry.value = 'all';
  selectedConfidenceScore.value = 'all';
  selectedFeedback.value = 'all';
  selectedSource.value = 'all';
  dateRange.value = undefined;
  fetchConversations(1);
}

function clearTopicFilter() {
  selectedTopic.value = 'all';
  fetchConversations(1);
}

function clearSentimentFilter() {
  selectedSentiment.value = 'all';
  fetchConversations(1);
}

function clearToneFilter() {
  selectedTone.value = 'all';
  fetchConversations(1);
}

function clearCountryFilter() {
  selectedCountry.value = 'all';
  fetchConversations(1);
}

function clearConfidenceScoreFilter() {
  selectedConfidenceScore.value = 'all';
  fetchConversations(1);
}

function clearFeedbackFilter() {
  selectedFeedback.value = 'all';
  fetchConversations(1);
}

function clearSourceFilter() {
  selectedSource.value = 'all';
  fetchConversations(1);
}

function clearDateRangeFilter() {
  dateRange.value = undefined;
  fetchConversations(1);
}

// Check if any filters are active
const hasActiveFilters = computed(() => {
  return selectedTopic.value !== 'all' || 
    selectedSentiment.value !== 'all' || 
    selectedTone.value !== 'all' ||
    selectedCountry.value !== 'all' ||
    selectedConfidenceScore.value !== 'all' ||
    selectedFeedback.value !== 'all' ||
    selectedSource.value !== 'all' ||
    dateRange.value !== undefined;
});

// Count active filters
const activeFilterCount = computed(() => {
  let count = 0;
  if (selectedTopic.value !== 'all') count++;
  if (selectedSentiment.value !== 'all') count++;
  if (selectedTone.value !== 'all') count++;
  if (selectedCountry.value !== 'all') count++;
  if (selectedConfidenceScore.value !== 'all') count++;
  if (selectedFeedback.value !== 'all') count++;
  if (selectedSource.value !== 'all') count++;
  if (dateRange.value !== undefined) count++;
  return count;
});

// Format date range for display
function formatDateRangeDisplay(): string {
  if (!dateRange.value) return '';
  return `${dateRange.value.start} ~ ${dateRange.value.end}`;
}

// Get country name by id
function getCountryName(id: string): string {
  const country = countries.value.find(c => String(c.id) === id);
  return country?.name || id;
}

// Get confidence score display name
function getConfidenceScoreDisplayName(score: string): string {
  const names: Record<string, string> = {
    'high': 'High (≥80%)',
    'medium': 'Medium (65-80%)',
    'low': 'Low (<65%)'
  };
  return names[score] || score;
}

// Get feedback display name
function getFeedbackDisplayName(feedback: string): string {
  const names: Record<string, string> = {
    'positive': 'Thumbs Up',
    'negative': 'Thumbs Down'
  };
  return names[feedback] || feedback;
}

// Get source display name
function getSourceDisplayName(source: string): string {
  const names: Record<string, string> = {
    'widget': 'Widget',
    'playground': 'Playground',
    'api': 'API'
  };
  return names[source] || source;
}

// Get sentiment display name
function getSentimentDisplayName(sentiment: string): string {
  const names: Record<string, string> = {
    'positive': 'Positive',
    'neutral': 'Neutral',
    'negative': 'Negative'
  };
  return names[sentiment] || sentiment;
}

// Get sentiment color
function getSentimentBadgeColor(sentiment: string): string {
  const colors: Record<string, string> = {
    'positive': 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300',
    'neutral': 'bg-gray-100 text-gray-800 dark:bg-gray-900/30 dark:text-gray-300',
    'negative': 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300'
  };
  return colors[sentiment] || 'bg-gray-100 text-gray-800';
}

// Get tone display name
function getToneDisplayName(tone: string): string {
  const names: Record<string, string> = {
    'neutral': 'Neutral',
    'frustrated': 'Frustrated',
    'urgent': 'Urgent',
    'confused': 'Confused',
    'satisfied': 'Satisfied',
    'formal': 'Formal',
    'casual': 'Casual'
  };
  return names[tone] || tone;
}

// Get topic name by id
function getTopicName(topicId: number): string {
  const topic = topics.value.find(t => t.id === topicId);
  return topic?.topic || '';
}

// Get hostname from URL with error handling
function getHostnameFromUrl(url: string | null | undefined): string {
  if (!url) return '';
  try {
    return new URL(url).hostname;
  } catch {
    return url; // Return raw URL if parsing fails
  }
}

// Get pathname from URL with error handling
function getPathnameFromUrl(url: string | null | undefined): string {
  if (!url) return '';
  try {
    return new URL(url).pathname;
  } catch {
    return url; // Return raw URL if parsing fails
  }
}

// Get tone emoji
function getToneEmoji(tone: string): string {
  const emojis: Record<string, string> = {
    'neutral': '😐',
    'frustrated': '😤',
    'urgent': '⚡',
    'confused': '😕',
    'satisfied': '😊',
    'formal': '👔',
    'casual': '😎'
  };
  return emojis[tone] || '😐';
}

// Get tone color
function getToneBadgeColor(tone: string): string {
  const colors: Record<string, string> = {
    'neutral': 'bg-gray-100 text-gray-800 dark:bg-gray-900/30 dark:text-gray-300',
    'frustrated': 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300',
    'urgent': 'bg-orange-100 text-orange-800 dark:bg-orange-900/30 dark:text-orange-300',
    'confused': 'bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-300',
    'satisfied': 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300',
    'formal': 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300',
    'casual': 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-300'
  };
  return colors[tone] || 'bg-gray-100 text-gray-800';
}
</script>

<template>
    <AgentLayout :agent="agent" :title="title" hide-header no-padding full-height>
      <div class="relative h-full w-full">
        <div class="flex gap-0 absolute inset-0 overflow-hidden">
          <!-- Conversations Sidebar -->
          <div class="w-[300px] flex-shrink-0 flex flex-col h-full overflow-hidden bg-muted/30 border-r">
            <div class="p-0 flex-1 overflow-y-auto min-h-0">
              <div class="px-4 pb-3 pt-4 flex-shrink-0 border-b border-border/50 sticky top-0 bg-muted/30 backdrop-blur-sm z-10">
                <div class="flex items-center justify-between mb-3">
                  <h2 class="text-sm font-semibold tracking-tight">Conversations</h2>
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
                        <DropdownMenuItem @click="exportAsJSON">
                          Export JSON
                        </DropdownMenuItem>
                        <DropdownMenuItem @click="exportAsCSV">
                          Export CSV
                        </DropdownMenuItem>
                        <DropdownMenuItem @click="exportAsPDF">
                          Export PDF
                        </DropdownMenuItem>
                      </DropdownMenuContent>
                    </DropdownMenu>
                  </div>
                </div>
                
                <!-- Active Filters Display -->
                <div v-if="hasActiveFilters" class="mb-3 flex flex-wrap gap-1.5">
                  <!-- Date Range -->
                  <Badge 
                    v-if="dateRange" 
                    variant="secondary" 
                    class="text-xs px-2 py-1 flex items-center gap-1"
                  >
                    <Calendar class="h-3 w-3" />
                    {{ formatDateRangeDisplay() }}
                    <button @click="clearDateRangeFilter" class="ml-1 hover:text-foreground cursor-pointer">
                      <X class="h-3 w-3" />
                    </button>
                  </Badge>
                  
                  <!-- Country -->
                  <Badge 
                    v-if="selectedCountry !== 'all'" 
                    variant="secondary" 
                    class="text-xs px-2 py-1 flex items-center gap-1"
                  >
                    <Globe class="h-3 w-3" />
                    {{ getCountryName(selectedCountry) }}
                    <button @click="clearCountryFilter" class="ml-1 hover:text-foreground cursor-pointer">
                      <X class="h-3 w-3" />
                    </button>
                  </Badge>
                  
                  <!-- Confidence Score -->
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
                    <button @click="clearConfidenceScoreFilter" class="ml-1 hover:opacity-70 cursor-pointer">
                      <X class="h-3 w-3" />
                    </button>
                  </Badge>
                  
                  <!-- Feedback -->
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
                    <button @click="clearFeedbackFilter" class="ml-1 hover:opacity-70 cursor-pointer">
                      <X class="h-3 w-3" />
                    </button>
                  </Badge>
                  
                  <!-- Source -->
                  <Badge 
                    v-if="selectedSource !== 'all'" 
                    variant="secondary" 
                    class="text-xs px-2 py-1 flex items-center gap-1"
                  >
                    {{ getSourceDisplayName(selectedSource) }}
                    <button @click="clearSourceFilter" class="ml-1 hover:text-foreground cursor-pointer">
                      <X class="h-3 w-3" />
                    </button>
                  </Badge>
                  
                  <!-- Topic -->
                  <Badge 
                    v-if="selectedTopic !== 'all'" 
                    variant="secondary" 
                    class="text-xs px-2 py-1 flex items-center gap-1"
                  >
                    {{ getTopicName(Number(selectedTopic)) }}
                    <button @click="clearTopicFilter" class="ml-1 hover:text-foreground cursor-pointer">
                      <X class="h-3 w-3" />
                    </button>
                  </Badge>
                  
                  <!-- Sentiment -->
                  <Badge 
                    v-if="selectedSentiment !== 'all'" 
                    :class="getSentimentBadgeColor(selectedSentiment)"
                    class="text-xs px-2 py-1 flex items-center gap-1"
                  >
                    {{ getSentimentDisplayName(selectedSentiment) }}
                    <button @click="clearSentimentFilter" class="ml-1 hover:opacity-70 cursor-pointer">
                      <X class="h-3 w-3" />
                    </button>
                  </Badge>
                  
                  <!-- Tone -->
                  <Badge 
                    v-if="selectedTone !== 'all'" 
                    :class="getToneBadgeColor(selectedTone)"
                    class="text-xs px-2 py-1 flex items-center gap-1"
                  >
                    {{ getToneEmoji(selectedTone) }} {{ getToneDisplayName(selectedTone) }}
                    <button @click="clearToneFilter" class="ml-1 hover:opacity-70 cursor-pointer">
                      <X class="h-3 w-3" />
                    </button>
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
                    @click="() => { currentConversationId = c.conversation_id; currentConversationData = { ...c }; }"
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
                        <!-- Messages Count -->
                        <span 
                          class="text-[10px] tabular-nums"
                          :class="currentConversationId === c.conversation_id ? 'opacity-80' : 'opacity-50'"
                        >
                          {{ c.messages_count }}
                        </span>
                        
                        <!-- Lead Indicator -->
                        <span 
                          v-if="leadConversationIds.includes(c.conversation_id)" 
                          class="w-1.5 h-1.5 rounded-full bg-green-500"
                          :class="currentConversationId === c.conversation_id ? 'bg-primary-foreground' : ''"
                        />
                      </div>
                    </div>
                    
                    <!-- Meta Row -->
                    <div class="flex items-center gap-2 text-[11px]" :class="currentConversationId === c.conversation_id ? 'opacity-70' : 'opacity-50'">
                      <!-- Flag -->
                      <img 
                        v-if="c.country && getFlagPath(c.country)" 
                        :src="getFlagPath(c.country)!" 
                        :alt="c.country"
                        class="w-3 h-3 rounded-sm object-cover"
                        :title="c.country.toUpperCase()"
                      />
                      
                      <!-- Topic -->
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

          <!-- Messages Column -->
          <div class="flex-1 flex flex-col h-full overflow-hidden bg-background">
            <!-- Header -->
            <div class="px-5 py-4 flex-shrink-0 border-b space-y-3">
              <!-- Top Row: Search + Actions -->
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
                
                <!-- Lead Button (pushed to right) -->
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
                    value="details" 
                    class="relative px-4 py-2.5 rounded-none bg-transparent text-muted-foreground hover:text-foreground transition-colors data-[state=active]:text-foreground data-[state=active]:bg-transparent data-[state=active]:shadow-none font-medium text-sm cursor-pointer after:absolute after:bottom-0 after:left-0 after:right-0 after:h-0.5 after:bg-transparent data-[state=active]:after:bg-primary"
                  >
                    <Tag class="h-4 w-4 mr-2" />
                    Details
                  </TabsTrigger>
                </TabsList>
              </div>

              <!-- Chat Tab Content -->
              <TabsContent value="chat" class="flex-1 flex flex-col min-h-0 mt-0 overflow-hidden">
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
                          <div 
                            v-if="m.role === 'user' && m.tone"
                            class="group relative cursor-help"
                          >
                            <span>{{ getToneEmoji(m.tone) }}</span>
                            <span class="absolute bottom-full right-0 mb-1.5 px-2 py-1 bg-gray-900 text-white text-[10px] rounded opacity-0 pointer-events-none group-hover:opacity-100 transition-opacity z-50 shadow-lg whitespace-nowrap">
                              Tone: {{ getToneDisplayName(m.tone) }}
                            </span>
                          </div>

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
              </TabsContent>

              <!-- Details Tab Content -->
              <TabsContent value="details" class="flex-1 min-h-0 mt-0 overflow-auto">
                <div v-if="!currentConversationId" class="flex flex-col items-center justify-center py-20 text-muted-foreground">
                  <MessageSquare class="h-10 w-10 mb-3 opacity-30" />
                  <p class="text-sm">Select a conversation</p>
                </div>
                <div v-else-if="currentConversationData" class="p-5">
                  <div class="rounded-lg border bg-card">
                    <table class="w-full text-sm">
                      <tbody class="divide-y">
                        <!-- Messages -->
                        <tr>
                          <td class="px-4 py-3 text-muted-foreground font-medium w-1/3">Messages</td>
                          <td class="px-4 py-3">{{ totalMessages }}</td>
                        </tr>
                        
                        <!-- Last Activity -->
                        <tr>
                          <td class="px-4 py-3 text-muted-foreground font-medium">Last Activity</td>
                          <td class="px-4 py-3">{{ formatTimeAgo(currentConversationData.last_at) }}</td>
                        </tr>
                        
                        <!-- Topic -->
                        <tr v-if="currentConversationData.topic">
                          <td class="px-4 py-3 text-muted-foreground font-medium">Topic</td>
                          <td class="px-4 py-3">{{ currentConversationData.topic }}</td>
                        </tr>
                        
                        <!-- Sentiment -->
                        <tr v-if="messages.find(m => m.role === 'user' && m.sentiment)">
                          <td class="px-4 py-3 text-muted-foreground font-medium">Sentiment</td>
                          <td class="px-4 py-3">
                            <span class="capitalize">{{ messages.find(m => m.role === 'user' && m.sentiment)?.sentiment }}</span>
                          </td>
                        </tr>
                        
                        <!-- Tone -->
                        <tr v-if="messages.find(m => m.role === 'user' && m.tone)">
                          <td class="px-4 py-3 text-muted-foreground font-medium">Tone</td>
                          <td class="px-4 py-3">
                            {{ getToneEmoji(messages.find(m => m.role === 'user' && m.tone)?.tone || '') }}
                            {{ getToneDisplayName(messages.find(m => m.role === 'user' && m.tone)?.tone || '') }}
                          </td>
                        </tr>
                        
                        <!-- Country -->
                        <tr v-if="currentConversationData.country">
                          <td class="px-4 py-3 text-muted-foreground font-medium">Country</td>
                          <td class="px-4 py-3">
                            <div class="flex items-center gap-2">
                              <img 
                                v-if="getFlagPath(currentConversationData.country)"
                                :src="getFlagPath(currentConversationData.country)!" 
                                :alt="currentConversationData.country_name || currentConversationData.country"
                                class="w-5 h-4 rounded-sm object-cover"
                              />
                              <span>{{ currentConversationData.country_name || currentConversationData.country.toUpperCase() }}</span>
                            </div>
                          </td>
                        </tr>
                        
                        <!-- City -->
                        <tr v-if="currentConversationData.city">
                          <td class="px-4 py-3 text-muted-foreground font-medium">City</td>
                          <td class="px-4 py-3">{{ currentConversationData.city }}</td>
                        </tr>
                        
                        <!-- Device -->
                        <tr v-if="currentConversationData.device_type">
                          <td class="px-4 py-3 text-muted-foreground font-medium">Device</td>
                          <td class="px-4 py-3 capitalize">{{ currentConversationData.device_type }}</td>
                        </tr>
                        
                        <!-- Browser -->
                        <tr v-if="currentConversationData.browser">
                          <td class="px-4 py-3 text-muted-foreground font-medium">Browser</td>
                          <td class="px-4 py-3">{{ currentConversationData.browser }}</td>
                        </tr>
                        
                        <!-- OS -->
                        <tr v-if="currentConversationData.os">
                          <td class="px-4 py-3 text-muted-foreground font-medium">Operating System</td>
                          <td class="px-4 py-3">{{ currentConversationData.os }}</td>
                        </tr>
                        
                        <!-- Page URL -->
                        <tr v-if="currentConversationData.page_url">
                          <td class="px-4 py-3 text-muted-foreground font-medium">Page URL</td>
                          <td class="px-4 py-3">
                            <a 
                              :href="currentConversationData.page_url" 
                              target="_blank" 
                              class="text-blue-600 dark:text-blue-400 hover:underline break-all"
                            >
                              {{ currentConversationData.page_url }}
                            </a>
                          </td>
                        </tr>
                        
                        <!-- Referrer -->
                        <tr v-if="currentConversationData.referrer_url">
                          <td class="px-4 py-3 text-muted-foreground font-medium">Referrer</td>
                          <td class="px-4 py-3">
                            <a 
                              :href="currentConversationData.referrer_url" 
                              target="_blank" 
                              class="text-blue-600 dark:text-blue-400 hover:underline break-all"
                            >
                              {{ currentConversationData.referrer_url }}
                            </a>
                          </td>
                        </tr>
                        
                        <!-- Locale -->
                        <tr v-if="currentConversationData.locale">
                          <td class="px-4 py-3 text-muted-foreground font-medium">Locale</td>
                          <td class="px-4 py-3">{{ currentConversationData.locale }}</td>
                        </tr>
                      </tbody>
                    </table>
                  </div>
                </div>
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
            <!-- User Message -->
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

            <!-- Bot Response -->
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

            <!-- Expected Response -->
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
      <Dialog v-model:open="showFiltersDialog">
        <DialogContent class="max-w-lg">
          <DialogHeader>
            <DialogTitle class="flex items-center gap-2">
              <Filter class="h-5 w-5" />
              Filter by
            </DialogTitle>
          </DialogHeader>

          <div class="space-y-4 py-2">
            <!-- Two Column Grid -->
            <div class="grid grid-cols-2 gap-4">
              <!-- Date Range -->
              <div class="space-y-2">
                <Label class="text-sm font-medium">Date range</Label>
                <DateRangePicker v-model="dateRange" class="w-full" />
              </div>
              
              <!-- Confidence Score -->
              <div class="space-y-2">
                <Label for="confidence-filter" class="text-sm font-medium">Confidence score</Label>
                <Select v-model="selectedConfidenceScore">
                  <SelectTrigger id="confidence-filter" class="w-full">
                    <SelectValue placeholder="Select score" />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="all">All scores</SelectItem>
                    <SelectItem value="high">High (≥80%)</SelectItem>
                    <SelectItem value="medium">Medium (65-80%)</SelectItem>
                    <SelectItem value="low">Low (&lt;65%)</SelectItem>
                  </SelectContent>
                </Select>
              </div>
              
              <!-- Source -->
              <div class="space-y-2">
                <Label for="source-filter" class="text-sm font-medium">Source</Label>
                <Select v-model="selectedSource">
                  <SelectTrigger id="source-filter" class="w-full">
                    <SelectValue placeholder="Select source" />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="all">All sources</SelectItem>
                    <SelectItem value="widget">Widget</SelectItem>
                    <SelectItem value="playground">Playground</SelectItem>
                    <SelectItem value="api">API</SelectItem>
                  </SelectContent>
                </Select>
              </div>
              
              <!-- Feedback -->
              <div class="space-y-2">
                <Label for="feedback-filter" class="text-sm font-medium">Feedback</Label>
                <Select v-model="selectedFeedback">
                  <SelectTrigger id="feedback-filter" class="w-full">
                    <SelectValue placeholder="Select feedback" />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="all">All feedback</SelectItem>
                    <SelectItem value="positive">
                      <div class="flex items-center gap-2">
                        <ThumbsUp class="h-4 w-4 text-green-600" />
                        Thumbs Up
                      </div>
                    </SelectItem>
                    <SelectItem value="negative">
                      <div class="flex items-center gap-2">
                        <ThumbsDown class="h-4 w-4 text-red-600" />
                        Thumbs Down
                      </div>
                    </SelectItem>
                  </SelectContent>
                </Select>
              </div>
              
              <!-- Country -->
              <div class="space-y-2">
                <Label for="country-filter" class="text-sm font-medium">Country</Label>
                <Select v-model="selectedCountry">
                  <SelectTrigger id="country-filter" class="w-full">
                    <SelectValue placeholder="Select country" />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="all">All countries</SelectItem>
                    <SelectItem 
                      v-for="country in countries" 
                      :key="country.id" 
                      :value="String(country.id)"
                    >
                      <div class="flex items-center gap-2">
                        <img 
                          v-if="country.code && getFlagPath(country.code)"
                          :src="getFlagPath(country.code)!" 
                          :alt="country.code"
                          class="w-4 h-3 rounded-sm object-cover"
                        />
                        {{ country.name }}
                      </div>
                    </SelectItem>
                  </SelectContent>
                </Select>
              </div>
              
              <!-- Topic -->
              <div class="space-y-2">
                <Label for="topic-filter" class="text-sm font-medium">Topic</Label>
                <Select v-model="selectedTopic">
                  <SelectTrigger id="topic-filter" class="w-full">
                    <SelectValue placeholder="Select topic" />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="all">All topics</SelectItem>
                    <SelectItem 
                      v-for="topic in topics" 
                      :key="topic.id" 
                      :value="String(topic.id)"
                    >
                      {{ topic.topic }}
                    </SelectItem>
                  </SelectContent>
                </Select>
              </div>
              
              <!-- Sentiment -->
              <div class="space-y-2">
                <Label for="sentiment-filter" class="text-sm font-medium">Sentiment</Label>
                <Select v-model="selectedSentiment">
                  <SelectTrigger id="sentiment-filter" class="w-full">
                    <SelectValue placeholder="Select sentiment" />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="all">All sentiments</SelectItem>
                    <SelectItem value="positive">Positive</SelectItem>
                    <SelectItem value="neutral">Neutral</SelectItem>
                    <SelectItem value="negative">Negative</SelectItem>
                  </SelectContent>
                </Select>
              </div>
              
              <!-- Tone -->
              <div class="space-y-2">
                <Label for="tone-filter" class="text-sm font-medium">Tone</Label>
                <Select v-model="selectedTone">
                  <SelectTrigger id="tone-filter" class="w-full">
                    <SelectValue placeholder="Select tone" />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="all">All tones</SelectItem>
                    <SelectItem value="neutral">😐 Neutral</SelectItem>
                    <SelectItem value="frustrated">😤 Frustrated</SelectItem>
                    <SelectItem value="urgent">⚡ Urgent</SelectItem>
                    <SelectItem value="confused">😕 Confused</SelectItem>
                    <SelectItem value="satisfied">😊 Satisfied</SelectItem>
                    <SelectItem value="formal">👔 Formal</SelectItem>
                    <SelectItem value="casual">😎 Casual</SelectItem>
                  </SelectContent>
                </Select>
              </div>
            </div>
          </div>

          <DialogFooter class="flex gap-2 sm:gap-2">
            <Button 
              variant="ghost" 
              @click="clearAllFilters" 
              :disabled="!hasActiveFilters"
              class="text-destructive hover:text-destructive"
            >
              Clear all
            </Button>
            <Button @click="applyFilters">
              Close
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </AgentLayout>
  </template>

