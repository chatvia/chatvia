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
import { Sheet, SheetContent, SheetHeader, SheetTitle, SheetDescription, SheetClose } from '@/components/ui/sheet';
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

// Lead presentation helpers (for avatar & status)
const leadInitials = computed(() => {
  if (!lead) return '';
  if (lead.name) return lead.name.split(' ').map((p:string)=>p[0]).join('').slice(0,2).toUpperCase();
  if (lead.email) return lead.email[0].toUpperCase();
  return '?';
});

const statusStyles: Record<string, string> = {
  new: 'bg-blue-500/10 text-blue-600 border-blue-200',
  contacted: 'bg-yellow-500/10 text-yellow-600 border-yellow-200',
  qualified: 'bg-green-500/10 text-green-600 border-green-200',
  converted: 'bg-purple-500/10 text-purple-600 border-purple-200'
};

const leadStatusClass = computed(() => {
  if (!lead || !lead.status) return statusStyles.new;
  return statusStyles[lead.status] || statusStyles.new;
});

// --- User Agent Parsing (lightweight) ---
interface ParsedUA { browser: string; os: string; device: string; raw: string; }
function parseUserAgent(ua?: string | null): ParsedUA | null {
  if (!ua) return null;
  const raw = ua;
  let browser = 'Unknown';
  let os = 'Unknown';
  let device = 'Desktop';

  // Browser detection order matters
  const browserRegexes: { name: string; regex: RegExp }[] = [
    { name: 'Edge', regex: /Edg\/(\d+[\.\d+]*)/ },
    { name: 'Opera', regex: /OPR\/(\d+[\.\d+]*)/ },
    { name: 'Chrome', regex: /Chrome\/(\d+[\.\d+]*)/ },
    { name: 'Firefox', regex: /Firefox\/(\d+[\.\d+]*)/ },
    { name: 'Safari', regex: /Version\/(\d+[\.\d+]*) Safari/ },
  ];
  for (const b of browserRegexes) {
    const m = ua.match(b.regex);
    if (m) { browser = `${b.name} ${m[1]}`; break; }
  }

  // OS detection
  if (/Windows NT 10\.0/.test(ua)) os = 'Windows 10/11';
  else if (/Windows NT 6\.3/.test(ua)) os = 'Windows 8.1';
  else if (/Windows NT 6\.2/.test(ua)) os = 'Windows 8';
  else if (/Windows NT 6\.1/.test(ua)) os = 'Windows 7';
  else if (/Mac OS X (\d+[._]\d+)/.test(ua)) {
    os = 'macOS ' + ua.match(/Mac OS X (\d+[._]\d+)/)![1].replace(/_/g,'.');
  } else if (/iPhone OS (\d+[._]\d+)/.test(ua)) {
    os = 'iOS ' + ua.match(/iPhone OS (\d+[._]\d+)/)![1].replace(/_/g,'.');
  } else if (/iPad; CPU OS (\d+[._]\d+)/.test(ua)) {
    os = 'iPadOS ' + ua.match(/iPad; CPU OS (\d+[._]\d+)/)![1].replace(/_/g,'.');
  } else if (/Android (\d+[\.\d]*)/.test(ua)) {
    os = 'Android ' + ua.match(/Android (\d+[\.\d]*)/)![1];
  } else if (/Linux/.test(ua)) os = 'Linux';

  // Device type
  if (/Mobile|iPhone|Android.*Mobile/.test(ua)) device = 'Mobile';
  else if (/Tablet|iPad|Android(?!.*Mobile)/.test(ua)) device = 'Tablet';

  return { browser, os, device, raw };
}

const parsedLeadUA = computed(() => parseUserAgent(lead?.user_agent));

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
                            <span v-if="m.model && $page.props.permissions.models.view" class="hidden sm:inline">• {{ m.model }}</span>

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

      <!-- Lead Info Sheet -->
      <Sheet v-model:open="showLeadPanel">
        <SheetContent side="right" class="w-full sm:max-w-xl overflow-y-auto p-0 flex flex-col">
          <div class="px-6 pt-6 pb-4 border-b bg-gradient-to-b from-background/60 to-background backdrop-blur supports-[backdrop-filter]:bg-background/40">
            <SheetHeader class="items-start text-left">
              <SheetTitle class="flex items-center gap-2 text-base font-semibold">
                <span>Lead Details</span>
                <Badge v-if="lead" :class="['border text-xs font-medium tracking-wide', leadStatusClass]">
                  {{ lead.status_label || lead.status }}
                </Badge>
              </SheetTitle>
              <SheetDescription v-if="lead" class="text-xs mt-1">
                Session <code class="font-mono text-[11px] px-1.5 py-0.5 rounded bg-muted">{{ lead.session_id }}</code>
              </SheetDescription>
              <SheetDescription v-else class="text-xs">No lead data.</SheetDescription>
            </SheetHeader>
          </div>

          <div v-if="lead" class="flex-1 overflow-y-auto px-6 py-6 space-y-8">
            <div class="flex items-start gap-4">
              <div class="relative h-14 w-14 rounded-full bg-primary/10 flex items-center justify-center text-sm font-semibold text-primary select-none">
                {{ leadInitials }}
              </div>
              <div class="flex-1 space-y-1">
                <h2 class="text-base font-semibold leading-tight">{{ lead.name || 'Unnamed Lead' }}</h2>
                <div class="flex flex-wrap items-center gap-2 text-xs text-muted-foreground">
                  <span v-if="lead.email" class="inline-flex items-center gap-1"><Mail class="h-3.5 w-3.5" />{{ lead.email }}</span>
                  <span v-if="lead.phone" class="inline-flex items-center gap-1"><Phone class="h-3.5 w-3.5" />{{ lead.phone }}</span>
                  <span v-if="lead.company" class="inline-flex items-center gap-1"><Building2 class="h-3.5 w-3.5" />{{ lead.company }}</span>
                </div>
              </div>
            </div>
            <div v-if="lead.message" class="space-y-2">
              <h3 class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Submitted Message</h3>
              <div class="text-sm leading-relaxed rounded-md border bg-muted/40 px-3 py-3 whitespace-pre-wrap">
                {{ lead.message }}
              </div>
            </div>
            <div v-if="lead.custom_fields && Object.keys(lead.custom_fields).length" class="space-y-3">
              <h3 class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Custom Fields</h3>
              <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                <div v-for="(val,key) in lead.custom_fields" :key="key" class="group rounded-md border bg-card/50 hover:bg-card transition-colors p-2">
                  <div class="text-[10px] font-medium tracking-wide uppercase text-muted-foreground mb-1">{{ key }}</div>
                  <div class="text-xs font-medium truncate" :title="val">{{ val }}</div>
                </div>
              </div>
            </div>
            <div class="space-y-3">
              <h3 class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Lifecycle Timeline</h3>
              <ol class="relative ml-3 border-l pl-4 space-y-4 border-border/60">
                <li class="space-y-1">
                  <div class="flex items-center gap-2">
                    <span class="absolute -left-[7px] w-3 h-3 rounded-full bg-primary ring-2 ring-background"></span>
                    <span class="text-xs font-medium">Created</span>
                    <span class="text-[10px] text-muted-foreground">{{ formatDate(lead.created_at) }}</span>
                  </div>
                </li>
                <li v-if="lead.contacted_at" class="space-y-1">
                  <div class="flex items-center gap-2">
                    <span class="absolute -left-[7px] w-3 h-3 rounded-full bg-blue-500 ring-2 ring-background"></span>
                    <span class="text-xs font-medium">Contacted</span>
                    <span class="text-[10px] text-muted-foreground">{{ formatDate(lead.contacted_at) }}</span>
                  </div>
                </li>
                <li v-if="lead.qualified_at" class="space-y-1">
                  <div class="flex items-center gap-2">
                    <span class="absolute -left-[7px] w-3 h-3 rounded-full bg-green-500 ring-2 ring-background"></span>
                    <span class="text-xs font-medium">Qualified</span>
                    <span class="text-[10px] text-muted-foreground">{{ formatDate(lead.qualified_at) }}</span>
                  </div>
                </li>
                <li v-if="lead.converted_at" class="space-y-1">
                  <div class="flex items-center gap-2">
                    <span class="absolute -left-[7px] w-3 h-3 rounded-full bg-purple-500 ring-2 ring-background"></span>
                    <span class="text-xs font-medium">Converted</span>
                    <span class="text-[10px] text-muted-foreground">{{ formatDate(lead.converted_at) }}</span>
                  </div>
                </li>
              </ol>
            </div>
            <div class="space-y-3">
              <h3 class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Technical</h3>
              <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-[11px]">
                <div class="flex flex-col gap-0.5">
                  <span class="font-medium text-muted-foreground">IP Address</span>
                  <span class="font-mono text-xs">{{ lead.ip_address || '—' }}</span>
                </div>
                <div class="flex flex-col gap-0.5">
                  <span class="font-medium text-muted-foreground">Referrer</span>
                  <span class="font-mono text-xs truncate" :title="lead.referrer">{{ lead.referrer || '—' }}</span>
                </div>
                <div class="flex flex-col gap-0.5">
                  <span class="font-medium text-muted-foreground">Browser</span>
                  <span class="font-mono text-xs">{{ parsedLeadUA?.browser || '—' }}</span>
                </div>
                <div class="flex flex-col gap-0.5">
                  <span class="font-medium text-muted-foreground">OS</span>
                  <span class="font-mono text-xs">{{ parsedLeadUA?.os || '—' }}</span>
                </div>
                <div class="flex flex-col gap-0.5">
                  <span class="font-medium text-muted-foreground">Device</span>
                  <span class="font-mono text-xs">{{ parsedLeadUA?.device || '—' }}</span>
                </div>
                <div class="flex flex-col gap-0.5 sm:col-span-2">
                  <span class="font-medium text-muted-foreground">User Agent (Raw)</span>
                  <span class="font-mono text-[10px] break-all leading-relaxed max-h-20 overflow-y-auto pr-1">{{ parsedLeadUA?.raw || '—' }}</span>
                </div>
              </div>
            </div>
          </div>
          <div v-else class="flex-1 flex items-center justify-center p-10 text-sm text-muted-foreground">No lead data.</div>
          <div class="px-6 py-4 border-t bg-background/80 backdrop-blur flex items-center justify-end gap-2">
            <SheetClose as-child>
              <Button variant="outline" size="sm">Close</Button>
            </SheetClose>
          </div>
        </SheetContent>
      </Sheet>
    </AgentLayout>
  </template>
