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
import { LoaderCircle, Search, MessageSquare, RefreshCcw, X, RotateCcw, ThumbsUp, ThumbsDown, Download, Filter, UserCheck } from 'lucide-vue-next';
import LeadDetailsSheet from '@/components/LeadDetailsSheet.vue';
import MessageCard from '@/components/chat/MessageCard.vue';
import { useTimeAgo } from '@/composables/useTimeAgo';
import axios from 'axios';

interface Message {
  id: string;
  content: string;
  role: 'user' | 'assistant';
  model?: string;
  created_at: string;
  session_id?: string;
  conversation_id?: string;
  feedback?: 'positive' | 'negative' | null;
  sentiment?: string | null;
  emotion?: string | null;
  emotion_confidence?: number | null;
  agent_question_answer_id?: number | null;
  is_direct_response?: boolean | null;
}

interface ConversationSummary {
  conversation_id: string;
  last_at: string;
  messages_count: number;
  first_message_excerpt?: string;
  country?: string | null;
  user_agent?: string | null;
  device_type?: string | null;
  browser?: string | null;
  os?: string | null;
  city?: string | null;
  referrer_url?: string | null;
  page_url?: string | null;
  locale?: string | null;
  topic?: string | null;
  topic_id?: number | null;
}

interface Topic {
  id: number;
  topic: string;
  description: string;
}

interface AIModel {
  id: string;
  name: string;
  icon: string;
  context_window: number;
  description: string;
  location: string;
  reasoning: boolean;
  user_allowed_to_use: boolean;
  is_selected: boolean;
}

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

// Loading states
const isLoadingConversations = ref(false);
const isLoadingMessages = ref(false);

// Filters
const topics = ref<Topic[]>([]);
const selectedTopic = ref<string>('all');
const selectedSentiment = ref<string>('all');
const selectedEmotion = ref<string>('all');
const showFiltersPopover = ref(false);

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
    if (selectedEmotion.value && selectedEmotion.value !== 'all') {
      params.emotion = selectedEmotion.value;
    }
    
    const response = await axios.get(route('agents.logs.conversations', { agent: agent.id }), {
      params
    });
    conversations.value = response.data.conversations;
    leadConversationIds.value = response.data.lead_conversation_ids;
    
    // Update current conversation data if viewing a conversation
    if (currentConversationId.value) {
      currentConversationData.value = response.data.conversations?.data?.find((c: any) => c.conversation_id === currentConversationId.value) || currentConversationData.value;
    }
    
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
    // Fetch models, topics, and conversations in parallel
    const [modelsResponse, topicsResponse] = await Promise.all([
      axios.get(route('agents.models.list', { agent: agent.id })),
      axios.get(route('agents.logs.topics', { agent: agent.id }))
    ]);
    
    models.value = modelsResponse.data.models || [];
    topics.value = topicsResponse.data.topics || [];
    
    // Fetch conversations
    await fetchConversations();
    
    // Fetch messages if there's a current conversation
    if (currentConversationId.value) {
      await fetchMessages(currentConversationId.value);
      // Set current conversation data
      currentConversationData.value = conversations.value?.data?.find((c: any) => c.conversation_id === currentConversationId.value) || null;
    }
  } catch (error) {
    console.error('Failed to fetch initial data:', error);
  }
});

// Watch for conversation changes
watch(() => currentConversationId.value, (newId, oldId) => {
  if (newId) {
    fetchMessages(newId);
    // Update current conversation data
    currentConversationData.value = conversations.value?.data?.find((c: any) => c.conversation_id === newId) || null;
    
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

// Watch for filter changes and automatically apply them
watch([selectedTopic, selectedSentiment, selectedEmotion], () => {
  fetchConversations(1);
});

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
    (m.model && m.model.toLowerCase().includes(q))
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

const isRefreshing = ref(false);
async function refresh() {
  isRefreshing.value = true;
  try {
    await Promise.all([
      fetchConversations(currentConversationsPage.value),
      currentConversationId.value ? fetchMessages(currentConversationId.value) : Promise.resolve()
    ]);
  } finally {
    isRefreshing.value = false;
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
function getFeedbackInfo(feedback: 'positive' | 'negative' | null | undefined): { icon: string; color: string; label: string } | null {
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
  selectedEmotion.value = 'all';
  showFiltersPopover.value = false;
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

function clearEmotionFilter() {
  selectedEmotion.value = 'all';
  fetchConversations(1);
}

// Check if any filters are active
const hasActiveFilters = computed(() => {
  return selectedTopic.value !== 'all' || selectedSentiment.value !== 'all' || selectedEmotion.value !== 'all';
});

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

// Get emotion display name
function getEmotionDisplayName(emotion: string): string {
  const names: Record<string, string> = {
    'happy': 'Happy',
    'excited': 'Excited',
    'satisfied': 'Satisfied',
    'neutral': 'Neutral',
    'confused': 'Confused',
    'frustrated': 'Frustrated',
    'sad': 'Sad',
    'anxious': 'Anxious',
    'angry': 'Angry'
  };
  return names[emotion] || emotion;
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

// Get emotion emoji
function getEmotionEmoji(emotion: string): string {
  const emojis: Record<string, string> = {
    'happy': '😊',
    'excited': '😃',
    'satisfied': '😌',
    'neutral': '😐',
    'confused': '😕',
    'frustrated': '😤',
    'sad': '😢',
    'anxious': '😰',
    'angry': '😠'
  };
  return emojis[emotion] || '😐';
}

// Get emotion color
function getEmotionBadgeColor(emotion: string): string {
  const colors: Record<string, string> = {
    'happy': 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300',
    'excited': 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-300',
    'satisfied': 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300',
    'neutral': 'bg-gray-100 text-gray-800 dark:bg-gray-900/30 dark:text-gray-300',
    'confused': 'bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-300',
    'frustrated': 'bg-orange-100 text-orange-800 dark:bg-orange-900/30 dark:text-orange-300',
    'sad': 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900/30 dark:text-indigo-300',
    'anxious': 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300',
    'angry': 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300'
  };
  return colors[emotion] || 'bg-gray-100 text-gray-800';
}
</script>

<template>
    <AgentLayout :agent="agent" :title="title" hide-header no-padding full-height>
      <div class="flex gap-0 h-full">
          <!-- Conversations Sidebar -->
          <div class="w-[300px] flex-shrink-0 flex flex-col h-full bg-muted/30 border-r">
            <div class="p-0 flex-1 overflow-y-auto min-h-0">
              <div class="px-4 pb-3 pt-4 flex-shrink-0 border-b border-border/50 sticky top-0 bg-muted/30 backdrop-blur-sm z-10">
                <div class="flex items-center justify-between mb-3">
                  <h2 class="text-sm font-semibold tracking-tight">Conversations</h2>
                  <div class="flex items-center gap-1">
                    <!-- Filters Button -->
                    <Popover v-model:open="showFiltersPopover">
                      <PopoverTrigger as-child>
                        <Button 
                          variant="ghost" 
                          size="icon" 
                          class="h-8 w-8 relative"
                          :class="{ 'text-primary': hasActiveFilters }"
                        >
                          <Filter class="h-4 w-4" />
                          <span v-if="hasActiveFilters" class="absolute -top-0.5 -right-0.5 h-2 w-2 bg-primary rounded-full"></span>
                        </Button>
                      </PopoverTrigger>
                      <PopoverContent class="w-80" align="start">
                          <div class="space-y-4">
                            <div class="space-y-2">
                              <h4 class="font-medium leading-none">Filters</h4>
                              <p class="text-sm text-muted-foreground">
                                Filter conversations by topic, sentiment, and emotion
                              </p>
                            </div>
                            
                            <!-- Topic Filter -->
                            <div class="space-y-2">
                              <Label for="topic-filter" class="text-sm font-medium">Topic</Label>
                              <Select v-model="selectedTopic">
                                <SelectTrigger id="topic-filter">
                                  <SelectValue placeholder="All topics" />
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
                            
                            <!-- Sentiment Filter -->
                            <div class="space-y-2">
                              <Label for="sentiment-filter" class="text-sm font-medium">Sentiment</Label>
                              <Select v-model="selectedSentiment">
                                <SelectTrigger id="sentiment-filter">
                                  <SelectValue placeholder="All sentiments" />
                                </SelectTrigger>
                                <SelectContent>
                                  <SelectItem value="all">All sentiments</SelectItem>
                                  <SelectItem value="positive">Positive</SelectItem>
                                  <SelectItem value="neutral">Neutral</SelectItem>
                                  <SelectItem value="negative">Negative</SelectItem>
                                </SelectContent>
                              </Select>
                            </div>
                            
                            <!-- Emotion Filter -->
                            <div class="space-y-2">
                              <Label for="emotion-filter" class="text-sm font-medium">Emotion</Label>
                              <Select v-model="selectedEmotion">
                                <SelectTrigger id="emotion-filter">
                                  <SelectValue placeholder="All emotions" />
                                </SelectTrigger>
                                <SelectContent>
                                  <SelectItem value="all">All emotions</SelectItem>
                                  <SelectItem value="happy">😊 Happy</SelectItem>
                                  <SelectItem value="excited">😃 Excited</SelectItem>
                                  <SelectItem value="satisfied">😌 Satisfied</SelectItem>
                                  <SelectItem value="neutral">😐 Neutral</SelectItem>
                                  <SelectItem value="confused">😕 Confused</SelectItem>
                                  <SelectItem value="frustrated">😤 Frustrated</SelectItem>
                                  <SelectItem value="sad">😢 Sad</SelectItem>
                                  <SelectItem value="anxious">😰 Anxious</SelectItem>
                                  <SelectItem value="angry">😠 Angry</SelectItem>
                                </SelectContent>
                              </Select>
                            </div>
                          
                          <!-- Actions -->
                          <div class="flex gap-2 pt-2">
                            <Button size="sm" variant="outline" class="w-full" @click="clearAllFilters" :disabled="!hasActiveFilters">
                              <X class="h-3 w-3 mr-1" />
                              Clear Filters
                            </Button>
                          </div>
                        </div>
                      </PopoverContent>
                    </Popover>
                    
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
                  <Badge 
                    v-if="selectedTopic !== 'all'" 
                    variant="secondary" 
                    class="text-xs px-2 py-1 flex items-center gap-1"
                  >
                    {{ getTopicName(Number(selectedTopic)) }}
                    <button @click="clearTopicFilter" class="ml-1 hover:text-foreground">
                      <X class="h-3 w-3" />
                    </button>
                  </Badge>
                  <Badge 
                    v-if="selectedSentiment !== 'all'" 
                    :class="getSentimentBadgeColor(selectedSentiment)"
                    class="text-xs px-2 py-1 flex items-center gap-1"
                  >
                    {{ getSentimentDisplayName(selectedSentiment) }}
                    <button @click="clearSentimentFilter" class="ml-1 hover:opacity-70">
                      <X class="h-3 w-3" />
                    </button>
                  </Badge>
                  <Badge 
                    v-if="selectedEmotion !== 'all'" 
                    :class="getEmotionBadgeColor(selectedEmotion)"
                    class="text-xs px-2 py-1 flex items-center gap-1"
                  >
                    {{ getEmotionEmoji(selectedEmotion) }} {{ getEmotionDisplayName(selectedEmotion) }}
                    <button @click="clearEmotionFilter" class="ml-1 hover:opacity-70">
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
                    @click="() => { currentConversationId = c.conversation_id; currentConversationData = c; }"
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
          <div class="flex-1 flex flex-col overflow-hidden bg-background">
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
                
                <!-- Actions -->
                <div class="flex items-center gap-2">
                  <Button variant="ghost" size="icon" class="h-9 w-9" @click="refresh" :disabled="isRefreshing">
                    <RefreshCcw class="h-4 w-4" :class="{ 'animate-spin': isRefreshing }" />
                  </Button>
                  <Button 
                    size="sm" 
                    class="h-9"
                    :variant="hasLeadForCurrentConversation ? 'default' : 'outline'" 
                    :disabled="!hasLeadForCurrentConversation" 
                    @click="openLeadPanel"
                  >
                    <UserCheck class="h-4 w-4 mr-1.5" /> Lead
                  </Button>
                </div>
              </div>

              <!-- Session Info Row -->
              <div v-if="currentConversationData" class="flex items-center gap-1.5 flex-wrap">
                <span class="text-xs text-muted-foreground mr-1">{{ totalMessages }} messages</span>
                <span class="text-muted-foreground/30">|</span>
                
                <!-- Country -->
                <div v-if="currentConversationData.country" class="flex items-center gap-1 text-[11px] text-muted-foreground">
                  <img 
                    :src="getFlagPath(currentConversationData.country)!" 
                    :alt="currentConversationData.country"
                    class="w-3.5 h-3.5 rounded-sm object-cover"
                  />
                  <span>{{ currentConversationData.country.toUpperCase() }}</span>
                </div>
                
                <template v-if="currentConversationData.browser || currentConversationData.os">
                  <span class="text-muted-foreground/30">•</span>
                  <span class="text-[11px] text-muted-foreground">
                    {{ [currentConversationData.browser, currentConversationData.os].filter(Boolean).join(' / ') }}
                  </span>
                </template>
                
                <template v-if="currentConversationData.device_type">
                  <span class="text-muted-foreground/30">•</span>
                  <span class="text-[11px] text-muted-foreground capitalize">{{ currentConversationData.device_type }}</span>
                </template>
                
                <!-- Page URL -->
                <template v-if="currentConversationData.page_url">
                  <span class="text-muted-foreground/30">•</span>
                  <span class="text-[11px] text-muted-foreground truncate max-w-[200px] group relative cursor-help">
                    {{ getPathnameFromUrl(currentConversationData.page_url) }}
                    <span class="absolute bottom-full left-0 mb-2 px-2 py-1 bg-gray-900 text-white text-xs rounded whitespace-nowrap opacity-0 pointer-events-none group-hover:opacity-100 transition-opacity z-50 shadow-lg">
                      {{ currentConversationData.page_url }}
                    </span>
                  </span>
                </template>
              </div>
            </div>

            <!-- Messages Content -->
            <div class="flex-1 overflow-y-auto overflow-x-hidden min-h-0">
              <div class="px-6 py-4 h-full">
                <!-- Loading State -->
                <div v-if="isLoadingMessages" class="flex items-center justify-center h-full">
                  <LoaderCircle class="h-6 w-6 animate-spin text-muted-foreground" />
                </div>
                
                <!-- Empty States -->
                <div v-else-if="!currentConversationId" class="flex flex-col items-center justify-center h-full text-muted-foreground">
                  <MessageSquare class="h-10 w-10 mb-3 opacity-30" />
                  <p class="text-sm">Select a conversation</p>
                </div>
                <div v-else-if="messages.length === 0 && !search" class="flex flex-col items-center justify-center h-full text-muted-foreground">
                  <MessageSquare class="h-10 w-10 mb-3 opacity-30" />
                  <p class="text-sm">No messages yet</p>
                </div>
                <div v-else-if="messages.length === 0 && search" class="flex flex-col items-center justify-center h-full text-muted-foreground">
                  <Search class="h-10 w-10 mb-3 opacity-30" />
                  <p class="text-sm">No results found</p>
                  <Button variant="link" size="sm" @click="clearSearch" class="mt-1 h-auto p-0">
                    Clear search
                  </Button>
                </div>
                
                <!-- Messages List -->
                <div v-else class="space-y-4">
                  <div v-for="m in messages" :key="m.id">
                    <div 
                      class="flex"
                      :class="m.role === 'user' ? 'justify-end' : 'justify-start'"
                    >
                      <div 
                        class="flex flex-col gap-1.5 max-w-[70%]"
                        :class="m.role === 'user' ? 'items-end' : 'items-start'"
                      >
                        <!-- Message Bubble -->
                        <div 
                          class="rounded-2xl px-4 py-2.5"
                          :class="m.role === 'user' 
                            ? 'bg-primary text-primary-foreground rounded-br-md' 
                            : 'bg-muted/60 rounded-bl-md'"
                        >
                          <div v-if="search" v-html="highlightText(m.content, search)" class="prose prose-sm dark:prose-invert max-w-none"></div>
                          <div v-else-if="m.role === 'user'" class="text-sm whitespace-pre-wrap break-words">{{ m.content }}</div>
                          <MessageCard v-else :message="m" :hide-feedback="true" />
                        </div>

                        <!-- Meta Information -->
                        <div class="flex items-center gap-1.5 text-[11px] text-muted-foreground px-1 flex-wrap">
                          <!-- Emotion (for user messages) -->
                          <div 
                            v-if="m.role === 'user' && m.emotion"
                            class="group relative cursor-help"
                          >
                            <span>{{ getEmotionEmoji(m.emotion) }}</span>
                            <span class="absolute bottom-full right-0 mb-1.5 px-2 py-1 bg-gray-900 text-white text-[10px] rounded opacity-0 pointer-events-none group-hover:opacity-100 transition-opacity z-50 shadow-lg whitespace-nowrap">
                              Emotion: {{ getEmotionDisplayName(m.emotion) }}
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

                          <span v-if="m.role === 'user' && (m.emotion || m.sentiment)" class="text-muted-foreground/30">•</span>

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
                          <template v-if="m.role === 'assistant' && m.model">
                            <span class="text-muted-foreground/30">•</span>
                            <div class="group relative cursor-help">
                              <img 
                                :src="getModelIcon(m.model)" 
                                :alt="getModelName(m.model)"
                                class="w-3.5 h-3.5 rounded"
                              />
                              <span class="absolute bottom-full left-0 mb-1.5 px-2 py-1 bg-gray-900 text-white text-[10px] rounded opacity-0 pointer-events-none group-hover:opacity-100 transition-opacity z-50 shadow-lg whitespace-nowrap">
                                {{ getModelName(m.model) }}
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
                              class="text-green-600 dark:text-green-400"
                            >
                              ✓ Q&A
                            </span>
                            <span 
                              v-else-if="m.agent_question_answer_id"
                              class="text-green-600 dark:text-green-400"
                            >
                              ✓ Revised
                            </span>
                            <button
                              v-else
                              class="text-muted-foreground hover:text-foreground transition-colors flex items-center gap-0.5"
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
              </div>
            </div>

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
    </AgentLayout>
  </template>

