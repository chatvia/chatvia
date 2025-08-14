<script setup>
import ChatLayout from '@/components/chat/ChatLayout.vue';
import Composer from '@/components/chat/Composer.vue';
import UserChatBubble from '@/components/chat/UserChatBubble.vue';
import AssistantChatBubble from '@/components/chat/AssistantChatBubble.vue';
import { useSSE } from '@/composables/useSSE';
import { router, usePage } from '@inertiajs/vue3';
import { useScroll } from '@vueuse/core';
import axios from 'axios';
import { computed, inject, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';
import { ChevronDown } from 'lucide-vue-next';
import { useColorTheme } from '@/composables/useColorTheme';

const chatEventBus = inject('chatEventBus');

const { isStreaming, startStream, stopStream } = useSSE();

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
            composer_placeholder: null
        })
    }
});

const loadingMessages = ref(true);
const messages = ref([]);
const defaultModelId = ref();
const isSidebarOpen = ref(false);
const isAutoScrollEnabled = ref(true);
const scrollTimeout = ref(null);
const messagesContainer = ref(null);
const { arrivedState } = useScroll(messagesContainer);
const selectedModel = ref({});
const streamingForMessageIndex = ref(null);
const isChatExpanded = ref(false);
const userHasScrolledUp = ref(false);
const lastScrollTop = ref(0);
const scrollAnimationFrame = ref(null);
const messageSources = ref(new Map()); // Store sources for each message
const scrollThrottleTimeout = ref(null); // For throttling scroll updates

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
watch(() => arrivedState.bottom, (isAtBottom) => {
    if (isAtBottom) {
        userHasScrolledUp.value = false;
        isAutoScrollEnabled.value = true;
    }
});

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
        const urlModel = urlModelId ? models.value.find((m) => m.id === urlModelId) : null;
        defaultModel.value = urlModel || response.data.default;
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

        // Map sources to message indices based on message IDs
        messages.value.forEach((message, index) => {
            if (message.role === 'assistant' && message.id && sourcesData[message.id]) {
                messageSources.value.set(index, sourcesData[message.id]);
            }
        });
    } catch (error) {
        console.error('Failed to load sources:', error);
    }
};



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
    messages.value.push({
        role: 'user',
        content: message,
        id: 0, // Default ID that will be overwritten by real ID
    });

    if (window.innerWidth < 768 && isSidebarOpen.value) {
        isSidebarOpen.value = false;
    }

    // Reset scroll state for new message
    userHasScrolledUp.value = false;
    isAutoScrollEnabled.value = true;

    await nextTick(() => {
        scrollToBottom();
        startSseStreaming(message, model.id);
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
                    behavior: 'smooth'
                });
            } else {
                // Use instant scroll to avoid scroll-linked effects
                messagesContainer.value.scrollTo({
                    top: messagesContainer.value.scrollHeight,
                    behavior: 'instant'
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
                startSseStreaming(messages.value[0].content, modelId, {}, true);
            }

            nextTick(() => {
                scrollToBottom();
            });

            // Load sources asynchronously to avoid blocking the page
            loadSourcesAsync();
        })
        .catch((error) => {
            router.visit(route('chat.home', { model: defaultModel.value?.id }));
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
                    behavior: 'instant'
                });
            }
            scrollThrottleTimeout.value = null;
        }, 16); // ~60fps throttling
    }
}

function startSseStreaming(message, modelId, updatedMessageInfo = {}, isNewConversation = false) {
    const newMessage = {
        role: 'assistant',
        content: '',
        model: modelId,
        id: 0, // Default ID that will be overwritten by real ID
    };
    messages.value.push(newMessage);

    const stream = startStream(
        route('chat.stream', { conversationId: props.conversation_id }),
        JSON.stringify({
            model: modelId,
            message: message,
            updated_message_info: updatedMessageInfo,
        }),
        {
            onMessage: (content, messageId) => {
                let messageIndex = messages.value.length - 1;

                if (streamingForMessageIndex.value !== null) {
                    messageIndex = streamingForMessageIndex.value;
                }

                messages.value[messageIndex].content += content;

                // Set the message ID if provided (only on first chunk)
                if (messageId && messages.value[messageIndex] && messages.value[messageIndex].role === 'assistant') {
                    messages.value[messageIndex].id = messageId;
                }

                scrollWhileStreaming();
            },
            onSources: (sources) => {
                let messageIndex = messages.value.length - 1;

                if (streamingForMessageIndex.value !== null) {
                    messageIndex = streamingForMessageIndex.value;
                }

                // Store sources for this message using message index as key
                messageSources.value.set(messageIndex, sources);
            },
            onLanguage: (language) => {
                // Store the detected language in the streaming message
                let messageIndex = messages.value.length - 1;
                if (streamingForMessageIndex.value !== null) {
                    messageIndex = streamingForMessageIndex.value;
                }
                
                if (messages.value[messageIndex] && messages.value[messageIndex].role === 'assistant') {
                    messages.value[messageIndex].language = language;
                }
            },
            onDone: () => {
                streamingForMessageIndex.value = null;
                if (isNewConversation) {
                    chatEventBus.emitConversationCreated(props.conversation_id)
                }

                // Remove 'new' query parameter after streaming is done
                if (getQueryParam('new') === '1') {
                    const url = new URL(window.location);
                    url.searchParams.delete('new');
                    window.history.replaceState({}, '', url);
                }

                // Final scroll to ensure we're at the bottom if auto-scroll is enabled
                if (isAutoScrollEnabled.value) {
                    nextTick(() => {
                        scrollToBottom();
                    });
                }
            },
            onError: (error) => {},
        },
    );

    stream();
}

const stopStreaming = () => {
    stopStream();
};

const messageUpdated = (messageId, messageContent) => {
    const currentMessage = messages.value.find((m) => m.id === messageId);
    const currentMessageIndex = messages.value.findIndex((m) => m.id === messageId);

    if (currentMessage) {
        if (currentMessage.content !== messageContent) {
            currentMessage.content = messageContent;

            messages.value[currentMessageIndex + 1].content = '';
            streamingForMessageIndex.value = currentMessageIndex + 1;

            // Clear existing sources for the assistant message being regenerated
            messageSources.value.delete(currentMessageIndex + 1);

            // Reset scroll state for edited message
            userHasScrolledUp.value = false;
            isAutoScrollEnabled.value = true;

            // updatedMessageInfo for edited message
            startSseStreaming(currentMessage.content, selectedModel.value.id, {
                userMessageId: currentMessage.id,
                assistantMessageId: messages.value[currentMessageIndex + 1].id,
            });
        }
    }
};

const messageDeleted = (messageId) => {
    const messageIndex = messages.value.findIndex(m => m.id === messageId);

    if (messageIndex === -1) return;

    const message = messages.value[messageIndex];

    if (message.role === 'user') {
        // If deleting a user message, also remove the subsequent assistant message
        const assistantMessageIndex = messageIndex + 1;
        if (assistantMessageIndex < messages.value.length &&
            messages.value[assistantMessageIndex].role === 'assistant') {
            // Remove both user and assistant messages
            messages.value.splice(messageIndex, 2);
            // Clear sources for the assistant message
            messageSources.value.delete(assistantMessageIndex);
            // Update source indices for remaining messages
            const newSources = new Map();
            messageSources.value.forEach((sources, index) => {
                if (index > assistantMessageIndex) {
                    newSources.set(index - 2, sources);
                } else if (index < messageIndex) {
                    newSources.set(index, sources);
                }
            });
            messageSources.value = newSources;
        } else {
            // Remove only the user message
            messages.value.splice(messageIndex, 1);
            // Update source indices
            const newSources = new Map();
            messageSources.value.forEach((sources, index) => {
                if (index > messageIndex) {
                    newSources.set(index - 1, sources);
                } else if (index < messageIndex) {
                    newSources.set(index, sources);
                }
            });
            messageSources.value = newSources;
        }
    } else {
        // If deleting an assistant message, also remove the previous user message
        const userMessageIndex = messageIndex - 1;
        if (userMessageIndex >= 0 &&
            messages.value[userMessageIndex].role === 'user') {
            // Remove both user and assistant messages
            messages.value.splice(userMessageIndex, 2);
            // Clear sources for the assistant message
            messageSources.value.delete(messageIndex);
            // Update source indices for remaining messages
            const newSources = new Map();
            messageSources.value.forEach((sources, index) => {
                if (index > messageIndex) {
                    newSources.set(index - 2, sources);
                } else if (index < userMessageIndex) {
                    newSources.set(index, sources);
                }
            });
            messageSources.value = newSources;
        } else {
            // Remove only the assistant message if no previous user message
            messages.value.splice(messageIndex, 1);
            // Clear sources for this message
            messageSources.value.delete(messageIndex);
            // Update source indices for remaining messages
            const newSources = new Map();
            messageSources.value.forEach((sources, index) => {
                if (index > messageIndex) {
                    newSources.set(index - 1, sources);
                } else if (index < messageIndex) {
                    newSources.set(index, sources);
                }
            });
            messageSources.value = newSources;
        }
    }

    // Check if all messages are deleted, redirect to home
    if (messages.value.length === 0) {
        router.visit(route('chat.home'));
        return;
    }

    console.log('Message deleted from UI:', messageId);
};

const feedbackProvided = (messageId, type) => {
    // TODO: Implement feedback handling
    console.log('Feedback:', messageId, type);
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
    <ChatLayout :appearance="props.appearance">
        <template #content>
            <div class="flex h-full flex-col overflow-hidden">
                <div
                    id="messages-container"
                    ref="messagesContainer"
                    :class="[
                        'flex-1 overflow-y-auto overflow-x-hidden',
                        { 'flex flex-col justify-between': messages.length === 0 }
                    ]"
                >
                    <!-- Loading popup -->
                    <div v-if="loadingMessages" class="flex items-center justify-center h-full">
                        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg border border-gray-200 dark:border-gray-700 p-6 max-w-sm mx-4">
                            <div class="flex items-center space-x-3">
                                <div class="animate-spin rounded-full h-6 w-6 border-b-2 border-blue-500"></div>
                                <div>
                                    <h3 class="text-sm font-medium text-gray-900 dark:text-gray-100">Loading messages...</h3>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">Please wait while we fetch your conversation</p>
                                </div>
                            </div>
                        </div>
                    </div>



                    <!-- Messages -->
                    <div
                        v-else
                        class="min-h-full"
                    >
                        <div v-for="(message, index) in messages"
                             :id="`message-${index}`"
                             :key="`message-${message.id || index}`"
                             :class="[
                                 'w-full',
                                 message.role === 'assistant' ? '' : ''
                             ]">
                            <div :class="[
                                'mx-auto px-4 sm:px-8 transition-all duration-300 ease-in-out',
                                isChatExpanded ? 'max-w-6xl' : 'max-w-3xl',
                                index === 0 ? 'pt-6' : '',
                                index > 0 && messages[index - 1].role !== message.role ? 'pt-6' : 'pt-3',
                                'pb-3'
                            ]">
                                <UserChatBubble
                                    v-if="message.role === 'user'"
                                    :message="message"
                                    :conversationId="conversation_id"
                                    :isRTL="isMessageRTL(message)"
                                    @messageUpdated="messageUpdated"
                                    @messageDeleted="messageDeleted"
                                />
                                <AssistantChatBubble
                                    v-else
                                    :message="message"
                                    :models="models"
                                    :defaultModel="defaultModel"
                                    :sources="messageSources.get(index) || []"
                                    :conversationId="conversation_id"
                                    :hideFeedback="index === messages.length - 1 && isStreaming"
                                    :isThinking="index === messages.length - 1 && isStreaming && !message.content"
                                    :avatar="props.appearance?.avatar"
                                    :isRTL="isMessageRTL(message)"
                                    @messageDeleted="messageDeleted"
                                    @feedbackProvided="feedbackProvided"
                                />
                            </div>
                        </div>
                    </div>

                    <!-- Add bottom padding to last message -->
                    <div class="pb-6"></div>
                </div>

                <!-- Auto-scroll paused indicator -->
                <div v-if="isStreaming && userHasScrolledUp" class="absolute bottom-24 left-1/2 transform -translate-x-1/2 bg-background/90 backdrop-blur-sm px-3 py-1.5 rounded-full border border-border shadow-sm text-xs text-muted-foreground">
                    Auto-scroll paused
                </div>

                <div v-if="showScrollToBottom && messages.length > 0" class="relative flex justify-center -mt-20 mb-4 pointer-events-none">
                    <button
                        class="pointer-events-auto flex h-8 w-8 cursor-pointer items-center justify-center rounded-full bg-card border border-border shadow-md"
                        @click="handleScrollToBottomClick"
                    >
                        <ChevronDown class="h-5 w-5" />
                    </button>
                </div>

                <div class="flex-shrink-0 border-t bg-background/95 backdrop-blur-sm">
                    <div class="mx-auto max-w-3xl px-4 py-4 sm:px-8">
                        <!-- Show composer if user can send messages -->
                        <Composer
                            v-if="canSendMessages"
                            :isThinking="isStreaming"
                            :models="models"
                            :defaultModel="defaultModel"
                            :isLoadingModels="isLoadingModels"
                            :placeholder="appearance.composer_placeholder"
                            @messageAdded="messageAdded"
                            @modelSelected="updateModel"
                            @stopStreaming="stopStreaming"
                        />

                        <!-- Show limit message if user cannot send messages -->
                        <div v-else class="flex items-center justify-center p-4 bg-orange-50 dark:bg-orange-900/20 border border-orange-200 dark:border-orange-800 rounded-lg">
                            <div class="text-center">
                                <p class="text-sm font-medium text-orange-800 dark:text-orange-200">
                                    You've reached your message limit
                                </p>
                                <p class="text-xs text-orange-600 dark:text-orange-300 mt-1">
                                    Please upgrade your plan to continue chatting
                                </p>
                            </div>
                        </div>

                        <div v-if="props.appearance?.chat_footer_text" class="text-center mt-2">
                            <p class="text-xs text-muted-foreground">{{ props.appearance.chat_footer_text }}</p>
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

/* Message container expansion animation */
.mx-auto.px-4.sm\\:px-8 {
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

/* Message list animations removed */

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
#messages-container [id^="message-"]:last-child {
    /* Isolate the last message to prevent reflows affecting other messages */
    contain: layout style;
}

/* Better focus states */
:focus-visible {
    outline: 2px solid hsl(var(--primary));
    outline-offset: 2px;
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
[data-rtl="true"] {
    font-family: 'Noto Sans Arabic', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
}

[data-rtl="true"] .prose {
    font-family: 'Noto Sans Arabic', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
}

[data-rtl="true"] .prose p,
[data-rtl="true"] .prose h1,
[data-rtl="true"] .prose h2,
[data-rtl="true"] .prose h3,
[data-rtl="true"] .prose h4,
[data-rtl="true"] .prose h5,
[data-rtl="true"] .prose h6,
[data-rtl="true"] .prose li,
[data-rtl="true"] .prose blockquote,
[data-rtl="true"] .prose td,
[data-rtl="true"] .prose th {
    font-family: 'Noto Sans Arabic', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
}
</style>
