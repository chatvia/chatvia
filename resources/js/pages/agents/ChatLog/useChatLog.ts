/**
 * Shared state for the chat log page and its panels (sidebar, messages,
 * details, pulse, live chat, filters). The state is a module-level singleton,
 * so every component calling `useChatLog()` sees the same conversation,
 * filters and handoff session; it resets when the page switches agent.
 */
import {
    chatLogUrl,
    fetchChatLogConversations,
    fetchChatLogCountries,
    fetchChatLogMessages,
    fetchChatLogTopics,
    fetchConversationPulse,
    type ChatLogConversation,
    type ChatLogConversationFilters,
    type ChatLogCountry,
    type ChatLogHandoffStatus,
    type ChatLogLead,
    type ChatLogMessage,
    type ChatLogPulse,
    type ChatLogTopic,
    type LaravelPage,
} from '@/api/chatLog';
import { errorMessage } from '@/api/http';
import { useDateFormat } from '@/composables/useDateFormat';
import { useHandoff, type HandoffMessageEvent, type HandoffState } from '@/composables/useHandoff';
import { useTimeAgo } from '@/composables/useTimeAgo';
import { i18n } from '@/i18n';
import { router, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import * as format from './chatLogFormat';

export type ChatLogAgent = Domain.Agent.Data.Resources.Account.AgentResourceData;
export type ChatLogTab = 'chat' | 'details' | 'pulse' | 'handoff';

/** A filter left at this value is not sent to the server. */
const ALL = 'all';

// ---- Singleton state, shared by every consumer -----------------------------
const agent = ref<ChatLogAgent | null>(null);
const currentConversationId = ref<string | null>(null);
// A full page from the server, or just `data` when live-chat messages arrive before any page loaded.
const messagesData = ref<(Pick<LaravelPage<ChatLogMessage>, 'data'> & Partial<LaravelPage<ChatLogMessage>>) | null>(null);
// The loaded conversations: the first page, plus every page "Load more" appended.
const conversations = ref<ChatLogConversation[] | null>(null);
const nextConversationsCursor = ref<string | null>(null);
const conversationTitle = ref<string | null>(null);
const leadConversationIds = ref<number[]>([]);
const lead = ref<ChatLogLead | null>(null);
const currentConversationData = ref<ChatLogConversation | null>(null);
const activeTab = ref<ChatLogTab>('chat');

const isLoadingConversations = ref(false);
const isLoadingMoreConversations = ref(false);
// Bumped on every first-page load, so a "Load more" answer for an older list
// (filters changed while it was in flight) is dropped instead of appended.
let conversationsListVersion = 0;
const isLoadingMessages = ref(false);

let handoffInstance: ReturnType<typeof useHandoff> | null = null;

// Several components call useChatLog(), but one agent watcher is enough.
let agentWatcherRegistered = false;

const topics = ref<ChatLogTopic[]>([]);
const countries = ref<ChatLogCountry[]>([]);

const selectedTopic = ref<string>(ALL);
const selectedSentiment = ref<string>(ALL);
const selectedTone = ref<string>(ALL);
const selectedCountry = ref<string>(ALL);
const selectedConfidenceScore = ref<string>(ALL);
const selectedFeedback = ref<string>(ALL);
const selectedSource = ref<string>(ALL);
const dateRange = ref<{ start: string; end: string } | undefined>(undefined);
const showFiltersDialog = ref(false);
const onlineOnly = ref(false);

const search = ref('');

const pulseData = ref<ChatLogPulse | null>(null);
const isLoadingPulse = ref(false);
const pulseError = ref<string | null>(null);

/** The select-style filters, in the order the server reads them. */
const selectFilters = {
    topic: selectedTopic,
    sentiment: selectedSentiment,
    tone: selectedTone,
    country: selectedCountry,
    confidence_score: selectedConfidenceScore,
    feedback: selectedFeedback,
    source: selectedSource,
} as const;

function resetFilters() {
    Object.values(selectFilters).forEach((filter) => (filter.value = ALL));
    dateRange.value = undefined;
}

/** Flushes all per-agent state (filters are agent-scoped too). */
function resetForAgent(newAgent: ChatLogAgent | null, conversationId: string | null) {
    agent.value = newAgent;
    currentConversationId.value = conversationId;

    messagesData.value = null;
    conversations.value = null;
    nextConversationsCursor.value = null;
    // A load still in flight belongs to the previous agent and is dropped.
    conversationsListVersion++;
    isLoadingConversations.value = false;
    conversationTitle.value = null;
    leadConversationIds.value = [];
    lead.value = null;
    currentConversationData.value = null;
    activeTab.value = 'chat';

    topics.value = [];
    countries.value = [];
    resetFilters();
    onlineOnly.value = false;
    search.value = '';

    pulseData.value = null;
    pulseError.value = null;
}

function buildFilters(cursor: string | null = null): ChatLogConversationFilters {
    const filters: ChatLogConversationFilters = {};
    if (cursor) filters.cursor = cursor;
    for (const [key, selected] of Object.entries(selectFilters)) {
        if (selected.value !== ALL) filters[key as keyof typeof selectFilters] = selected.value;
    }
    if (onlineOnly.value) filters.online_only = true;
    if (dateRange.value?.start && dateRange.value?.end) {
        filters.start_date = dateRange.value.start;
        filters.end_date = dateRange.value.end;
    }

    return filters;
}

function toHandoffState(status: ChatLogHandoffStatus): HandoffState {
    return {
        status: (status.status || 'ai') as HandoffState['status'],
        adminId: status.admin_id || null,
        adminName: status.admin_name || null,
        handoffAt: status.handoff_at || null,
    };
}

export function useChatLog() {
    const page = usePage();
    const { formatTimeAgo } = useTimeAgo();
    // Calendar dates follow the user's chosen format (Settings → Date & time).
    const { formatDateTime } = useDateFormat();

    const pageAgent = () => (page.props.agent as ChatLogAgent | undefined) ?? null;
    const pageConversationId = () => (page.props.current_conversation_id as string | null | undefined) ?? null;

    const incomingAgent = pageAgent();
    if (!agent.value || agent.value.external_id !== incomingAgent?.external_id) {
        resetForAgent(incomingAgent, pageConversationId());
    }

    // Inertia reuses this page when navigating between agents and only swaps
    // page.props, so watch the agent instead of relying on setup re-running.
    if (!agentWatcherRegistered) {
        agentWatcherRegistered = true;
        watch(
            () => pageAgent()?.external_id,
            (newExternalId) => {
                if (!newExternalId || newExternalId === agent.value?.external_id) return;

                resetForAgent(pageAgent(), pageConversationId());
                // Elsewhere, the next mount of the chat log fetches fresh data itself.
                if (typeof page.component === 'string' && page.component.startsWith('agents/ChatLog')) {
                    void fetchInitialData();
                }
            },
        );
    }

    if (!handoffInstance) {
        handoffInstance = useHandoff(
            () => agent.value?.external_id ?? null,
            () => currentConversationId.value,
        );
    }
    const handoff = handoffInstance;

    const agentId = (): string => agent.value?.external_id ?? '';

    const hasMoreConversations = computed(() => nextConversationsCursor.value !== null);
    const hasLeadForCurrentConversation = computed(() => !!lead.value);
    const totalMessages = computed(() => messagesData.value?.data?.length || 0);

    // Client-side search over the loaded page of messages.
    const messages = computed<ChatLogMessage[]>(() => {
        const list = messagesData.value?.data || [];
        if (!search.value.trim()) return list;

        const query = search.value.toLowerCase();

        return list.filter((message) => [message.content, message.role, message.llm_name].some((field) => field?.toLowerCase().includes(query)));
    });

    const activeFilterCount = computed(
        () => Object.values(selectFilters).filter((filter) => filter.value !== ALL).length + (dateRange.value !== undefined ? 1 : 0),
    );
    const hasActiveFilters = computed(() => activeFilterCount.value > 0);

    function syncCurrentConversationData() {
        const found = conversations.value?.find((conversation) => conversation.conversation_id === currentConversationId.value);
        if (found) currentConversationData.value = { ...found };
    }

    // Mirrors the selection into the address bar (the server reads
    // ?conversation_id= back on reload). A client-side visit: no request is
    // made, and Inertia's page object and history entry stay consistent.
    function syncUrlToSelection() {
        router.replace({
            url: chatLogUrl(agentId(), currentConversationId.value),
            props: (props) => ({ ...props, current_conversation_id: currentConversationId.value }),
            preserveScroll: true,
            preserveState: true,
        });
    }

    /** Loads the first page with the current filters, replacing the list. */
    async function fetchConversations(refreshCurrentConversation = false) {
        const version = ++conversationsListVersion;
        isLoadingConversations.value = true;
        try {
            const result = await fetchChatLogConversations(agentId(), buildFilters());
            if (version !== conversationsListVersion) return;

            conversations.value = result.conversations;
            nextConversationsCursor.value = result.next_cursor;
            leadConversationIds.value = result.lead_conversation_ids;

            if (currentConversationId.value) syncCurrentConversationData();

            if (refreshCurrentConversation && currentConversationId.value) {
                await fetchMessages(currentConversationId.value);
                if (activeTab.value === 'pulse') await fetchPulseData();
            }

            syncUrlToSelection();
        } catch {
            // The list keeps what it had; the refresh button retries.
        } finally {
            if (version === conversationsListVersion) isLoadingConversations.value = false;
        }
    }

    /**
     * Appends the next page ("Load more"). A conversation already in the list
     * is skipped: a row whose activity moved between two loads can come back.
     */
    async function loadMoreConversations() {
        const cursor = nextConversationsCursor.value;
        if (!cursor || isLoadingMoreConversations.value) return;

        const version = conversationsListVersion;
        isLoadingMoreConversations.value = true;
        try {
            const result = await fetchChatLogConversations(agentId(), buildFilters(cursor));
            if (version !== conversationsListVersion) return;

            const loaded = new Set((conversations.value ?? []).map((conversation) => conversation.conversation_id));
            conversations.value = [
                ...(conversations.value ?? []),
                ...result.conversations.filter((conversation) => !loaded.has(conversation.conversation_id)),
            ];
            nextConversationsCursor.value = result.next_cursor;
            leadConversationIds.value = [...new Set([...leadConversationIds.value, ...result.lead_conversation_ids])];
        } catch {
            // The cursor is kept, so "Load more" can be tried again.
        } finally {
            isLoadingMoreConversations.value = false;
        }
    }

    async function fetchMessages(conversationId: string, pageNumber = 1) {
        if (!conversationId) return;

        isLoadingMessages.value = true;
        try {
            const result = await fetchChatLogMessages(agentId(), conversationId, pageNumber);
            messagesData.value = result.messages;
            conversationTitle.value = result.conversation_title;
            lead.value = result.lead;

            if (result.handoff) {
                handoff.handoffState.value = toHandoffState(result.handoff);
                // Opening a handed-off conversation marks the visitor's messages as read.
                if (result.handoff.status === 'human') void handoff.markMessagesAsRead();
            }

            // Always join, to show whether the visitor is online (the previous channel is left first).
            handoff.joinChannel(conversationId);
        } catch {
            // The panel keeps its previous content; the refresh button retries.
        } finally {
            isLoadingMessages.value = false;
        }
    }

    async function fetchPulseData() {
        if (!currentConversationId.value) return;

        isLoadingPulse.value = true;
        pulseError.value = null;
        try {
            pulseData.value = await fetchConversationPulse(agentId(), currentConversationId.value);
        } catch (error) {
            pulseError.value = errorMessage(error, i18n.global.t('agents.chatlog.errors.pulse_load_failed'));
        } finally {
            isLoadingPulse.value = false;
        }
    }

    async function fetchInitialData() {
        try {
            const [topicList, countryList] = await Promise.all([fetchChatLogTopics(agentId()), fetchChatLogCountries(agentId())]);
            topics.value = topicList;
            countries.value = countryList;

            await fetchConversations();

            if (currentConversationId.value) {
                await fetchMessages(currentConversationId.value);
                const found = conversations.value?.find((conversation) => conversation.conversation_id === currentConversationId.value);
                currentConversationData.value = found ? { ...found } : null;
            }
        } catch {
            // Filters stay empty; the conversation list can still be refreshed.
        }
    }

    /**
     * Adds a message pushed over the live-chat channel. The broadcast carries
     * only a few fields, so the rest of ChatLogMessageData is padded.
     */
    function appendMessage(event: HandoffMessageEvent) {
        if (!messagesData.value?.data) {
            messagesData.value = { data: [] };
        }
        if (messagesData.value.data.some((message) => message.id === event.id)) return;

        messagesData.value.data.push({
            id: event.id,
            chat_conversation_id: event.chat_conversation_id ?? '',
            content: event.content,
            role: event.role,
            sentiment: null,
            tone: null,
            feedback: null,
            is_response_from_qa: false,
            response_time_ms: null,
            prompt_tokens: null,
            completion_tokens: null,
            llm_name: null,
            created_at: event.timestamp || new Date().toISOString(),
            agent_question_answer_id: null,
            artifacts: event.artifacts ?? null,
            source: null,
            read_at: null,
            confidence_score: null,
            voice: false,
            trace_id: null,
        });
    }

    function selectConversation(conversationId: string, conversationData: ChatLogConversation) {
        currentConversationId.value = conversationId;
        currentConversationData.value = { ...conversationData };
        pulseData.value = null;
        pulseError.value = null;
        syncUrlToSelection();
    }

    function clearAllFilters() {
        resetFilters();
        void fetchConversations();
    }

    function applyFilters() {
        showFiltersDialog.value = false;
        void fetchConversations();
    }

    function getTopicName(topicId: number): string {
        return topics.value.find((topic) => topic.id === topicId)?.topic || '';
    }

    function getCountryName(id: string): string {
        return countries.value.find((country) => String(country.id) === id)?.name || id;
    }

    function formatDateRangeDisplay(): string {
        return dateRange.value ? `${dateRange.value.start} ~ ${dateRange.value.end}` : '';
    }

    function formatFullDateTime(dateTime: string | null | undefined): string {
        if (!dateTime) return '';
        try {
            return formatDateTime(dateTime);
        } catch {
            return dateTime;
        }
    }

    return {
        // State
        agent,
        currentConversationId,
        messagesData,
        conversations,
        conversationTitle,
        leadConversationIds,
        lead,
        currentConversationData,
        activeTab,
        isLoadingConversations,
        isLoadingMoreConversations,
        isLoadingMessages,
        topics,
        countries,
        selectedTopic,
        selectedSentiment,
        selectedTone,
        selectedCountry,
        selectedConfidenceScore,
        selectedFeedback,
        selectedSource,
        dateRange,
        showFiltersDialog,
        onlineOnly,
        search,
        pulseData,
        isLoadingPulse,
        pulseError,

        handoff,

        // Computed
        hasMoreConversations,
        hasLeadForCurrentConversation,
        totalMessages,
        messages,
        hasActiveFilters,
        activeFilterCount,

        // Actions
        fetchConversations,
        loadMoreConversations,
        fetchMessages,
        fetchPulseData,
        fetchInitialData,
        selectConversation,
        clearAllFilters,
        applyFilters,
        appendMessage,

        // Display helpers
        formatTimeAgo,
        formatFullDateTime,
        formatDateRangeDisplay,
        getTopicName,
        getCountryName,
        getFlagPath: format.getFlagPath,
        getSentimentDisplayName: format.getSentimentDisplayName,
        getSentimentBadgeColor: format.getSentimentBadgeColor,
        getToneDisplayName: format.getToneDisplayName,
        getToneEmoji: format.getToneEmoji,
        getToneBadgeColor: format.getToneBadgeColor,
        getConfidenceColor: format.getConfidenceColor,
        getConfidenceScoreDisplayName: format.getConfidenceScoreDisplayName,
        getFeedbackDisplayName: format.getFeedbackDisplayName,
        getSourceDisplayName: format.getSourceDisplayName,
        highlightText: format.highlightText,
        getPulsePointColor: format.getPulsePointColor,
    };
}
