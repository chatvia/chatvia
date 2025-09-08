<script lang="ts" setup>
import AgentLayout from '@/layouts/AgentLayout.vue';
import { computed, ref, watch } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { ScrollArea } from '@/components/ui/scroll-area';
import { LoaderCircle, Search, MessageSquare, RefreshCcw, FilterX, User as UserIcon, Mail, Phone, Building2 } from 'lucide-vue-next';
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
const conversations = page.props.conversations as ConversationSummary[];
const currentConversationId = page.props.current_conversation_id as string | null;
const conversationTitle = page.props.conversation_title as string | null;
const leadConversationIds = page.props.lead_conversation_ids as string[];
const lead = page.props.lead as any | null;
const showLeadPanel = ref(false);
const hasLeadForCurrentConversation = computed(() => !!lead);

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


// Force reload if we have a session id but missing message data (edge cases)
watch(() => currentConversationId, (val) => {
  if (val && (!messagesData || !messagesData.data)) {
    router.get(route('agents.logs', { agent: agent.id, conversation_id: val }));
  }
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


  <div class="grid lg:grid-cols-12 gap-6 min-h-[calc(100vh-220px)]">
          <!-- Sessions Sidebar -->
          <Card class="lg:col-span-3 h-full">
            <CardHeader class="pb-4">
              <CardTitle class="text-sm font-medium">Conversations</CardTitle>
              <CardDescription>Select a conversation to view its messages.</CardDescription>
            </CardHeader>
            <CardContent class="p-0">
              <div class="px-4 pb-3">
                <div class="relative">
                  <Search class="absolute left-2.5 top-2.5 h-4 w-4 text-muted-foreground" />
                  <Input v-model="search" placeholder="Search in loaded messages" class="pl-8 h-9" />
                </div>
                <div class="flex items-center justify-between mt-3 text-xs text-muted-foreground">
                  <span>{{ totalMessages }} msgs</span>
                  <!-- Clear link removed as 'All Sessions' view deprecated -->
                </div>
              </div>
              <ScrollArea class="h-[420px] pr-2">
                <ul class="space-y-1 px-2">
                  <!-- 'All Sessions' option removed -->
                  <li v-for="c in conversations" :key="c.conversation_id">
                    <Link
                      :href="route('agents.logs', { agent: agent.id, conversation_id: c.conversation_id })"
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
              </ScrollArea>
            </CardContent>
          </Card>

          <!-- Messages Column -->
          <div class="lg:col-span-9 flex flex-col">
            <Card class="flex-1 flex flex-col h-[calc(100vh-260px)]">
              <CardHeader class="pb-3">
                <div class="flex flex-wrap items-center justify-end gap-2">
                  <div>
                      <div class="flex flex-col sm:flex-row gap-3 sm:items-center justify-end">
                          <div class="flex gap-2">
                              <Button variant="outline" size="sm" @click="refresh" :disabled="isRefreshing">
                                  <LoaderCircle v-if="isRefreshing" class="h-4 w-4 mr-1 animate-spin" />
                                  <RefreshCcw v-else class="h-4 w-4 mr-1" /> Refresh
                              </Button>
                              <Button size="sm" :variant="hasLeadForCurrentConversation ? 'default' : 'outline'" :disabled="!hasLeadForCurrentConversation" @click="openLeadPanel">
                                  <UserIcon class="h-4 w-4 mr-1" /> View Lead
                              </Button>
                          </div>
                      </div>
                  </div>
                </div>
              </CardHeader>
              <CardContent class="p-0 flex-1 min-h-0">
                <ScrollArea class="h-full">
                  <div class="px-6 pb-6 pt-2">
                    <div v-if="!currentConversationId" class="text-center py-12 text-sm text-muted-foreground">
                      Select a conversation to view its messages.
                    </div>
                    <div v-else-if="messages.length === 0" class="text-center py-12 text-sm text-muted-foreground">
                      No messages found for this conversation.
                    </div>
                    <div v-else class="space-y-4">
                      <div v-for="m in messages" :key="m.id" class="border rounded-lg p-3 bg-card/50">
                        <div class="flex items-center justify-between mb-2">
                          <div class="flex items-center gap-2 text-xs text-muted-foreground">
                            <Badge variant="outline" class="text-[10px] uppercase">{{ m.role }}</Badge>
                            <span>{{ formatDate(m.created_at) }}</span>
                            <span v-if="m.model" class="hidden sm:inline">• {{ m.model }}</span>

                          </div>
                        </div>
                        <MessageCard :message="m" :hide-feedback="true" />
                      </div>
                      <div v-if="currentConversationId && messagesData?.meta" class="sticky bottom-0 bg-gradient-to-t from-background via-background/95 to-background/0 pt-4 mt-6">
                        <div class="flex justify-between items-center border-t pt-4">
                          <Button
                            variant="outline"
                            size="sm"
                            :disabled="!messagesData?.links?.prev"
                            @click="router.get(messagesData?.links?.prev?.url)"
                          >Prev</Button>
                          <div class="text-xs text-muted-foreground">Page {{ messagesData?.meta?.current_page }} / {{ messagesData?.meta?.last_page }}</div>
                          <Button
                            variant="outline"
                            size="sm"
                            :disabled="!messagesData?.links?.next"
                            @click="router.get(messagesData?.links?.next?.url)"
                          >Next</Button>
                        </div>
                      </div>
                    </div>
                  </div>
                </ScrollArea>
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
