<script lang="ts" setup>
import AgentLayout from '@/layouts/AgentLayout.vue';
import { computed, ref, watch } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { LoaderCircle, Search, MessageSquare, RefreshCcw, User as UserIcon, X } from 'lucide-vue-next';
import LeadDetailsSheet from '@/components/LeadDetailsSheet.vue';
import MessageCard from '@/components/chat/MessageCard.vue';

interface Message {
  id: string;
  content: string;
  role: 'user' | 'assistant';
  model?: string;
  created_at: string;
  session_id?: string;
  conversation_id?: string;
}

interface ConversationSummary {
  conversation_id: string;
  last_at: string;
  messages_count: number;
  first_message_excerpt?: string;
}

const page = usePage();
const agent = page.props.agent as any;
const messagesData = page.props.messages as any; // may be empty structure when no session selected
const conversations = page.props.conversations as any; // Infinite scroll paginated data
const currentConversationId = page.props.current_conversation_id as string | null;
const conversationTitle = page.props.conversation_title as string | null;
const leadConversationIds = page.props.lead_conversation_ids as string[];
const lead = page.props.lead as any | null;
const showLeadPanel = ref(false);
const hasLeadForCurrentConversation = computed(() => !!lead);

// Total conversations count
const totalConversations = computed(() => conversations?.total || 0);

// Current conversations page
const currentConversationsPage = computed(() => conversations?.current_page || 1);

function openLeadPanel() {
  if (!hasLeadForCurrentConversation.value) return;
  showLeadPanel.value = true;
}


// Page title (could include session short id)
const title = computed(() => conversationTitle ? `Chat Log – ${conversationTitle}` : 'Chat Log');

// Client-side search across loaded page messages (content + role + model)
const search = ref('');
const messages = computed(() => {
  const list: Message[] = (messagesData && messagesData.data) ? messagesData.data : [];
  if (!search.value.trim()) return list;
  const q = search.value.toLowerCase();
  return list.filter(m =>
    (m.content && m.content.toLowerCase().includes(q)) ||
    (m.role && m.role.toLowerCase().includes(q)) ||
    (m.model && m.model.toLowerCase().includes(q))
  );
});

const totalMessages = computed(() => messagesData?.data?.length || 0);
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

// Force reload if we have a session id but missing message data (edge cases)
watch(() => currentConversationId, (val) => {
  if (val && (!messagesData || !messagesData.data)) {
    router.get(route('agents.logs', { agent: agent.id, conversation_id: val }));
  }
  // Clear search when switching conversations
  search.value = '';
});

const isRefreshing = ref(false);
function refresh() {
  isRefreshing.value = true;
  router.reload({ only: ['messages', 'conversations', 'lead', 'lead_conversation_ids'], onFinish: () => { isRefreshing.value = false; } });
}

function formatDate(dt: string | null | undefined) {
  if (!dt) return '—';
  try {
    return new Intl.DateTimeFormat('en-US', { dateStyle: 'short', timeStyle: 'short' }).format(new Date(dt));
  } catch {
    return dt;
  }
}
</script>

<template>
    <AgentLayout :agent="agent" :title="title">
      <div class="space-y-6">


  <div class="grid lg:grid-cols-12 gap-6">
          <!-- Conversations Sidebar -->
          <Card class="lg:col-span-3 flex flex-col sticky top-6 h-[calc(100vh-220px)] self-start">
            <CardHeader class="pb-4 flex-shrink-0">
              <CardTitle class="text-sm font-medium">Conversations</CardTitle>
              <CardDescription>Select a conversation to view its messages.</CardDescription>
            </CardHeader>
            <CardContent class="p-0 flex-1 overflow-y-auto min-h-0">
              <div class="px-4 pb-3 pt-2 flex-shrink-0 border-b sticky top-0 bg-card z-10">
                <div class="flex items-center justify-between text-xs text-muted-foreground">
                  <span>{{ totalConversations }} conversations</span>
                </div>
              </div>
              <ul class="space-y-1 p-2">
                <li v-for="c in conversations.data" :key="c.conversation_id">
                  <Link
                    :href="route('agents.logs', { agent: agent.id, conversation_id: c.conversation_id, conversations: currentConversationsPage })"
                    preserve-scroll
                    class="w-full text-left px-3 py-2 rounded-md text-sm flex flex-col gap-0.5 transition-colors"
                    :class="currentConversationId === c.conversation_id ? 'bg-primary text-primary-foreground shadow-sm' : 'hover:bg-muted'"
                  >
                    <div class="flex items-center justify-between">
                      <span class="truncate max-w-[180px] font-medium" :title="c.first_message_excerpt || c.conversation_id">
                        {{ c.first_message_excerpt || c.conversation_id.slice(0,8) }}
                      </span>
                      <div class="flex items-center gap-1">
                        <Badge v-if="leadConversationIds.includes(c.conversation_id)" variant="secondary" class="text-[10px] px-1 py-0 font-normal">Lead</Badge>
                        <Badge v-if="currentConversationId !== c.conversation_id" variant="outline" class="text-[10px] px-1 py-0 font-normal">
                          {{ c.messages_count }}
                        </Badge>
                      </div>
                    </div>
                    <span class="text-[10px] opacity-80">{{ formatDate(c.last_at) }}</span>
                  </Link>
                </li>
              </ul>
            </CardContent>

            <!-- Pagination Controls - Outside scrollable area -->
            <div v-if="conversations.last_page > 1" class="border-t p-3 flex-shrink-0">
              <div class="flex justify-between items-center gap-2">
                <Button
                  variant="outline"
                  size="sm"
                  :disabled="!conversations.prev_page_url"
                  @click="router.get(conversations.prev_page_url)"
                >Prev</Button>
                <div class="text-xs text-muted-foreground">
                  Page {{ conversations.current_page }} / {{ conversations.last_page }}
                </div>
                <Button
                  variant="outline"
                  size="sm"
                  :disabled="!conversations.next_page_url"
                  @click="router.get(conversations.next_page_url)"
                >Next</Button>
              </div>
            </div>
          </Card>

          <!-- Messages Column -->
          <div class="lg:col-span-9 flex flex-col min-h-[calc(100vh-220px)]">
            <Card class="flex flex-col overflow-hidden">
              <CardHeader class="pb-3 flex-shrink-0 space-y-3">
                <!-- Search Bar -->
                <div class="relative">
                  <Search class="absolute left-2.5 top-2.5 h-4 w-4 text-muted-foreground" />
                  <Input 
                    v-model="search" 
                    placeholder="Search messages in this conversation..." 
                    class="pl-8 h-9 pr-8" 
                    :disabled="!currentConversationId"
                  />
                  <button
                    v-if="search"
                    @click="clearSearch"
                    class="absolute right-2.5 top-2.5 h-4 w-4 text-muted-foreground hover:text-foreground transition-colors"
                  >
                    <X class="h-4 w-4" />
                  </button>
                </div>

                <!-- Action Bar -->
                <div class="flex flex-wrap items-center justify-between gap-2">
                  <div class="text-xs text-muted-foreground">
                    <span v-if="currentConversationId && search">
                      Showing {{ filteredCount }} of {{ totalMessages }} messages
                    </span>
                    <span v-else-if="currentConversationId">
                      {{ totalMessages }} messages
                    </span>
                  </div>
                  <div class="flex gap-2">
                    <Button variant="outline" size="sm" @click="refresh" :disabled="isRefreshing">
                      <LoaderCircle v-if="isRefreshing" class="h-4 w-4 mr-1 animate-spin" />
                      <RefreshCcw v-else class="h-4 w-4 mr-1" /> Refresh
                    </Button>
                    <Button 
                      size="sm" 
                      :variant="hasLeadForCurrentConversation ? 'default' : 'outline'" 
                      :disabled="!hasLeadForCurrentConversation" 
                      @click="openLeadPanel"
                    >
                      <UserIcon class="h-4 w-4 mr-1" /> View Lead
                    </Button>
                  </div>
                </div>
              </CardHeader>

              <CardContent class="p-0">
                  <div class="px-6 pb-6 pt-2">
                    <div v-if="!currentConversationId" class="text-center py-12 text-sm text-muted-foreground">
                      <MessageSquare class="h-12 w-12 mx-auto mb-3 opacity-20" />
                      <p>Select a conversation to view its messages.</p>
                    </div>
                    <div v-else-if="messages.length === 0 && !search" class="text-center py-12 text-sm text-muted-foreground">
                      <MessageSquare class="h-12 w-12 mx-auto mb-3 opacity-20" />
                      <p>No messages found for this conversation.</p>
                    </div>
                    <div v-else-if="messages.length === 0 && search" class="text-center py-12 text-sm text-muted-foreground">
                      <Search class="h-12 w-12 mx-auto mb-3 opacity-20" />
                      <p>No messages match your search.</p>
                      <Button variant="link" size="sm" @click="clearSearch" class="mt-2">
                        Clear search
                      </Button>
                    </div>
                    <div v-else class="space-y-4">
                      <div v-for="m in messages" :key="m.id" class="border rounded-lg p-3 bg-card/50 transition-shadow hover:shadow-sm">
                        <div class="flex items-center justify-between mb-2">
                          <div class="flex items-center gap-2 text-xs text-muted-foreground">
                            <Badge variant="outline" class="text-[10px] uppercase">{{ m.role }}</Badge>
                            <span>{{ formatDate(m.created_at) }}</span>
                            <span v-if="m.model" class="hidden sm:inline">• {{ m.model }}</span>
                          </div>
                        </div>
                        <div v-if="search" v-html="highlightText(m.content, search)" class="prose prose-sm dark:prose-invert max-w-none"></div>
                        <MessageCard v-else :message="m" :hide-feedback="true" />
                      </div>
                    </div>
                  </div>

                <!-- Pagination -->
                <div v-if="currentConversationId && messagesData?.last_page > 1" class="border-t p-4 flex-shrink-0">
                  <div class="flex justify-between items-center">
                    <Button
                      variant="outline"
                      size="sm"
                      :disabled="!messagesData?.prev_page_url"
                      @click="router.get(messagesData?.prev_page_url)"
                    >Prev</Button>
                    <div class="text-xs text-muted-foreground">
                      Page {{ messagesData?.current_page }} / {{ messagesData?.last_page }}
                    </div>
                    <Button
                      variant="outline"
                      size="sm"
                      :disabled="!messagesData?.next_page_url"
                      @click="router.get(messagesData?.next_page_url)"
                    >Next</Button>
                  </div>
                </div>
              </CardContent>
            </Card>
          </div>
        </div>
      </div>

      <!-- Lead Details Sheet -->
      <LeadDetailsSheet 
        v-model:open="showLeadPanel" 
        :lead="lead" 
      />
    </AgentLayout>
  </template>
