<script setup>
import AssistantChatBubble from '@/components/chat/AssistantChatBubble.vue';
import ChatComposer from '@/components/chat/ChatComposer.vue';
import ChatErrorBubble from '@/components/chat/ChatErrorBubble.vue';
import ChatLayout from '@/components/chat/ChatLayout.vue';
import { normalizeLocale, resolveLocale, setI18nLocale } from '@/i18n';
import { resolveAppearanceText } from '@/lib/appearanceText';
import UserChatBubble from '@/components/chat/UserChatBubble.vue';
import { useChatStream } from '@/composables/useChatStream';
import { useColorTheme } from '@/composables/useColorTheme';
import { router, usePage } from '@inertiajs/vue3';
import { useScroll } from '@vueuse/core';
import axios from 'axios';
import { ChevronDown } from 'lucide-vue-next';
import { computed, inject, nextTick, onMounted, onUnmounted, provide, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { toast } from 'vue-sonner';

const { t, locale: i18nLocale } = useI18n();

const chatEventBus = inject('chatEventBus');

// Inline-form submit transport for this surface. InlineFormBlock (rendered deep
// inside assistant messages) injects this so it stays transport-agnostic — the
// widget surface provides its own. axios rejects on 4xx (409 already-submitted,
// 410 expired), which the form block handles.
provide('inlineFormSubmit', async (formInstanceId, fields, assistantMessageId) => {
    const response = await axios.post(route('chat.forms.submit', { formInstance: formInstanceId }), {
        fields,
        assistant_message_id: assistantMessageId,
    });
    return response.data;
});

// Per-chat upload ceiling — sourced from `agent.files.upload.max_files`
// via Inertia shared props (ChatHandleInertiaRequests). Falls back to 2
// (the config default) for the brief window before the prop hydrates.
const page = usePage();
const maxFilesPerChat = computed(() => page.props.maxFilesPerChat ?? 2);

const { state: streamState, error: streamError, start: startStream, stop: stopStream } = useChatStream();

const isStreaming = computed(() => streamState.value === 'streaming' || streamState.value === 'connecting');

/**
 * Tell whether a message is the one currently being streamed into.
 * After the server's `start` event, we have a real UUID; before it,
 * the pending assistant stub still has id `0`.
 */
function isMessageStreaming(message) {
    if (!isStreaming.value) return false;
    if (streamingAssistantId.value) {
        return message.id === streamingAssistantId.value;
    }
    return message.role === 'assistant' && (message.id === 0 || message.id === null);
}

const props = defineProps({
    agent_id: {
        type: Number,
        required: true,
    },
    conversation_id: {
        type: String,
        required: true,
    },
    appearance: {
        type: Object,
        default: () => ({
            color_theme: 'black',
            welcome_message: null,
            chat_window_title: null,
            avatar: null,
            composer_placeholder: null,
        }),
    },
    removeSidebar: {
        type: Boolean,
        default: false,
    },
    hideSidebarOnPageLoad: {
        type: Boolean,
        default: false,
    },
    enableThemeSwitcher: {
        type: Boolean,
        default: true,
    },
    defaultTheme: {
        type: String,
        default: 'auto',
        validator: (value) => ['auto', 'light', 'dark'].includes(value),
    },
    enableChatExpand: {
        type: Boolean,
        default: true,
    },
    newChatRoute: {
        type: String,
        default: 'chat.home',
    },
    chatviaBranding: {
        type: Boolean,
        default: true,
    },
    // Owner's pinned chat language ('auto' = detect from the visitor). Seeds the
    // initial UI + appearance language on mount.
    locale: {
        type: String,
        default: 'auto',
    },
    // Whether to show the visitor language switcher in the header.
    enableLanguageSwitcher: {
        type: Boolean,
        default: false,
    },
    // Language codes the owner enabled in Setup → Languages. Empty ⇒ all locales.
    enabledLanguages: {
        type: Array,
        default: () => [],
    },
});

// The three appearance strings localized to the active UI locale. The language
// switcher in the header changes the i18n locale, which re-resolves these
// instantly from the translation maps already in the payload.
const resolvedAppearance = computed(() => ({
    ...props.appearance,
    ...resolveAppearanceText(props.appearance, normalizeLocale(i18nLocale.value)),
}));

onMounted(() => {
    // Honor the owner's pinned language, else the visitor's stored/browser choice.
    void setI18nLocale(resolveLocale(props.locale));
});

const loadingMessages = ref(true);
const messages = ref([]);

const isSidebarOpen = ref(false);
const isAutoScrollEnabled = ref(true);
const scrollTimeout = ref(null);
const messagesContainer = ref(null);
const { arrivedState } = useScroll(messagesContainer);
const streamingAssistantId = ref(null);
const isChatExpanded = ref(false);
const userHasScrolledUp = ref(false);
const lastScrollTop = ref(0);
const scrollAnimationFrame = ref(null);
const messageSources = ref(new Map()); // Keyed by assistant message UUID (string)
// Structured artifacts (charts, etc.) emitted by tool calls, keyed by
// assistant message UUID. Each entry is the array of ToolArtifact blocks
// that arrived across all tool calls in that assistant turn. Mirrors
// `messageSources`; AssistantChatBubble consumes the matching entry.
const messageArtifacts = ref(new Map());
const scrollThrottleTimeout = ref(null); // For throttling scroll updates

// In-flight function/tool calls keyed by tool_call_id. Populated by the
// `tool` envelope event from useChatStream — see submitChat()'s onTool
// callback below. Synchronous + ordered with the LLM stream; the equivalent
// async pattern (knowledge-base ingestion) uses Reverb broadcasts instead.
const activeFunctionCalls = ref(new Map());

const hasActiveFunctionCalls = computed(() => activeFunctionCalls.value.size > 0);

// Sorted by start time (oldest first), so AssistantChatBubble can stack
// the icons in arrival order and show the most-recently-started one as
// the current label.
const activeFunctionCallsList = computed(() => Array.from(activeFunctionCalls.value.values()).sort((a, b) => a.started_at - b.started_at));

// Human-in-the-loop tool approval. `pendingApprovals` holds the ToolApprovalCall
// cards awaiting the user's decision, keyed by assistant message UUID. Set by the
// `awaiting_approval` tool event; cleared when the user resolves them (which
// re-opens the stream) or when a new turn supersedes them.
const pendingApprovals = ref(new Map());
// approval_ids currently being resolved — their cards show a spinner + disable.
const resolvingApprovalIds = ref([]);
// approval_id -> { [tool_call_id]: 'approve' | 'deny' }. A checkpoint may gate
// several calls; we accumulate decisions until all are made, then post together.
const approvalDecisions = new Map();

const hasPendingApprovals = computed(() => pendingApprovals.value.size > 0);

// Usage checking
const USAGE_CHECK_INTERVAL = 60000; // 1 minute
const canSendMessages = ref(true);
const usageCheckInterval = ref(null);

const { applyColorTheme } = useColorTheme();

const showScrollToBottom = computed(() => {
    if (!messagesContainer.value) return false;

    const { clientHeight, scrollHeight } = messagesContainer.value;
    return !arrivedState.bottom && scrollHeight > clientHeight;
});

// Watch for when user reaches bottom
watch(
    () => arrivedState.bottom,
    (isAtBottom) => {
        if (isAtBottom) {
            userHasScrolledUp.value = false;
            isAutoScrollEnabled.value = true;
        }
    },
);

// Helper function to check if a message is RTL
const isMessageRTL = (message) => {
    const rtlLanguages = ['ar', 'he', 'fa', 'ur', 'ps', 'sd', 'yi', 'dv'];
    return message.language && rtlLanguages.includes(message.language);
};

const getQueryParam = (param) => {
    const urlParams = new URLSearchParams(window.location.search);
    return urlParams.get(param);
};

// Function to load sources asynchronously
const loadSourcesAsync = async () => {
    try {
        const response = await axios.get(route('chat.sources', { conversationId: props.conversation_id }));
        const sourcesData = response.data;

        // Map sources keyed by assistant message UUID (stable across re-orderings/deletions).
        messages.value.forEach((message) => {
            if (message.role === 'assistant' && message.id && sourcesData[message.id]) {
                messageSources.value.set(message.id, sourcesData[message.id]);
            }
        });
    } catch (error) {
        console.error('Failed to load sources:', error);
    }
};

// Stream errors are rendered as an inline ChatErrorBubble where the assistant
// reply would have been (see submitChat's onError). The toast only fires when
// the assistant had already streamed some content — those cases can't be
// shown inline without clobbering the partial reply, so a toast is the
// least-disruptive fallback.
watch(streamError, (err) => {
    if (!err) return;
    const stub = messages.value[messages.value.length - 1];
    const inlineShown = stub && stub.role === 'error';
    if (!inlineShown) {
        toast.error(err.message);
    }
});

// Function to check usage status
const checkUsage = async () => {
    try {
        const response = await axios.get(route('chat.usage'));
        canSendMessages.value = response.data.can_send_messages;
    } catch (error) {
        console.error('Failed to check usage:', error);
        // On error, allow sending messages to prevent blocking the user
        canSendMessages.value = true;
    }
};

// Start periodic usage checking
const startUsageChecking = () => {
    // Check immediately
    checkUsage();

    // Then check at regular intervals
    usageCheckInterval.value = setInterval(checkUsage, USAGE_CHECK_INTERVAL);
};

// Stop periodic usage checking
const stopUsageChecking = () => {
    if (usageCheckInterval.value) {
        clearInterval(usageCheckInterval.value);
        usageCheckInterval.value = null;
    }
};

async function messageAdded(message, attachments = []) {
    const userIndex = messages.value.length;
    messages.value.push({
        role: 'user',
        content: message,
        id: 0,
        attachments,
    });

    const assistantIndex = messages.value.length;
    messages.value.push({
        role: 'assistant',
        content: '',
        id: 0,
    });

    if (window.innerWidth < 768 && isSidebarOpen.value) {
        isSidebarOpen.value = false;
    }

    userHasScrolledUp.value = false;
    isAutoScrollEnabled.value = true;

    await nextTick(() => {
        scrollToBottom();
        submitChat({
            message,
            userIndex,
            assistantIndex,
            attachments,
            isNewConversation: getQueryParam('new') === '1',
        });
    });
}

function scrollToBottom(smooth = false) {
    if (!messagesContainer.value) return;

    // Cancel any pending scroll animation
    if (scrollAnimationFrame.value) {
        cancelAnimationFrame(scrollAnimationFrame.value);
    }

    scrollAnimationFrame.value = requestAnimationFrame(() => {
        if (messagesContainer.value) {
            if (smooth) {
                // Use smooth scrolling for user interactions
                messagesContainer.value.scrollTo({
                    top: messagesContainer.value.scrollHeight,
                    behavior: 'smooth',
                });
            } else {
                // Use instant scroll to avoid scroll-linked effects
                messagesContainer.value.scrollTo({
                    top: messagesContainer.value.scrollHeight,
                    behavior: 'instant',
                });
            }
        }
        isAutoScrollEnabled.value = true;
        userHasScrolledUp.value = false;
        scrollAnimationFrame.value = null;
    });
}

function checkScreenSize() {
    const savedSidebarState = localStorage.getItem('sidebarOpen');

    if (savedSidebarState !== null) {
        isSidebarOpen.value = savedSidebarState === 'true';
    } else {
        isSidebarOpen.value = window.innerWidth >= 768;
        localStorage.setItem('sidebarOpen', isSidebarOpen.value);
    }
}

function handleChatScroll() {
    if (!messagesContainer.value) return;

    const currentScrollTop = messagesContainer.value.scrollTop;
    const { scrollHeight, clientHeight } = messagesContainer.value;
    const scrollBottom = scrollHeight - currentScrollTop - clientHeight;

    // Resume auto-scroll when the user reaches the bottom again. Direction-based
    // user-scrolled-up detection is unreliable here — programmatic scrollTo() and
    // user wheel/touch events both produce the same scroll event, so we detect
    // user intent at the input layer instead (see handleUserScrollIntent).
    if (scrollBottom < 20) {
        userHasScrolledUp.value = false;
        isAutoScrollEnabled.value = true;
    }

    lastScrollTop.value = currentScrollTop;
}

function handleUserScrollIntent(e) {
    if (!messagesContainer.value) return;

    if (e.type === 'keydown') {
        const scrollKeys = ['ArrowUp', 'ArrowDown', 'PageUp', 'PageDown', 'Home', 'End', ' '];
        if (!scrollKeys.includes(e.key)) return;
    }

    const { scrollTop, scrollHeight, clientHeight } = messagesContainer.value;
    if (scrollHeight - scrollTop - clientHeight < 20) return;

    userHasScrolledUp.value = true;
    isAutoScrollEnabled.value = false;
}

onUnmounted(() => {
    chatEventBus.offChatExpandToggle();
    if (scrollTimeout.value) clearTimeout(scrollTimeout.value);
    if (scrollAnimationFrame.value) cancelAnimationFrame(scrollAnimationFrame.value);
    if (scrollThrottleTimeout.value) clearTimeout(scrollThrottleTimeout.value);

    // Stop usage checking
    stopUsageChecking();

    // Remove scroll listener
    if (messagesContainer.value) {
        messagesContainer.value.removeEventListener('scroll', handleChatScroll);
        messagesContainer.value.removeEventListener('wheel', handleUserScrollIntent);
        messagesContainer.value.removeEventListener('touchmove', handleUserScrollIntent);
    }
    window.removeEventListener('keydown', handleUserScrollIntent);
});

function _checkScrollPosition() {
    if (!messagesContainer.value) return;

    const { scrollTop, scrollHeight, clientHeight } = messagesContainer.value;
    const atBottom = Math.abs(scrollHeight - scrollTop - clientHeight) < 10;

    if (atBottom) {
        userHasScrolledUp.value = false;
        isAutoScrollEnabled.value = true;
    }
}

onMounted(async () => {
    nextTick();

    // Check if this is a new conversation from Home page
    const isNewConversation = getQueryParam('new') === '1';

    // Only show loading if it's not a new conversation
    loadingMessages.value = !isNewConversation;

    // Initialize from chatEventBus
    isChatExpanded.value = chatEventBus.isChatExpanded.value;

    // Add passive scroll listener for better performance
    if (messagesContainer.value) {
        messagesContainer.value.addEventListener('scroll', handleChatScroll, { passive: true });
        messagesContainer.value.addEventListener('wheel', handleUserScrollIntent, { passive: true });
        messagesContainer.value.addEventListener('touchmove', handleUserScrollIntent, { passive: true });
        window.addEventListener('keydown', handleUserScrollIntent);
    }

    // Start usage checking
    startUsageChecking();

    // Apply color theme
    applyColorTheme(props.appearance);

    axios
        .get(route('conversations.messages', { conversationId: props.conversation_id }))
        .then((response) => {
            messages.value = response.data;

            // Re-hydrate chart / tool artifacts from each persisted message.
            // Without this, a page reload renders the LLM's "Here's a bar
            // chart of …" caption with no chart underneath, because the
            // runtime messageArtifacts Map is empty after a fresh mount.
            messages.value.forEach((message) => {
                if (
                    message.role === 'assistant' &&
                    message.id &&
                    Array.isArray(message.metadata?.artifacts) &&
                    message.metadata.artifacts.length > 0
                ) {
                    messageArtifacts.value.set(message.id, message.metadata.artifacts);
                }
            });

            loadingMessages.value = false;

            if (messages.value.length === 1) {
                const userIndex = 0;
                const assistantIndex = messages.value.length;
                messages.value.push({
                    role: 'assistant',
                    content: '',
                    id: 0,
                });
                submitChat({
                    message: messages.value[0].content,
                    userIndex,
                    assistantIndex,
                    // Forward attachments from the just-loaded user message so the
                    // LLM sees the file URLs on the very first turn.
                    attachments: messages.value[0].metadata?.attachments ?? [],
                    isNewConversation: true,
                });
            }

            nextTick(() => {
                scrollToBottom();
            });

            // Load sources asynchronously to avoid blocking the page
            loadSourcesAsync();
        })
        .catch((_error) => {
            router.visit(route(props.newChatRoute));
        });

    nextTick(() => {
        checkScreenSize();
    });

    chatEventBus.onChatExpandToggle((expanded) => {
        isChatExpanded.value = expanded;
    });
});

function scrollWhileStreaming() {
    // Only auto-scroll if user hasn't manually scrolled up
    if (isAutoScrollEnabled.value && !userHasScrolledUp.value) {
        if (!messagesContainer.value) return;

        // Throttle scroll updates to prevent scroll-linked effects
        if (scrollThrottleTimeout.value) {
            return; // Skip this update if we're throttling
        }

        scrollThrottleTimeout.value = setTimeout(() => {
            if (messagesContainer.value && isAutoScrollEnabled.value && !userHasScrolledUp.value) {
                // Use scrollTo with behavior: 'instant' to avoid scroll-linked effects
                messagesContainer.value.scrollTo({
                    top: messagesContainer.value.scrollHeight,
                    behavior: 'instant',
                });
            }
            scrollThrottleTimeout.value = null;
        }, 16); // ~60fps throttling
    }
}

/**
 * Build the SSE callback set for a stream targeting the message at
 * `assistantIndex`. Shared by submitChat (new / edited / regenerated turns) and
 * resumeApproval (the continuation after a human approves/denies a gated tool),
 * so both paths route envelope events to the right message identically.
 */
function buildStreamCallbacks({ userIndex, assistantIndex, isNewConversation = false, autoScrollOnDone = true }) {
    return {
        onStart: (data) => {
            if (messages.value[assistantIndex] && messages.value[assistantIndex].role === 'assistant') {
                messages.value[assistantIndex].id = data.assistant_message_id;
            }
            if (data.user_message_id && messages.value[userIndex] && messages.value[userIndex].role === 'user') {
                messages.value[userIndex].id = data.user_message_id;
            }
            // An input guardrail masked PII in the message — swap the raw text we
            // rendered optimistically for the server's redacted version.
            if (
                typeof data.user_message === 'string' &&
                messages.value[userIndex] &&
                messages.value[userIndex].role === 'user' &&
                messages.value[userIndex].content !== data.user_message
            ) {
                messages.value[userIndex].content = data.user_message;
            }
            streamingAssistantId.value = data.assistant_message_id;

            // A (resume) stream beginning supersedes any approval cards on this
            // message — the continuation now drives the UI.
            clearApprovalsFor(data.assistant_message_id);
        },
        onDelta: (text) => {
            const msg = findStreamingAssistant(streamingAssistantId.value, assistantIndex);
            if (msg) {
                msg.content += text;
                scrollWhileStreaming();
            }
        },
        onReplace: (text) => {
            // An output guardrail corrected the answer — swap the rendered text.
            const msg = findStreamingAssistant(streamingAssistantId.value, assistantIndex);
            if (msg) msg.content = text;
        },
        onMeta: (key, value) => {
            if (key === 'language' && typeof value === 'string') {
                const msg = findStreamingAssistant(streamingAssistantId.value, assistantIndex);
                if (msg) msg.language = value;
            }
            // 'generation_id' and 'handoff' are no-ops in this view; widget/admin handle handoff.
        },
        onSources: (sources, id) => {
            if (id) messageSources.value.set(id, sources);
        },
        onTool: (data, assistantMessageId) => {
            if (!data?.tool_call_id) return;

            // Gated call: surface an Approve/Deny card instead of running it.
            if (data.phase === 'awaiting_approval') {
                if (!assistantMessageId || !data.approval_id) return;
                const list = pendingApprovals.value.get(assistantMessageId) ?? [];
                if (!list.some((c) => c.tool_call_id === data.tool_call_id)) {
                    list.push({
                        tool_call_id: data.tool_call_id,
                        approval_id: data.approval_id,
                        name: data.name ?? '',
                        label: data.label ?? data.name ?? '',
                        kind: data.kind ?? 'function',
                        icon_url: data.icon_url ?? null,
                        brand_color: data.brand_color ?? null,
                        args: typeof data.args === 'string' ? data.args : '',
                        reason: data.reason ?? null,
                    });
                }
                pendingApprovals.value.set(assistantMessageId, list);
                nextTick(() => scrollWhileStreaming());
                return;
            }

            // Drives the branded "Calling <service>…" indicator. The backend
            // yields `tool { phase: 'start' }` before dispatching and
            // `{ phase: 'end' }` after — synchronous + in order with the stream.
            if (data.phase === 'start') {
                activeFunctionCalls.value.set(data.tool_call_id, {
                    tool_call_id: data.tool_call_id,
                    name: data.name ?? '',
                    kind: data.kind ?? 'function',
                    label: data.label ?? data.name ?? '',
                    activity_label: data.activity_label ?? null,
                    icon_url: data.icon_url ?? null,
                    brand_color: data.brand_color ?? null,
                    started_at: Date.now(),
                });
            } else if (data.phase === 'end') {
                activeFunctionCalls.value.delete(data.tool_call_id);

                // Capture any structured artifacts (charts, etc.) the tool
                // returned. We append rather than replace because a single
                // assistant turn can call multiple chart-emitting tools.
                if (assistantMessageId && Array.isArray(data.artifacts) && data.artifacts.length > 0) {
                    const existing = messageArtifacts.value.get(assistantMessageId) ?? [];
                    messageArtifacts.value.set(assistantMessageId, [...existing, ...data.artifacts]);
                }
            }
        },
        onError: (err) => {
            // Replace the empty assistant stub with an inline error message
            // so the user sees a typed card (with CTA / retry) where the reply
            // would have appeared, instead of a transient toast.
            const stub = messages.value[assistantIndex];
            if (stub && stub.role === 'assistant' && !stub.content) {
                messages.value[assistantIndex] = {
                    ...stub,
                    role: 'error',
                    content: err.message,
                    errorCode: err.code,
                    errorRetryable: err.retryable,
                };
            }
            streamingAssistantId.value = null;

            // Title even when the first turn errors. The user message is already
            // persisted (conversations.create stores it before streaming), so the
            // backend can still name the conversation from it — otherwise a failed
            // first reply leaves the chat stuck as "New Chat" forever. onDone and
            // onError are mutually exclusive, so this never double-fires per turn.
            if (isNewConversation) {
                void generateTitleThenRefresh();
            }
        },
        onDone: () => {
            streamingAssistantId.value = null;
            activeFunctionCalls.value.clear();

            if (isNewConversation) {
                void generateTitleThenRefresh();
            }

            if (getQueryParam('new') === '1') {
                const url = new URL(window.location);
                url.searchParams.delete('new');
                window.history.replaceState({}, '', url);
            }

            if (autoScrollOnDone && isAutoScrollEnabled.value) {
                nextTick(() => scrollToBottom());
            }
        },
    };
}

/** Drop any approval cards (and resolving state) for an assistant message. */
function clearApprovalsFor(assistantMessageId) {
    const list = pendingApprovals.value.get(assistantMessageId);
    if (!list) return;
    const ids = list.map((c) => c.approval_id);
    pendingApprovals.value.delete(assistantMessageId);
    resolvingApprovalIds.value = resolvingApprovalIds.value.filter((x) => !ids.includes(x));
}

function approveTool(call) {
    decideApproval(call, 'approve');
}

function denyTool(call) {
    decideApproval(call, 'deny');
}

/**
 * Record one card's decision. Once every gated call sharing the same checkpoint
 * (approval_id) has been decided, post them together and re-open the stream.
 */
function decideApproval(call, decision) {
    if (resolvingApprovalIds.value.includes(call.approval_id)) return;

    const decisions = approvalDecisions.get(call.approval_id) ?? {};
    decisions[call.tool_call_id] = decision;
    approvalDecisions.set(call.approval_id, decisions);

    let assistantMessageId = null;
    for (const [mid, calls] of pendingApprovals.value.entries()) {
        if (calls.some((c) => c.approval_id === call.approval_id)) {
            assistantMessageId = mid;
            break;
        }
    }
    if (assistantMessageId === null) return;

    const siblings = (pendingApprovals.value.get(assistantMessageId) ?? []).filter((c) => c.approval_id === call.approval_id);
    if (siblings.every((c) => decisions[c.tool_call_id])) {
        resolveApproval(call.approval_id, assistantMessageId, decisions);
    }
}

/**
 * Re-open the stream against the resolve endpoint with the collected decisions.
 * The continuation streams under the original assistant message id; its
 * `onStart` clears the cards (see clearApprovalsFor).
 */
function resolveApproval(approvalId, assistantMessageId, decisions) {
    resolvingApprovalIds.value = [...resolvingApprovalIds.value, approvalId];
    approvalDecisions.delete(approvalId);

    const assistantIndex = messages.value.findIndex((m) => m.id === assistantMessageId);
    if (assistantIndex === -1) {
        clearApprovalsFor(assistantMessageId);
        return;
    }

    userHasScrolledUp.value = false;
    isAutoScrollEnabled.value = true;
    streamingAssistantId.value = null;

    startStream(
        route('chat.tools.resolve', { approval: approvalId }),
        JSON.stringify({ decisions }),
        buildStreamCallbacks({ userIndex: assistantIndex - 1, assistantIndex, isNewConversation: false, autoScrollOnDone: true }),
    );
}

/**
 * Title the conversation, then refresh the sidebar. Titling is a quick
 * synchronous endpoint (no queue); we call it async so it never blocks the UI,
 * and only reload the conversation list once the real title has landed — no
 * fixed delay guessing whether a background job finished. Best-effort: the
 * sidebar still refreshes (showing "New Chat") if titling fails.
 */
async function generateTitleThenRefresh() {
    // Retry once: the endpoint is idempotent (a no-op once the conversation is
    // named) and cheap, so a single transient blip — a 503 under load, a network
    // hiccup — shouldn't permanently leave the chat as "New Chat". Best-effort
    // throughout; the sidebar still refreshes even if both attempts fail.
    for (let attempt = 0; attempt < 2; attempt++) {
        try {
            await axios.post(route('conversations.title', { conversationId: props.conversation_id }));
            break;
        } catch {
            if (attempt === 0) {
                await new Promise((resolve) => setTimeout(resolve, 1000));
            }
        }
    }

    chatEventBus.emitConversationCreated(props.conversation_id);
}

/**
 * Single entry point for all stream invocations (compose / edit / regenerate).
 *
 * The caller mutates `messages` first (push or replace user + assistant stubs)
 * and tells us the indices. We own the stream lifecycle and route every
 * incoming envelope event to the right message via the assistant UUID that
 * the server emits in the `start` event.
 */
function submitChat({
    message,
    userIndex,
    assistantIndex,
    updatedMessageInfo = {},
    saveUserMessageImmediately = false,
    createdAt = null,
    isNewConversation = false,
    autoScrollOnDone = true,
    attachments = [],
}) {
    const requestId = window.crypto?.randomUUID?.() ?? `${Date.now()}-${Math.random().toString(36).slice(2)}`;

    streamingAssistantId.value = null;
    // A fresh turn supersedes any pending approval cards.
    pendingApprovals.value.clear();
    resolvingApprovalIds.value = [];
    approvalDecisions.clear();

    const callbacks = buildStreamCallbacks({ userIndex, assistantIndex, isNewConversation, autoScrollOnDone });

    startStream(
        route('chat.stream', { conversationId: props.conversation_id }),
        JSON.stringify({
            message,
            updated_message_info: updatedMessageInfo,
            save_user_message_immediately: saveUserMessageImmediately,
            created_at: createdAt,
            request_id: requestId,
            attachments,
        }),
        callbacks,
    );
}

/**
 * Resolve the assistant message currently being streamed. Prefer the UUID
 * once the server has sent it; fall back to the array index until then.
 */
function findStreamingAssistant(id, fallbackIndex) {
    if (id) {
        const byId = messages.value.find((m) => m.id === id);
        if (byId) return byId;
    }
    return messages.value[fallbackIndex];
}

const stopStreaming = () => {
    stopStream();
};

const messageUpdated = (messageId, messageContent) => {
    const userIndex = messages.value.findIndex((m) => m.id === messageId);
    if (userIndex === -1) return;

    const currentMessage = messages.value[userIndex];
    if (currentMessage.content === messageContent) return;

    const assistantIndex = userIndex + 1;
    const assistant = messages.value[assistantIndex];
    if (!assistant || assistant.role !== 'assistant') return;

    const previousAssistantId = assistant.id;

    currentMessage.content = messageContent;
    assistant.content = '';
    assistant.id = 0;

    if (previousAssistantId) {
        messageSources.value.delete(previousAssistantId);
        messageArtifacts.value.delete(previousAssistantId);
    }

    userHasScrolledUp.value = false;
    isAutoScrollEnabled.value = true;

    submitChat({
        message: messageContent,
        userIndex,
        assistantIndex,
        updatedMessageInfo: {
            userMessageId: currentMessage.id,
            assistantMessageId: previousAssistantId,
        },
    });
};

const attachmentDeleted = (messageId, attachmentId) => {
    const message = messages.value.find((m) => m.id === messageId);
    if (!message) return;
    // The hydrator merges `attachments` onto the message at fetch time;
    // freshly-sent messages also keep a copy under metadata.attachments.
    // Drop both so the chip doesn't re-appear on the next render.
    if (Array.isArray(message.attachments)) {
        message.attachments = message.attachments.filter((a) => a.id !== attachmentId);
    }
    if (message.metadata?.attachments) {
        message.metadata.attachments = message.metadata.attachments.filter((a) => a.id !== attachmentId);
    }
};

const messageDeleted = (messageId) => {
    const messageIndex = messages.value.findIndex((m) => m.id === messageId);
    if (messageIndex === -1) return;

    const message = messages.value[messageIndex];

    if (message.role === 'user') {
        // Delete the user message and its following assistant reply (if present).
        const next = messages.value[messageIndex + 1];
        if (next && next.role === 'assistant') {
            if (next.id) {
                messageSources.value.delete(next.id);
                messageArtifacts.value.delete(next.id);
            }
            messages.value.splice(messageIndex, 2);
        } else {
            messages.value.splice(messageIndex, 1);
        }
    } else {
        // Delete the assistant message and its preceding user message (if present).
        const prev = messages.value[messageIndex - 1];
        if (message.id) {
            messageSources.value.delete(message.id);
            messageArtifacts.value.delete(message.id);
        }
        if (prev && prev.role === 'user') {
            messages.value.splice(messageIndex - 1, 2);
        } else {
            messages.value.splice(messageIndex, 1);
        }
    }

    if (messages.value.length === 0) {
        router.visit(route(props.newChatRoute));
    }
};

const feedbackProvided = (messageId, type) => {
    // TODO: Implement feedback handling
    console.log('Feedback:', messageId, type);
};

// Shared edit/regen flow: delete both messages on the server (which also
// returns the original timestamp), then restream into the same slot.
async function reissue({ userMessageId, assistantMessageId, overrideContent = null }) {
    let response;
    try {
        response = await axios.post(route('chat.messages.regenerate'), {
            conversation_id: props.conversation_id,
            user_message_id: userMessageId,
            assistant_message_id: assistantMessageId,
        });
    } catch (error) {
        // 404 = the conversation was deleted elsewhere (e.g. another tab), so
        // there's nothing server-side to regenerate. Tell the user it's gone
        // and fall back to a fresh chat rather than logging a scary error.
        if (error?.response?.status === 404) {
            toast.error(t('chat.conversation_gone'));
            router.visit(route(props.newChatRoute));
            return;
        }
        console.error('Failed to reissue message:', error);
        toast.error(t('chat.regenerate_failed'));
        return;
    }

    if (!response.data?.success) {
        toast.error(t('chat.regenerate_failed'));
        return;
    }

    const createdAt = response.data.created_at;
    const newContent = overrideContent ?? response.data.user_message_content;

    const userIndex = messages.value.findIndex((m) => m.id === userMessageId);
    const assistantIndex = messages.value.findIndex((m) => m.id === assistantMessageId);
    if (userIndex === -1 || assistantIndex === -1) {
        console.error('Could not find messages to reissue');
        toast.error(t('chat.regenerate_failed'));
        return;
    }

    if (assistantMessageId) {
        messageSources.value.delete(assistantMessageId);
        messageArtifacts.value.delete(assistantMessageId);
    }

    messages.value[userIndex] = { role: 'user', content: newContent, id: 0 };
    messages.value[assistantIndex] = { role: 'assistant', content: '', id: 0 };

    userHasScrolledUp.value = true;
    isAutoScrollEnabled.value = false;

    await nextTick(() => {
        const messageElement = document.getElementById(`message-${userIndex}`);
        if (messageElement) {
            messageElement.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }

        submitChat({
            message: newContent,
            userIndex,
            assistantIndex,
            saveUserMessageImmediately: true,
            createdAt,
            autoScrollOnDone: false,
        });
    });
}

const handleMessageEdited = (userMessageId, assistantMessageId, newContent) => {
    return reissue({ userMessageId, assistantMessageId, overrideContent: newContent });
};

const handleRegenerate = async (userMessageId, assistantMessageId, onDone) => {
    try {
        await reissue({ userMessageId, assistantMessageId });
    } finally {
        onDone?.();
    }
};

const handleErrorRetry = (errorIndex) => {
    const prevUser = messages.value[errorIndex - 1];
    if (!prevUser || prevUser.role !== 'user') return;

    // The error bubble was never a real assistant message — the stream
    // failed before `start` fired, so there's nothing server-side to
    // regenerate against. Replace the bubble with a fresh assistant stub
    // and re-issue the stream into the same slot.
    const userIndex = errorIndex - 1;
    const assistantIndex = errorIndex;
    messages.value[assistantIndex] = { role: 'assistant', content: '', id: 0 };

    userHasScrolledUp.value = false;
    isAutoScrollEnabled.value = true;

    submitChat({
        message: prevUser.content,
        userIndex,
        assistantIndex,
    });
};

// Function to handle scroll to bottom button click
const handleScrollToBottomClick = () => {
    scrollToBottom(true); // Use smooth scrolling for button clicks
    // Ensure auto-scroll is re-enabled
    userHasScrolledUp.value = false;
    isAutoScrollEnabled.value = true;
};
</script>

<template>
    <ChatLayout
        :appearance="resolvedAppearance"
        :removeSidebar="props.removeSidebar"
        :hideSidebarOnPageLoad="props.hideSidebarOnPageLoad"
        :enableThemeSwitcher="props.enableThemeSwitcher"
        :defaultTheme="props.defaultTheme"
        :enableChatExpand="props.enableChatExpand"
        :enableLanguageSwitcher="props.enableLanguageSwitcher"
        :enabledLanguages="props.enabledLanguages"
        :newChatRoute="props.newChatRoute"
    >
        <template #content>
            <div class="relative flex h-full flex-col overflow-hidden">
                <div
                    id="messages-container"
                    ref="messagesContainer"
                    :class="['flex-1 overflow-x-hidden overflow-y-auto', { 'flex flex-col justify-between': messages.length === 0 }]"
                >
                    <!-- Loading state -->
                    <div v-if="loadingMessages" class="flex h-full items-center justify-center">
                        <div class="flex flex-col items-center gap-3">
                            <div class="flex gap-1">
                                <div class="bg-muted-foreground/40 h-2 w-2 animate-bounce rounded-full [animation-delay:-0.3s]"></div>
                                <div class="bg-muted-foreground/40 h-2 w-2 animate-bounce rounded-full [animation-delay:-0.15s]"></div>
                                <div class="bg-muted-foreground/40 h-2 w-2 animate-bounce rounded-full"></div>
                            </div>
                            <p class="text-muted-foreground text-xs">{{ t('chat.loading_conversation') }}</p>
                        </div>
                    </div>

                    <!-- Messages -->
                    <div v-else class="min-h-full">
                        <div
                            v-for="(message, index) in messages"
                            :id="`message-${index}`"
                            :key="`message-${message.id || index}`"
                            :class="['w-full', message.role === 'assistant' ? '' : '']"
                        >
                            <div
                                :class="[
                                    'mx-auto px-4 transition-all duration-300 ease-in-out sm:px-8',
                                    isChatExpanded ? 'max-w-6xl' : 'max-w-3xl',
                                    index === 0 ? 'pt-6' : '',
                                    index > 0 && messages[index - 1].role !== message.role ? 'pt-6' : 'pt-3',
                                    'pb-3',
                                ]"
                            >
                                <UserChatBubble
                                    v-if="message.role === 'user'"
                                    :message="message"
                                    :assistantMessageId="
                                        index < messages.length - 1 && messages[index + 1]?.role === 'assistant' ? messages[index + 1].id : undefined
                                    "
                                    :conversationId="conversation_id"
                                    :isRTL="isMessageRTL(message)"
                                    @messageUpdated="messageUpdated"
                                    @messageDeleted="messageDeleted"
                                    @messageEdited="handleMessageEdited"
                                    @attachmentDeleted="attachmentDeleted"
                                />
                                <ChatErrorBubble v-else-if="message.role === 'error'" :message="message" @retry="handleErrorRetry(index)" />
                                <AssistantChatBubble
                                    v-else
                                    :message="message"
                                    :userMessageId="index > 0 && messages[index - 1]?.role === 'user' ? messages[index - 1].id : undefined"
                                    :sources="message.id ? messageSources.get(message.id) || [] : []"
                                    :artifacts="message.id ? messageArtifacts.get(message.id) || [] : []"
                                    :conversationId="conversation_id"
                                    :hideFeedback="isMessageStreaming(message)"
                                    :isThinking="isMessageStreaming(message) && !message.content"
                                    :isStreaming="isMessageStreaming(message)"
                                    :isCallingExternal="isMessageStreaming(message) && hasActiveFunctionCalls"
                                    :activeCalls="isMessageStreaming(message) ? activeFunctionCallsList : []"
                                    :avatar="props.appearance?.avatar"
                                    :isRTL="isMessageRTL(message)"
                                    :pendingApprovals="message.id ? pendingApprovals.get(message.id) || [] : []"
                                    :resolvingApprovalIds="resolvingApprovalIds"
                                    @messageDeleted="messageDeleted"
                                    @feedbackProvided="feedbackProvided"
                                    @regenerate="handleRegenerate"
                                    @approveTool="approveTool"
                                    @denyTool="denyTool"
                                />
                            </div>
                        </div>
                    </div>

                    <!-- Add bottom padding to last message -->
                    <div class="pb-6"></div>
                </div>

                <!-- Scroll to bottom button -->
                <div class="pointer-events-none absolute start-0 end-0 bottom-36 z-10 flex justify-center">
                    <Transition name="scale-fade">
                        <button
                            v-if="showScrollToBottom && messages.length > 0"
                            class="bg-card text-foreground hover:bg-muted ring-border pointer-events-auto flex h-9 w-9 cursor-pointer items-center justify-center rounded-full shadow-lg ring-1 transition-colors"
                            @click="handleScrollToBottomClick"
                        >
                            <ChevronDown class="h-4 w-4" />
                        </button>
                    </Transition>
                </div>

                <!-- Sticky composer area -->
                <div class="bg-background shrink-0 px-4 pt-4 pb-6 sm:px-6">
                    <div class="mx-auto transition-all duration-300" :class="[isChatExpanded ? 'max-w-5xl' : 'max-w-3xl']">
                        <ChatComposer
                            :isThinking="isStreaming"
                            :placeholder="resolvedAppearance.composer_placeholder"
                            :allow-file-uploads="appearance.allow_file_uploads !== false"
                            :allow-voice-input="appearance.allow_voice_input !== false"
                            :max-files="maxFilesPerChat"
                            :disabled="!canSendMessages || hasPendingApprovals"
                            :upload-endpoint="route('chat.messages.attachments.store')"
                            :upload-headers="{ 'X-AGENT-ID': String(props.agent_id), 'X-CONVERSATION-ID': props.conversation_id }"
                            :delete-endpoint="(id) => route('chat.messages.attachments.delete', { attachment: id })"
                            @messageAdded="messageAdded"
                            @stopStreaming="stopStreaming"
                        />

                        <!-- Branding: hidden when the workspace owns the remove-branding add-on -->
                        <div v-if="props.chatviaBranding" class="mt-2 flex items-center justify-center">
                            <a
                                href="https://chatvia.ai"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="text-muted-foreground/50 hover:text-muted-foreground/80 inline-flex items-center gap-1 text-[11px] transition-colors"
                            >
                                <img src="/images/logo.svg" alt="Chatvia" class="h-3.5 w-3.5 opacity-70" />
                                Powered by Chatvia
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </template>
    </ChatLayout>
</template>

<style>
@import url('https://fonts.googleapis.com/css2?family=Noto+Sans+Arabic:wght@100..900&display=swap');

/* Force scrollbar to always be visible to prevent layout shifts */
#messages-container {
    overflow-y: scroll; /* Changed from auto to scroll */
    scrollbar-gutter: stable; /* Reserves space for scrollbar */
    -webkit-overflow-scrolling: touch; /* For iOS devices */
    /* Optimize rendering performance */
    transform: translateZ(0);
    backface-visibility: hidden;
    -webkit-backface-visibility: hidden;
}

/* Custom scrollbar styling with better stability */
#messages-container::-webkit-scrollbar {
    width: 8px;
    height: 8px;
}

#messages-container::-webkit-scrollbar-track {
    background: transparent;
}

#messages-container::-webkit-scrollbar-thumb {
    background-color: rgba(155, 155, 155, 0.5);
    border-radius: 20px;
    border: 2px solid transparent;
    background-clip: content-box;
}

.dark #messages-container::-webkit-scrollbar-thumb {
    background-color: rgba(155, 155, 155, 0.3);
}

#messages-container::-webkit-scrollbar-thumb:hover {
    background-color: rgba(155, 155, 155, 0.7);
}

.dark #messages-container::-webkit-scrollbar-thumb:hover {
    background-color: rgba(155, 155, 155, 0.5);
}

/* Firefox scrollbar support */
#messages-container {
    scrollbar-width: thin;
    scrollbar-color: rgba(155, 155, 155, 0.5) transparent;
}

.dark #messages-container {
    scrollbar-color: rgba(155, 155, 155, 0.3) transparent;
}

/* Remove transitions */

/* Reset some bulky defaults from Tailwind typography */
.prose ul,
.prose ol {
    margin-top: 0.5em;
    margin-bottom: 0.5em;
}

.prose li {
    margin-top: 0.25em;
    margin-bottom: 0.25em;
}

.prose p {
    margin-top: 0.5em;
    margin-bottom: 0.5em;
}

.prose pre {
    margin-top: 0.75em;
    margin-bottom: 0.75em;
}

/* Responsive design - add breakpoints */
@media (max-width: 640px) {
    .prose {
        font-size: 0.9rem;
    }
}

/* Prevent sidebar from taking full width on very small screens */
@media (max-width: 400px) {
    .w-85 {
        width: 85% !important;
    }
}

/* Markdown styles for AI responses */
#chat-ai-responses {
    /* Base styles */
    font-size: 1rem;
    line-height: 1.8rem;
    overflow-wrap: break-word;

    /* Code blocks */

    & pre {
        background-color: rgba(0, 0, 0, 0.05);
        border-radius: 0.375rem;
        padding: 1rem;
        margin: 1rem 0;
        overflow-x: auto;
        font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, 'Liberation Mono', 'Courier New', monospace;
        font-size: 0.875rem;
    }

    /* Dark mode for code blocks */

    .dark & pre {
        background-color: rgba(255, 255, 255, 0.05);
    }

    /* Inline code */

    & code:not(pre code) {
        background-color: rgba(0, 0, 0, 0.05);
        border-radius: 0.25rem;
        padding: 0.125rem 0.25rem;
        font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, 'Liberation Mono', 'Courier New', monospace;
        font-size: 0.875em;
    }

    /* Dark mode for inline code */

    .dark & code:not(pre code) {
        background-color: rgba(255, 255, 255, 0.05);
    }

    /* Headings */

    & h1,
    & h2,
    & h3,
    & h4,
    & h5,
    & h6 {
        font-weight: 600;
        line-height: 1.25;
        margin-top: 1.5em;
        margin-bottom: 0.5em;
    }

    & h1 {
        font-size: 1.5em;
    }

    & h2 {
        font-size: 1.25em;
    }

    & h3 {
        font-size: 1.125em;
    }

    /* Lists */

    & ul,
    & ol {
        padding-left: 1.5rem;
        margin: 0.5rem 0;
    }

    & ul {
        list-style-type: disc;
    }

    & ol {
        list-style-type: decimal;
    }

    & li {
        margin: 0.25rem 0;
    }

    /* Nested lists */

    & ul ul,
    & ol ul {
        list-style-type: circle;
    }

    & ul ul ul,
    & ol ul ul {
        list-style-type: square;
    }

    /* Blockquotes */

    & blockquote {
        border-left: 4px solid #e5e7eb;
        padding-left: 1rem;
        color: #6b7280;
        margin: 1rem 0;
    }

    .dark & blockquote {
        border-left-color: #4b5563;
        color: #9ca3af;
    }

    /* Tables */

    & table {
        width: 100%;
        border-collapse: collapse;
        margin: 1rem 0;
    }

    & th,
    & td {
        border: 1px solid #e5e7eb;
        padding: 0.5rem;
        text-align: left;
    }

    .dark & th,
    .dark & td {
        border-color: #4b5563;
    }

    & th {
        background-color: rgba(0, 0, 0, 0.05);
        font-weight: 600;
    }

    .dark & th {
        background-color: rgba(255, 255, 255, 0.05);
    }

    /* Links */

    & a {
        color: #2563eb;
        text-decoration: underline;
    }

    & a:hover {
        text-decoration: none;
    }

    .dark & a {
        color: #3b82f6;
    }

    /* Images */

    & img {
        max-width: 100%;
        height: auto;
        border-radius: 0.375rem;
    }

    /* Horizontal rule */

    & hr {
        border: 0;
        border-top: 1px solid #e5e7eb;
        margin: 1.5rem 0;
    }

    .dark & hr {
        border-top-color: #4b5563;
    }

    /* Syntax highlighting enhancements */

    & .hljs-keyword,
    & .hljs-selector-tag,
    & .hljs-subst {
        color: #569cd6;
    }

    & .hljs-string,
    & .hljs-doctag {
        color: #ce9178;
    }

    & .hljs-number,
    & .hljs-literal {
        color: #b5cea8;
    }

    & .hljs-comment,
    & .hljs-quote {
        color: #6a9955;
        font-style: italic;
    }
}

/* Responsive adjustments for markdown */
@media (max-width: 640px) {
    #chat-ai-response {
        font-size: 0.9rem;

        & pre {
            padding: 0.75rem;
            font-size: 0.8rem;
        }
    }
}

/* ChatMessage container expansion animation */
.chat-container {
    transition: max-width 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.chat-expanded-content {
    max-width: 100% !important;
}

.chat-expanded-content > div > div {
    max-width: 100% !important;
}

/* Adjust padding for expanded mode */
.chat-expanded-content > div > div {
    padding-left: 8% !important;
    padding-right: 8% !important;
}

/* Responsive adjustments for expanded mode */
@media (min-width: 1280px) {
    .chat-expanded-content > div > div {
        padding-left: 12% !important;
        padding-right: 12% !important;
    }
}

@media (min-width: 1536px) {
    .chat-expanded-content > div > div {
        padding-left: 15% !important;
        padding-right: 15% !important;
    }
}

/* Transitions removed */

html,
body,
#app {
    height: 100%;
}

/* ChatMessage list animations removed */

/* Smooth scrolling */
#messages-container {
    -webkit-overflow-scrolling: touch; /* For iOS devices */
    /* Optimize rendering performance */
    transform: translateZ(0);
    backface-visibility: hidden;
    -webkit-backface-visibility: hidden;
}

/* Prevent scroll jumps during streaming */
#messages-container > div {
    will-change: transform;
}

/* Streaming stabilization removed */

/* Optimize rendering for streaming messages */
#messages-container [id^='message-']:last-child {
    /* Isolate the last message to prevent reflows affecting other messages */
    contain: layout style;
}

/* Better focus states */
:focus-visible {
    outline: 2px solid hsl(var(--primary));
    outline-offset: 2px;
}

/* Transitions */
.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.2s ease;
}
.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}

.scale-fade-enter-active,
.scale-fade-leave-active {
    transition: all 0.2s ease;
}
.scale-fade-enter-from,
.scale-fade-leave-to {
    opacity: 0;
    transform: scale(0.9) translateY(10px);
}

/* Fade transitions removed */

/* Custom appearance colors */
.chat-assistant-bubble {
    background-color: var(--chat-bubble-color, rgb(243 244 246)) !important;
    color: var(--chat-text-color, inherit) !important;
}

.chat-primary-button {
    background-color: var(--chat-primary-color, hsl(var(--primary))) !important;
}

.dark .chat-assistant-bubble {
    background-color: var(--chat-bubble-color, rgb(31 41 55)) !important;
}

/* Arabic Font Support */
[data-rtl='true'] {
    font-family:
        'Noto Sans Arabic',
        -apple-system,
        BlinkMacSystemFont,
        'Segoe UI',
        Roboto,
        sans-serif;
}

[data-rtl='true'] .prose {
    font-family:
        'Noto Sans Arabic',
        -apple-system,
        BlinkMacSystemFont,
        'Segoe UI',
        Roboto,
        sans-serif;
}

[data-rtl='true'] .prose p,
[data-rtl='true'] .prose h1,
[data-rtl='true'] .prose h2,
[data-rtl='true'] .prose h3,
[data-rtl='true'] .prose h4,
[data-rtl='true'] .prose h5,
[data-rtl='true'] .prose h6,
[data-rtl='true'] .prose li,
[data-rtl='true'] .prose blockquote,
[data-rtl='true'] .prose td,
[data-rtl='true'] .prose th {
    font-family:
        'Noto Sans Arabic',
        -apple-system,
        BlinkMacSystemFont,
        'Segoe UI',
        Roboto,
        sans-serif;
}
</style>
