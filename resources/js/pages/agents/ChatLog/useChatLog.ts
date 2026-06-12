import { useDateFormat } from '@/composables/useDateFormat';
import { useHandoff } from '@/composables/useHandoff';
import { useTimeAgo } from '@/composables/useTimeAgo';
import { i18n } from '@/i18n';
import { usePage } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, ref, watch } from 'vue';

type Message = Domain.Agent.Data.Resources.ChatLog.Messages.ChatLogMessageData;
type Topic = Domain.Agent.Data.Resources.ChatLog.Topics.ChatLogTopicData;

// Singleton state - shared across components
const agent = ref<any>(null);
const currentConversationId = ref<string | null>(null);
const messagesData = ref<any>(null);
const conversations = ref<any>(null);
const conversationTitle = ref<string | null>(null);
const leadConversationIds = ref<string[]>([]);
const lead = ref<any | null>(null);
const currentConversationData = ref<any | null>(null);
const activeTab = ref<'chat' | 'details' | 'pulse' | 'handoff'>('chat');

// Loading states
const isLoadingConversations = ref(false);
const isLoadingMessages = ref(false);

// Singleton handoff instance
let handoffInstance: ReturnType<typeof useHandoff> | null = null;

// Track whether the cross-agent navigation watcher has been registered yet.
// useChatLog() is consumed by multiple components, but we only need one watcher.
let agentWatcherRegistered = false;

// Filter data
const topics = ref<Topic[]>([]);
const countries = ref<{ id: number; code: string; name: string }[]>([]);

// Filter selections
const selectedTopic = ref<string>('all');
const selectedSentiment = ref<string>('all');
const selectedTone = ref<string>('all');
const selectedCountry = ref<string>('all');
const selectedConfidenceScore = ref<string>('all');
const selectedFeedback = ref<string>('all');
const selectedSource = ref<string>('all');
const dateRange = ref<{ start: string; end: string } | undefined>(undefined);
const showFiltersDialog = ref(false);
const onlineOnly = ref(false);

// Search
const search = ref('');

// Pulse data
const pulseData = ref<any>(null);
const isLoadingPulse = ref(false);
const pulseError = ref<string | null>(null);

export function useChatLog() {
    const page = usePage();
    const { formatTimeAgo } = useTimeAgo();
    // Calendar dates follow the user's chosen format (Settings → Date & time).
    const { formatDateTime } = useDateFormat();

    // Flush all per-agent singleton state. Used both on initial mount when the
    // agent differs from the cached one, AND via a watcher to handle Inertia
    // navigations that update page.props without re-running setup (same-page
    // component reuse).
    const resetForAgent = (newAgent: any, conversationId: string | null) => {
        agent.value = newAgent;
        currentConversationId.value = conversationId;

        // Reset per-agent data
        messagesData.value = null;
        conversations.value = null;
        conversationTitle.value = null;
        leadConversationIds.value = [];
        lead.value = null;
        currentConversationData.value = null;
        activeTab.value = 'chat';

        // Reset filters (they're agent-scoped)
        topics.value = [];
        countries.value = [];
        selectedTopic.value = 'all';
        selectedSentiment.value = 'all';
        selectedTone.value = 'all';
        selectedCountry.value = 'all';
        selectedConfidenceScore.value = 'all';
        selectedFeedback.value = 'all';
        selectedSource.value = 'all';
        dateRange.value = undefined;
        onlineOnly.value = false;
        search.value = '';

        // Reset pulse
        pulseData.value = null;
        pulseError.value = null;
    };

    // Initial sync on mount
    const incomingAgent = page.props.agent as any;
    if (!agent.value || agent.value.external_id !== incomingAgent?.external_id) {
        resetForAgent(incomingAgent, page.props.current_conversation_id as string | null);
    }

    // React to Inertia prop changes (handles same-page navigation between agents
    // where Inertia reuses the component and only updates page.props). Register
    // once across all consumers of this composable.
    if (!agentWatcherRegistered) {
        agentWatcherRegistered = true;
        watch(
            () => (page.props.agent as any)?.external_id,
            (newExternalId) => {
                const nextAgent = page.props.agent as any;
                if (!newExternalId || newExternalId === agent.value?.external_id) {
                    return;
                }
                resetForAgent(nextAgent, page.props.current_conversation_id as string | null);
                // Only refetch when we're actually on the chat log page; otherwise
                // the next mount of the page will fetch fresh data itself.
                if (typeof page.component === 'string' && page.component.startsWith('agents/ChatLog')) {
                    fetchInitialData();
                }
            },
        );
    }

    // Singleton handoff composable - create once
    if (!handoffInstance) {
        handoffInstance = useHandoff(
            () => agent.value?.external_id ?? null,
            () => currentConversationId.value,
        );
    }
    const handoff = handoffInstance;

    // Computed values
    const totalConversations = computed(() => conversations.value?.total || 0);
    const currentConversationsPage = computed(() => conversations.value?.current_page || 1);
    const hasLeadForCurrentConversation = computed(() => !!lead.value);
    const totalMessages = computed(() => messagesData.value?.data?.length || 0);

    const messages = computed(() => {
        const list: Message[] = messagesData.value?.data || [];
        if (!search.value.trim()) return list;
        const q = search.value.toLowerCase();
        return list.filter(
            (m) =>
                (m.content && m.content.toLowerCase().includes(q)) ||
                (m.role && m.role.toLowerCase().includes(q)) ||
                (m.llm_name && m.llm_name.toLowerCase().includes(q)),
        );
    });

    const hasActiveFilters = computed(() => {
        return (
            selectedTopic.value !== 'all' ||
            selectedSentiment.value !== 'all' ||
            selectedTone.value !== 'all' ||
            selectedCountry.value !== 'all' ||
            selectedConfidenceScore.value !== 'all' ||
            selectedFeedback.value !== 'all' ||
            selectedSource.value !== 'all' ||
            dateRange.value !== undefined
        );
    });

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

    // Fetch functions
    async function fetchConversations(page = 1, refreshCurrentConversation = false) {
        isLoadingConversations.value = true;
        try {
            const params: any = { page };
            if (selectedTopic.value !== 'all') params.topic = selectedTopic.value;
            if (selectedSentiment.value !== 'all') params.sentiment = selectedSentiment.value;
            if (selectedTone.value !== 'all') params.tone = selectedTone.value;
            if (selectedCountry.value !== 'all') params.country = selectedCountry.value;
            if (selectedConfidenceScore.value !== 'all') params.confidence_score = selectedConfidenceScore.value;
            if (selectedFeedback.value !== 'all') params.feedback = selectedFeedback.value;
            if (selectedSource.value !== 'all') params.source = selectedSource.value;
            if (onlineOnly.value) params.online_only = true;
            if (dateRange.value?.start && dateRange.value?.end) {
                params.start_date = dateRange.value.start;
                params.end_date = dateRange.value.end;
            }

            const response = await axios.get(route('agents.logs.conversations', { agent: agent.value.external_id }), { params });
            conversations.value = response.data.conversations;
            leadConversationIds.value = response.data.lead_conversation_ids;

            // Update currentConversationData if a conversation is currently selected
            if (currentConversationId.value) {
                const found = conversations.value?.data?.find((c: any) => c.conversation_id === currentConversationId.value);
                if (found) {
                    currentConversationData.value = { ...found };
                }
            }

            // Refresh the current conversation's messages and details if requested
            if (refreshCurrentConversation && currentConversationId.value) {
                await fetchMessages(currentConversationId.value);

                // Also refresh pulse data if on pulse tab
                if (activeTab.value === 'pulse') {
                    await fetchPulseData();
                }
            }

            // Update URL
            if (page !== 1 || currentConversationId.value) {
                const urlParams: any = { agent: agent.value.external_id };
                if (currentConversationId.value) urlParams.conversation_id = currentConversationId.value;
                if (page > 1) urlParams.conversations = page;
                window.history.pushState({}, '', route('agents.logs', urlParams));
            }
        } catch (error) {
            console.error('Failed to fetch conversations:', error);
        } finally {
            isLoadingConversations.value = false;
        }
    }

    async function fetchMessages(conversationId: string, page = 1) {
        if (!conversationId) return;
        isLoadingMessages.value = true;
        try {
            const response = await axios.get(
                route('agents.logs.messages', {
                    agent: agent.value.external_id,
                    conversation: conversationId,
                }),
                { params: { page } },
            );
            messagesData.value = response.data.messages;
            conversationTitle.value = response.data.conversation_title;
            lead.value = response.data.lead;

            // Update handoff state from response
            if (response.data.handoff) {
                const h = response.data.handoff;
                handoff.handoffState.value = {
                    status: h.status || 'ai',
                    adminId: h.admin_id || null,
                    adminName: h.admin_name || null,
                    handoffAt: h.handoff_at || null,
                };

                // Mark user messages as read when admin opens a handed-off conversation
                if (h.status === 'human') {
                    handoff.markMessagesAsRead();
                }
            }

            // Always join the channel to see user online status
            // (leave previous channel first via the composable's watch)
            handoff.joinChannel(conversationId);
        } catch (error) {
            console.error('Failed to fetch messages:', error);
        } finally {
            isLoadingMessages.value = false;
        }
    }

    async function fetchPulseData() {
        if (!currentConversationId.value) return;
        isLoadingPulse.value = true;
        pulseError.value = null;
        try {
            const response = await axios.get(
                route('agents.logs.pulse', {
                    agent: agent.value.external_id,
                    conversation: currentConversationId.value,
                }),
            );
            pulseData.value = response.data;
        } catch (err: any) {
            pulseError.value = err.response?.data?.message || i18n.global.t('agents.chatlog.errors.pulse_load_failed');
        } finally {
            isLoadingPulse.value = false;
        }
    }

    async function fetchInitialData() {
        try {
            const [topicsResponse, countriesResponse] = await Promise.all([
                axios.get(route('agents.logs.topics', { agent: agent.value.external_id })),
                axios.get(route('agents.logs.countries', { agent: agent.value.external_id })),
            ]);

            topics.value = topicsResponse.data.topics || [];
            countries.value = countriesResponse.data.countries || [];

            await fetchConversations();

            if (currentConversationId.value) {
                await fetchMessages(currentConversationId.value);
                const found = conversations.value?.data?.find((c: any) => c.conversation_id === currentConversationId.value);
                currentConversationData.value = found ? { ...found } : null;
            }
        } catch (error) {
            console.error('Failed to fetch initial data:', error);
        }
    }

    // Append a message directly to the messages list (for real-time updates)
    function appendMessage(messageData: any) {
        if (!messagesData.value?.data) {
            messagesData.value = { data: [] };
        }

        // Check if message already exists
        const exists = messagesData.value.data.some((m: any) => m.id === messageData.id);
        if (exists) return;

        // Create message object matching the expected format. The websocket
        // payload only carries a handful of fields; pad the rest to keep the
        // typed `ChatLogMessageData` shape.
        const newMessage: Message = {
            id: messageData.id,
            chat_conversation_id: messageData.chat_conversation_id ?? '',
            content: messageData.content,
            role: messageData.role,
            sentiment: null,
            tone: null,
            feedback: null,
            is_response_from_qa: null,
            response_time_ms: null,
            prompt_tokens: null,
            completion_tokens: null,
            llm_name: null,
            llm_icon: null,
            created_at: messageData.timestamp || new Date().toISOString(),
            agent_question_answer_id: null,
            artifacts: messageData.artifacts ?? null,
            source: null,
            read_at: null,
        };

        messagesData.value.data.push(newMessage);
    }

    function selectConversation(conversationId: string, conversationData: any) {
        currentConversationId.value = conversationId;
        currentConversationData.value = { ...conversationData };
        pulseData.value = null;
        pulseError.value = null;
    }

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

    function applyFilters() {
        showFiltersDialog.value = false;
        fetchConversations(1);
    }

    // Utility functions
    function getFlagPath(countryCode: string | null | undefined): string | null {
        if (!countryCode) return null;
        return `/images/flags/${countryCode.toLowerCase()}.svg`;
    }

    function getTopicName(topicId: number): string {
        const topic = topics.value.find((t) => t.id === topicId);
        return topic?.topic || '';
    }

    function getCountryName(id: string): string {
        const country = countries.value.find((c) => String(c.id) === id);
        return country?.name || id;
    }

    function getSentimentDisplayName(sentiment: string): string {
        const names: Record<string, string> = {
            positive: i18n.global.t('agents.chatlog.sentiment.positive'),
            neutral: i18n.global.t('agents.chatlog.sentiment.neutral'),
            negative: i18n.global.t('agents.chatlog.sentiment.negative'),
        };
        return names[sentiment] || sentiment;
    }

    function getSentimentBadgeColor(sentiment: string): string {
        const colors: Record<string, string> = {
            positive: 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300',
            neutral: 'bg-gray-100 text-gray-800 dark:bg-gray-900/30 dark:text-gray-300',
            negative: 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300',
        };
        return colors[sentiment] || 'bg-gray-100 text-gray-800';
    }

    function getToneDisplayName(tone: string): string {
        const names: Record<string, string> = {
            neutral: i18n.global.t('agents.chatlog.tone.neutral'),
            frustrated: i18n.global.t('agents.chatlog.tone.frustrated'),
            urgent: i18n.global.t('agents.chatlog.tone.urgent'),
            confused: i18n.global.t('agents.chatlog.tone.confused'),
            satisfied: i18n.global.t('agents.chatlog.tone.satisfied'),
        };
        return names[tone] || tone;
    }

    function getToneEmoji(tone: string): string {
        const emojis: Record<string, string> = {
            neutral: '😐',
            frustrated: '😤',
            urgent: '⚡',
            confused: '😕',
            satisfied: '😊',
        };
        return emojis[tone] || '😐';
    }

    function getToneBadgeColor(tone: string): string {
        const colors: Record<string, string> = {
            neutral: 'bg-gray-100 text-gray-800 dark:bg-gray-900/30 dark:text-gray-300',
            frustrated: 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300',
            urgent: 'bg-orange-100 text-orange-800 dark:bg-orange-900/30 dark:text-orange-300',
            confused: 'bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-300',
            satisfied: 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300',
        };
        return colors[tone] || 'bg-gray-100 text-gray-800';
    }

    function getConfidenceColor(score: number): string {
        if (score >= 0.8) return 'text-green-600 dark:text-green-400';
        if (score >= 0.65) return 'text-yellow-600 dark:text-yellow-400';
        return 'text-red-600 dark:text-red-400';
    }

    function getConfidenceScoreDisplayName(score: string): string {
        const names: Record<string, string> = {
            high: i18n.global.t('agents.chatlog.confidence.high'),
            medium: i18n.global.t('agents.chatlog.confidence.medium'),
            low: i18n.global.t('agents.chatlog.confidence.low'),
        };
        return names[score] || score;
    }

    function getFeedbackDisplayName(feedback: string): string {
        return feedback === 'positive'
            ? i18n.global.t('agents.chatlog.feedback.thumbs_up')
            : feedback === 'negative'
              ? i18n.global.t('agents.chatlog.feedback.thumbs_down')
              : feedback;
    }

    function getSourceDisplayName(source: string): string {
        const names: Record<string, string> = {
            hosted_chat: i18n.global.t('agents.chatlog.source.hosted_chat'),
            widget: i18n.global.t('agents.chatlog.source.widget'),
            bar: i18n.global.t('agents.chatlog.source.bar'),
            telegram: i18n.global.t('agents.chatlog.source.telegram'),
            slack: i18n.global.t('agents.chatlog.source.slack'),
            messenger: i18n.global.t('agents.chatlog.source.messenger'),
            whatsapp: i18n.global.t('agents.chatlog.source.whatsapp'),
            msteams: i18n.global.t('agents.chatlog.source.msteams'),
            instagram: i18n.global.t('agents.chatlog.source.instagram'),
            playground: i18n.global.t('agents.chatlog.source.playground'),
            api: i18n.global.t('agents.chatlog.source.api'),
        };
        return names[source] || source;
    }

    function formatDateRangeDisplay(): string {
        if (!dateRange.value) return '';
        return `${dateRange.value.start} ~ ${dateRange.value.end}`;
    }

    function escapeHtml(value: string): string {
        return value.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
    }

    function highlightText(text: string, query: string): string {
        // `text` is visitor/assistant-authored content rendered via v-html, so
        // it MUST be HTML-escaped before we inject <mark> — otherwise a message
        // like `<img onerror=…>` executes in the operator's dashboard session.
        const safe = escapeHtml(text);
        if (!query.trim()) return safe;
        const regex = new RegExp(`(${escapeHtml(query).replace(/[.*+?^${}()|[\]\\]/g, '\\$&')})`, 'gi');
        return safe.replace(regex, '<mark class="bg-yellow-200 dark:bg-yellow-900/50 rounded px-0.5">$1</mark>');
    }

    function formatFullDateTime(dt: string | null | undefined): string {
        if (!dt) return '';
        try {
            return formatDateTime(dt);
        } catch {
            return dt || '';
        }
    }

    function getPulsePointColor(score: number): string {
        if (score >= 0.5) return 'rgb(34, 197, 94)';
        if (score >= -0.5) return 'rgb(234, 179, 8)';
        return 'rgb(239, 68, 68)';
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

        // Handoff
        handoff,

        // Computed
        totalConversations,
        currentConversationsPage,
        hasLeadForCurrentConversation,
        totalMessages,
        messages,
        hasActiveFilters,
        activeFilterCount,

        // Actions
        fetchConversations,
        fetchMessages,
        fetchPulseData,
        fetchInitialData,
        selectConversation,
        clearAllFilters,
        applyFilters,
        appendMessage,

        // Utilities
        formatTimeAgo,
        getFlagPath,
        getTopicName,
        getCountryName,
        getSentimentDisplayName,
        getSentimentBadgeColor,
        getToneDisplayName,
        getToneEmoji,
        getToneBadgeColor,
        getConfidenceColor,
        getConfidenceScoreDisplayName,
        getFeedbackDisplayName,
        getSourceDisplayName,
        formatDateRangeDisplay,
        highlightText,
        formatFullDateTime,
        getPulsePointColor,
    };
}
