<script setup>
import AssistantChatBubble from '@/components/chat/AssistantChatBubble.vue';
import ChatComposer from '@/components/chat/ChatComposer.vue';
import ChatLayout from '@/components/chat/ChatLayout.vue';
import UserChatBubble from '@/components/chat/UserChatBubble.vue';
import { useColorTheme } from '@/composables/useColorTheme';
import { useChatStream } from '@/composables/useChatStream';
import { router } from '@inertiajs/vue3';
import { useScroll } from '@vueuse/core';
import axios from 'axios';
import { ChevronDown } from 'lucide-vue-next';
import { computed, inject, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';
import { toast } from 'vue-sonner';

const chatEventBus = inject('chatEventBus');

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
            chat_footer_text: null,
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
});

const loadingMessages = ref(true);
const messages = ref([]);
const isSidebarOpen = ref(false);
const isAutoScrollEnabled = ref(true);
const scrollTimeout = ref(null);
const messagesContainer = ref(null);
const { arrivedState } = useScroll(messagesContainer);
const selectedModel = ref({});
const streamingAssistantId = ref(null);
const isChatExpanded = ref(false);
const userHasScrolledUp = ref(false);
const lastScrollTop = ref(0);
const scrollAnimationFrame = ref(null);
const messageSources = ref(new Map()); // Keyed by assistant message UUID (string)
const scrollThrottleTimeout = ref(null); // For throttling scroll updates

// In-flight function/tool calls keyed by tool_call_id. Populated by the
// `tool` envelope event from useChatStream — see submitChat()'s onTool
// callback below. Synchronous + ordered with the LLM stream; the equivalent
// async pattern (knowledge-base ingestion) uses Reverb broadcasts instead.
const activeFunctionCalls = ref(new Map());

const hasActiveFunctionCalls = computed(() => activeFunctionCalls.value.size > 0);

// Usage checking
const USAGE_CHECK_INTERVAL = 60000; // 1 minute
const canSendMessages = ref(true);
const usageCheckInterval = ref(null);

// Add reactive refs for models
const models = ref([]);
const defaultModel = ref(null);
const isLoadingModels = ref(true);

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

const updateModel = (model) => {
    selectedModel.value = model;
};

// Helper function to check if a message is RTL
const isMessageRTL = (message) => {
    const rtlLanguages = ['ar', 'he', 'fa', 'ur', 'ps', 'sd', 'yi', 'dv'];
    return message.language && rtlLanguages.includes(message.language);
};

const getQueryParam = (param) => {
    const urlParams = new URLSearchParams(window.location.search);
    return urlParams.get(param);
};

// Add function to fetch models
const fetchModels = async () => {
    try {
        isLoadingModels.value = true;
        const response = await axios.get(route('chat.models.index'));
        models.value = response.data.models;

        const urlModelId = getQueryParam('model');

        // Validate model from URL - use first valid model if invalid
        if (urlModelId) {
            const urlModel = models.value.find((m) => m.id === urlModelId);
            if (!urlModel) {
                // Invalid model in URL, redirect with first available model
                const firstModel = models.value[0] || response.data.default;
                router.visit(
                    route('chat.show', {
                        conversationId: props.conversation_id,
                        model: firstModel?.id,
                    }),
                    { replace: true },
                );
                return;
            }
            defaultModel.value = urlModel;
        } else {
            defaultModel.value = response.data.default;
        }
    } catch (error) {
        console.error('Failed to fetch models:', error);
    } finally {
        isLoadingModels.value = false;
    }
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

// Show typed stream errors as toasts. The bubble UI keeps the assistant
// message stub if there's already content; otherwise the streaming-cleanup
// code in submitChat removes it.
watch(streamError, (err) => {
    if (err) {
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

async function messageAdded(message, model) {
    const userIndex = messages.value.length;
    messages.value.push({
        role: 'user',
        content: message,
        id: 0,
    });

    const assistantIndex = messages.value.length;
    messages.value.push({
        role: 'assistant',
        content: '',
        model: model.id,
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
            model,
            userIndex,
            assistantIndex,
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

    // Check if user is scrolling up (increased threshold to prevent false positives)
    if (currentScrollTop < lastScrollTop.value && scrollBottom > 100) {
        userHasScrolledUp.value = true;
        isAutoScrollEnabled.value = false;
    }

    // Check if user scrolled to bottom (within 20px threshold for better detection)
    if (scrollBottom < 20) {
        userHasScrolledUp.value = false;
        isAutoScrollEnabled.value = true;
    }

    lastScrollTop.value = currentScrollTop;
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
    }
});

function checkScrollPosition() {
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
    }

    // Start usage checking
    startUsageChecking();

    // Apply color theme
    applyColorTheme(props.appearance);

    // Wait for models to be loaded before loading messages
    await fetchModels();

    axios
        .get(route('conversations.messages', { conversationId: props.conversation_id }))
        .then((response) => {
            messages.value = response.data;

            loadingMessages.value = false;

            const modelId = getQueryParam('model') || defaultModel.value?.id;

            if (messages.value.length === 1) {
                const model = models.value.find((m) => m.id === modelId) || defaultModel.value;
                const userIndex = 0;
                const assistantIndex = messages.value.length;
                messages.value.push({
                    role: 'assistant',
                    content: '',
                    model: model?.id,
                    id: 0,
                });
                submitChat({
                    message: messages.value[0].content,
                    model,
                    userIndex,
                    assistantIndex,
                    isNewConversation: true,
                });
            }

            nextTick(() => {
                scrollToBottom();
            });

            // Load sources asynchronously to avoid blocking the page
            loadSourcesAsync();
        })
        .catch((error) => {
            router.visit(route(props.newChatRoute, { model: defaultModel.value?.id }));
        });

    nextTick(() => {
        const modelId = getQueryParam('model');

        if (!modelId) {
            router.visit(
                route('chat.show', {
                    conversationId: props.conversation_id,
                    model: defaultModel.value?.id,
                }),
                {
                    preserveScroll: true,
                    preserveState: true,
                },
            );
        }

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
 * Single entry point for all stream invocations (compose / edit / regenerate).
 *
 * The caller mutates `messages` first (push or replace user + assistant stubs)
 * and tells us the indices. We own the stream lifecycle and route every
 * incoming envelope event to the right message via the assistant UUID that
 * the server emits in the `start` event.
 */
function submitChat({
    message,
    model,
    userIndex,
    assistantIndex,
    updatedMessageInfo = {},
    saveUserMessageImmediately = false,
    createdAt = null,
    isNewConversation = false,
    autoScrollOnDone = true,
}) {
    const requestId = window.crypto?.randomUUID?.() ?? `${Date.now()}-${Math.random().toString(36).slice(2)}`;

    streamingAssistantId.value = null;

    const callbacks = {
        onStart: (data) => {
            if (messages.value[assistantIndex] && messages.value[assistantIndex].role === 'assistant') {
                messages.value[assistantIndex].id = data.assistant_message_id;
            }
            if (data.user_message_id && messages.value[userIndex] && messages.value[userIndex].role === 'user') {
                messages.value[userIndex].id = data.user_message_id;
            }
            streamingAssistantId.value = data.assistant_message_id;
        },
        onDelta: (text) => {
            const msg = findStreamingAssistant(streamingAssistantId.value, assistantIndex);
            if (msg) {
                msg.content += text;
                scrollWhileStreaming();
            }
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
        onTool: (data) => {
            // Drives the "Calling <function>..." indicator. The backend
            // yields `tool { phase: 'start' }` before dispatching the
            // webhook and `{ phase: 'end' }` after — synchronous + in
            // order with the LLM stream.
            if (!data?.tool_call_id) return;
            if (data.phase === 'start') {
                activeFunctionCalls.value.set(data.tool_call_id, {
                    name: data.name ?? '',
                    started_at: Date.now(),
                });
            } else if (data.phase === 'end') {
                activeFunctionCalls.value.delete(data.tool_call_id);
            }
        },
        onError: () => {
            // Toast is shown by the streamError watcher; here we clean up the
            // empty assistant stub so the UI doesn't show an empty bubble.
            const stub = messages.value[assistantIndex];
            if (stub && stub.role === 'assistant' && !stub.content) {
                messages.value.splice(assistantIndex, 1);
            }
            streamingAssistantId.value = null;
        },
        onDone: () => {
            streamingAssistantId.value = null;
            activeFunctionCalls.value.clear();

            if (isNewConversation) {
                setTimeout(() => {
                    chatEventBus.emitConversationCreated(props.conversation_id);
                }, 2000);
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

    startStream(
        route('chat.stream', { conversationId: props.conversation_id }),
        JSON.stringify({
            model: model.id,
            message,
            updated_message_info: updatedMessageInfo,
            save_user_message_immediately: saveUserMessageImmediately,
            created_at: createdAt,
            request_id: requestId,
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
    }

    userHasScrolledUp.value = false;
    isAutoScrollEnabled.value = true;

    submitChat({
        message: messageContent,
        model: selectedModel.value,
        userIndex,
        assistantIndex,
        updatedMessageInfo: {
            userMessageId: currentMessage.id,
            assistantMessageId: previousAssistantId,
        },
    });
};

const messageDeleted = (messageId) => {
    const messageIndex = messages.value.findIndex((m) => m.id === messageId);
    if (messageIndex === -1) return;

    const message = messages.value[messageIndex];

    if (message.role === 'user') {
        // Delete the user message and its following assistant reply (if present).
        const next = messages.value[messageIndex + 1];
        if (next && next.role === 'assistant') {
            if (next.id) messageSources.value.delete(next.id);
            messages.value.splice(messageIndex, 2);
        } else {
            messages.value.splice(messageIndex, 1);
        }
    } else {
        // Delete the assistant message and its preceding user message (if present).
        const prev = messages.value[messageIndex - 1];
        if (message.id) messageSources.value.delete(message.id);
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
async function reissue({ userMessageId, assistantMessageId, model, overrideContent = null }) {
    if (!model) {
        console.error('No model selected');
        return;
    }

    let response;
    try {
        response = await axios.post(route('chat.messages.regenerate'), {
            conversation_id: props.conversation_id,
            user_message_id: userMessageId,
            assistant_message_id: assistantMessageId,
        });
    } catch (error) {
        console.error('Failed to reissue message:', error);
        return;
    }

    if (!response.data?.success) return;

    const createdAt = response.data.created_at;
    const newContent = overrideContent ?? response.data.user_message_content;

    const userIndex = messages.value.findIndex((m) => m.id === userMessageId);
    const assistantIndex = messages.value.findIndex((m) => m.id === assistantMessageId);
    if (userIndex === -1 || assistantIndex === -1) {
        console.error('Could not find messages to reissue');
        return;
    }

    if (assistantMessageId) messageSources.value.delete(assistantMessageId);

    messages.value[userIndex] = { role: 'user', content: newContent, id: 0 };
    messages.value[assistantIndex] = { role: 'assistant', content: '', model: model.id, id: 0 };

    userHasScrolledUp.value = true;
    isAutoScrollEnabled.value = false;

    await nextTick(() => {
        const messageElement = document.getElementById(`message-${userIndex}`);
        if (messageElement) {
            messageElement.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }

        submitChat({
            message: newContent,
            model,
            userIndex,
            assistantIndex,
            saveUserMessageImmediately: true,
            createdAt,
            autoScrollOnDone: false,
        });
    });
}

const handleMessageEdited = (userMessageId, assistantMessageId, newContent) => {
    const model = selectedModel.value || defaultModel.value;
    return reissue({ userMessageId, assistantMessageId, model, overrideContent: newContent });
};

const handleRegenerate = (userMessageId, assistantMessageId, model) => {
    return reissue({ userMessageId, assistantMessageId, model });
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
        :appearance="props.appearance"
        :removeSidebar="props.removeSidebar"
        :hideSidebarOnPageLoad="props.hideSidebarOnPageLoad"
        :enableThemeSwitcher="props.enableThemeSwitcher"
        :defaultTheme="props.defaultTheme"
        :enableChatExpand="props.enableChatExpand"
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
                            <p class="text-muted-foreground text-xs">Loading conversation</p>
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
                                />
                                <AssistantChatBubble
                                    v-else
                                    :message="message"
                                    :userMessageId="index > 0 && messages[index - 1]?.role === 'user' ? messages[index - 1].id : undefined"
                                    :models="models"
                                    :defaultModel="defaultModel"
                                    :sources="message.id ? messageSources.get(message.id) || [] : []"
                                    :conversationId="conversation_id"
                                    :hideFeedback="isMessageStreaming(message)"
                                    :isThinking="isMessageStreaming(message) && !message.content"
                                    :isStreaming="isMessageStreaming(message)"
                                    :isCallingExternal="isMessageStreaming(message) && hasActiveFunctionCalls"
                                    :avatar="props.appearance?.avatar"
                                    :isRTL="isMessageRTL(message)"
                                    @messageDeleted="messageDeleted"
                                    @feedbackProvided="feedbackProvided"
                                    @regenerate="handleRegenerate"
                                />
                            </div>
                        </div>
                    </div>

                    <!-- Add bottom padding to last message -->
                    <div class="pb-6"></div>
                </div>

                <!-- Auto-scroll paused indicator -->
                <Transition name="fade">
                    <div
                        v-if="isStreaming && userHasScrolledUp"
                        class="bg-foreground/90 text-background absolute bottom-28 left-1/2 -translate-x-1/2 rounded-full px-3 py-1.5 text-[11px] font-medium shadow-lg"
                    >
                        Scroll paused
                    </div>
                </Transition>

                <!-- Scroll to bottom button -->
                <div class="pointer-events-none absolute right-0 bottom-32 left-0 z-10 flex justify-center">
                    <Transition name="scale-fade">
                        <button
                            v-if="showScrollToBottom && messages.length > 0"
                            class="bg-foreground/90 text-background hover:bg-foreground pointer-events-auto flex h-9 w-9 cursor-pointer items-center justify-center rounded-full shadow-lg transition-colors"
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
                            :models="models"
                            :defaultModel="defaultModel"
                            :isLoadingModels="isLoadingModels"
                            :placeholder="appearance.composer_placeholder"
                            :disabled="!canSendMessages"
                            @messageAdded="messageAdded"
                            @modelSelected="updateModel"
                            @stopStreaming="stopStreaming"
                        />

                        <div v-if="props.appearance?.chat_footer_text" class="mt-3 text-center">
                            <p class="text-muted-foreground/60 text-[11px]">{{ props.appearance.chat_footer_text }}</p>
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
