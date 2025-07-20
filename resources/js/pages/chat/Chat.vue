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
});

const loadingMessages = ref(false);
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

// Add reactive refs for models
const models = ref([]);
const defaultModel = ref(null);
const isLoadingModels = ref(true);

// Add reactive ref for appearance
const appearance = ref({
    primary_color: null,
    chat_bubble_color: null,
    chat_text_color: null,
    welcome_message: null,
    chat_window_title: null,
    chat_footer_text: null,
    avatar: null
});

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
        defaultModel.value = response.data.default;
    } catch (error) {
        console.error('Failed to fetch models:', error);
    } finally {
        isLoadingModels.value = false;
    }
};

// Add function to fetch appearance
const fetchAppearance = async () => {
    try {
        const response = await axios.get(route('chat.appearance'));
        appearance.value = response.data;

        // Not applying dynamic colors - using default colors from app.css
    } catch (error) {
        console.error('Failed to fetch appearance:', error);
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
                // Instant scroll for streaming to avoid interrupting flow
                messagesContainer.value.scrollTop = messagesContainer.value.scrollHeight;
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
    loadingMessages.value = true;

    // Initialize from chatEventBus
    isChatExpanded.value = chatEventBus.isChatExpanded.value;

    // Add passive scroll listener for better performance
    if (messagesContainer.value) {
        messagesContainer.value.addEventListener('scroll', handleChatScroll, { passive: true });
    }

    // Wait for models to be loaded before loading messages
    await Promise.all([fetchModels(), fetchAppearance()]);

    axios
        .get(route('conversations.messages', { conversationId: props.conversation_id }))
        .then((response) => {
            messages.value = response.data;

            // Load sources from historical messages
            messages.value.forEach((message, index) => {
                if (message.role === 'assistant' && message.sources) {
                    messageSources.value.set(index, message.sources);
                }
            });

            loadingMessages.value = false;

            const modelId = getQueryParam('model') || defaultModel.value?.id;

            if (messages.value.length === 1) {
                startSseStreaming(messages.value[0].content, modelId, {}, true);
            }

            nextTick(() => {
                scrollToBottom();
            });
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

        // Use requestAnimationFrame for smooth performance
        if (scrollAnimationFrame.value) {
            cancelAnimationFrame(scrollAnimationFrame.value);
        }

        scrollAnimationFrame.value = requestAnimationFrame(() => {
            if (messagesContainer.value && isAutoScrollEnabled.value && !userHasScrolledUp.value) {
                // Direct assignment for immediate effect during streaming
                messagesContainer.value.scrollTop = messagesContainer.value.scrollHeight;
            }
            scrollAnimationFrame.value = null;
        });
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
            onMessage: (content) => {
                let messageIndex = messages.value.length - 1;

                if (streamingForMessageIndex.value !== null) {
                    messageIndex = streamingForMessageIndex.value;
                }

                messages.value[messageIndex].content += content;

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
            onMessageId: (messageId) => {
                let messageIndex = messages.value.length - 1;

                if (streamingForMessageIndex.value !== null) {
                    messageIndex = streamingForMessageIndex.value;
                }

                // Assign the message ID to the assistant message
                if (messages.value[messageIndex] && messages.value[messageIndex].role === 'assistant') {
                    messages.value[messageIndex].id = messageId;
                }
            },
            onDone: () => {
                streamingForMessageIndex.value = null;
                if (isNewConversation) {
                    chatEventBus.emitConversationCreated(props.conversation_id)
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
    <ChatLayout>
        <template #content>
            <div class="flex h-full flex-col overflow-hidden">
                <div
                    id="messages-container"
                    ref="messagesContainer"
                    :class="['flex-1 overflow-y-auto overflow-x-hidden', { 'flex flex-col justify-between': messages.length === 0 }]"
                >
                    <!-- Loading skeleton -->
                    <div v-if="loadingMessages" class="mx-auto max-w-3xl px-4 sm:px-8 py-6">
                        <div v-for="i in 3" :key="i" class="mb-6 animate-pulse">
                            <div class="flex gap-3" :class="i % 2 === 0 ? 'justify-end' : ''">
                                <div v-if="i % 2 !== 0" class="h-8 w-8 rounded-full bg-muted"></div>
                                <div class="max-w-[70%] space-y-2">
                                    <div class="h-4 bg-muted rounded w-3/4"></div>
                                    <div class="h-4 bg-muted rounded w-full"></div>
                                    <div class="h-4 bg-muted rounded w-2/3"></div>
                                </div>
                                <div v-if="i % 2 === 0" class="h-8 w-8 rounded-full bg-muted"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Empty state -->
                    <div v-else-if="messages.length === 0 && !loadingMessages" class="flex flex-col items-center justify-center h-full px-4">
                        <div class="text-center max-w-md">
                            <h3 class="text-lg font-semibold mb-2">{{ appearance.chat_window_title }}</h3>
                            <p class="text-muted-foreground text-sm">
                                {{ appearance.welcome_message }}
                            </p>
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
                                 message.role === 'assistant' ? 'bg-muted/30' : ''
                             ]">
                            <div :class="[
                                'mx-auto px-4 sm:px-8',
                                isChatExpanded ? 'max-w-6xl' : 'max-w-3xl',
                                index === 0 ? 'pt-6' : '',
                                index > 0 && messages[index - 1].role !== message.role ? 'pt-6' : 'pt-3',
                                'pb-3'
                            ]">
                                <UserChatBubble
                                    v-if="message.role === 'user'"
                                    :message="message"
                                    :conversationId="conversation_id"
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
                                    :avatar="appearance.avatar"
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
                        <Composer
                            :isThinking="isStreaming"
                            :models="models"
                            :defaultModel="defaultModel"
                            :isLoadingModels="isLoadingModels"
                            @messageAdded="messageAdded"
                            @modelSelected="updateModel"
                            @stopStreaming="stopStreaming"
                        />
                        <div v-if="appearance.chat_footer_text" class="text-center mt-2">
                            <p class="text-xs text-muted-foreground">{{ appearance.chat_footer_text }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </template>
    </ChatLayout>
</template>

<style>
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

/* Animations removed */

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
</style>
