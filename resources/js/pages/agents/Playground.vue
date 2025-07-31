<script setup lang="ts">
import { ref, computed, nextTick, onMounted, onUnmounted } from 'vue';
import axios from 'axios';
import { useSSE } from '@/composables/useSSE';
import { Marked } from 'marked';
import markedAlert from 'marked-alert';
import markedCodePreview from 'marked-code-preview';
import { markedHighlight } from 'marked-highlight';
import markedKatex from 'marked-katex-extension';
import hljs from 'highlight.js';
import AgentLayout from '@/layouts/AgentLayout.vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import { Separator } from '@/components/ui/separator';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import {
    Bot,
    Brain,
    User,
    Send,
    Square,
    Loader2,
    MessageSquare,
    Clock,
    Zap,
    BarChart3,
    TestTube,
    ChevronDown,
    Info
} from 'lucide-vue-next';

interface Props {
    agent: {
        id: number;
        name: string;
        type: string;
        is_active: boolean;
        is_public: boolean;
        url: string;
    };
}

interface AIModel {
    id: string;
    name: string;
    context_window: number;
    icon: string;
    description: string;
    features?: string[];
    location: string;
    reasoning: boolean;
    user_allowed_to_use: boolean;
    is_selected: boolean;
}

interface Message {
    id: string;
    content: string;
    role: 'user' | 'assistant';
    timestamp: Date;
    responseTime?: number;
}

interface PlaygroundStats {
    totalMessages: number;
    averageResponseTime: number;
    fastestResponse: number;
    slowestResponse: number;
    totalPlaygroundTime: number;
    modelStats: {
        [modelId: string]: {
            name: string;
            totalMessages: number;
            averageResponseTime: number;
            fastestResponse: number;
            slowestResponse: number;
        };
    };
    bestPerformingModel?: {
        id: string;
        name: string;
        averageResponseTime: number;
    };
}

const props = defineProps<Props>();

// SSE composable
const { isStreaming, startStream, stopStream } = useSSE();

// Chat state
const messages = ref<Message[]>([]);
const currentMessage = ref('');
const isLoading = ref(false);
const messagesContainer = ref<HTMLElement>();
const conversationId = ref<string>('');

// Models state
const availableModels = ref<AIModel[]>([]);
const selectedModelId = ref<string>('');
const isLoadingModels = ref(true);

// Stats state
const stats = ref<PlaygroundStats>({
    totalMessages: 0,
    averageResponseTime: 0,
    fastestResponse: 0,
    slowestResponse: 0,
    totalPlaygroundTime: 0,
    modelStats: {}
});

const playgroundStartTime = ref<Date | null>(null);

// Scroll state management
const isAutoScrollEnabled = ref(true);
const userHasScrolledUp = ref(false);
const scrollAnimationFrame = ref<number | null>(null);
const streamingMessageIndex = ref<number | null>(null);

// Setup markdown parser
const marked = new Marked(
    markedHighlight({
        emptyLangClass: 'hljs',
        langPrefix: 'hljs language-',
        highlight(code, lang) {
            const language = hljs.getLanguage(lang) ? lang : 'plaintext';
            return hljs.highlight(code, { language }).value;
        },
    }),
);

marked.use({ gfm: true })
    .use(markedKatex())
    .use(markedCodePreview())
    .use(markedAlert());

function formatMessage(content: string): string {
    const result = marked.parse(content);
    return typeof result === 'string' ? result : '';
}

// Create conversation for playground
const createConversation = async () => {
    try {
        const response = await axios.post(route('agents.playground.conversations.create', { agent: props.agent.id }));
        conversationId.value = response.data.id;
        console.log('Playground conversation created:', conversationId.value);
    } catch (error) {
        console.error('Failed to create playground conversation:', error);
    }
};

// Fetch available models
const fetchModels = async () => {
    try {
        isLoadingModels.value = true;
        const response = await axios.get(route('agents.models.list', { agent: props.agent.id }));
        availableModels.value = response.data.models.filter((model: AIModel) => model.user_allowed_to_use);

        // Auto-select the first available model if none selected
        if (availableModels.value.length > 0 && !selectedModelId.value) {
            selectedModelId.value = availableModels.value[0].id;
        }
    } catch (error) {
        console.error('Failed to fetch models:', error);
    } finally {
        isLoadingModels.value = false;
    }
};

// Simplified scroll handling
const scrollToBottom = (smooth = false) => {
    if (!messagesContainer.value) return;

    if (scrollAnimationFrame.value) {
        cancelAnimationFrame(scrollAnimationFrame.value);
    }

    scrollAnimationFrame.value = requestAnimationFrame(() => {
        if (messagesContainer.value) {
            if (smooth) {
                messagesContainer.value.scrollTo({
                    top: messagesContainer.value.scrollHeight,
                    behavior: 'smooth'
                });
            } else {
                messagesContainer.value.scrollTop = messagesContainer.value.scrollHeight;
            }
        }
        scrollAnimationFrame.value = null;
    });
};

const scrollWhileStreaming = () => {
    if (isAutoScrollEnabled.value && !userHasScrolledUp.value && isStreaming.value && messagesContainer.value) {
        if (scrollAnimationFrame.value) {
            cancelAnimationFrame(scrollAnimationFrame.value);
        }

        scrollAnimationFrame.value = requestAnimationFrame(() => {
            if (messagesContainer.value && isAutoScrollEnabled.value && !userHasScrolledUp.value) {
                messagesContainer.value.scrollTop = messagesContainer.value.scrollHeight;
            }
            scrollAnimationFrame.value = null;
        });
    }
};

const handleScroll = () => {
    if (!messagesContainer.value) return;

    const { scrollTop, scrollHeight, clientHeight } = messagesContainer.value;
    const scrollBottom = scrollHeight - scrollTop - clientHeight;

    // Check if user scrolled away from bottom
    if (scrollBottom > 50) {
        userHasScrolledUp.value = true;
        isAutoScrollEnabled.value = false;
    } else if (scrollBottom < 20) {
        userHasScrolledUp.value = false;
        isAutoScrollEnabled.value = true;
    }
};

const showScrollToBottom = computed(() => {
    return userHasScrolledUp.value && messages.value.length > 0;
});

const handleScrollToBottomClick = () => {
    userHasScrolledUp.value = false;
    isAutoScrollEnabled.value = true;
    scrollToBottom(true);
};

// Initialize on mount
onMounted(async () => {
    await Promise.all([
        createConversation(),
        fetchModels()
    ]);

    // Set up scroll listener
    nextTick(() => {
        if (messagesContainer.value) {
            messagesContainer.value.addEventListener('scroll', handleScroll, { passive: true });
        }
    });
});

onUnmounted(() => {
    if (scrollAnimationFrame.value) {
        cancelAnimationFrame(scrollAnimationFrame.value);
    }

    if (messagesContainer.value) {
        messagesContainer.value.removeEventListener('scroll', handleScroll);
    }
});

// Computed properties
const formattedStats = computed(() => ({
    totalMessages: stats.value.totalMessages,
    averageResponseTime: stats.value.averageResponseTime > 0 ? `${stats.value.averageResponseTime.toFixed(2)}s` : '0s',
    fastestResponse: stats.value.fastestResponse > 0 ? `${stats.value.fastestResponse.toFixed(2)}s` : '0s',
    slowestResponse: stats.value.slowestResponse > 0 ? `${stats.value.slowestResponse.toFixed(2)}s` : '0s',
    totalPlaygroundTime: stats.value.totalPlaygroundTime > 0 ? `${Math.floor(stats.value.totalPlaygroundTime / 60)}m ${Math.floor(stats.value.totalPlaygroundTime % 60)}s` : '0s'
}));

const canSendMessage = computed(() => {
    return currentMessage.value.trim().length > 0 && !isStreaming.value && conversationId.value && selectedModelId.value;
});

const selectedModel = computed(() => {
    return availableModels.value.find(model => model.id === selectedModelId.value);
});

const sortedModelStats = computed(() => {
    return Object.entries(stats.value.modelStats)
        .sort(([, a], [, b]) => a.averageResponseTime - b.averageResponseTime)
        .map(([modelId, modelStat]) => ({ modelId, ...modelStat }));
});

// Methods
const sendMessage = async () => {
    if (!canSendMessage.value || !conversationId.value || !selectedModelId.value) return;

    const messageContent = currentMessage.value.trim();
    currentMessage.value = '';

    // Reset scroll state for new message
    userHasScrolledUp.value = false;
    isAutoScrollEnabled.value = true;

    // Add user message
    const userMessage: Message = {
        id: `user-${Date.now()}`,
        content: messageContent,
        role: 'user',
        timestamp: new Date()
    };

    messages.value.push(userMessage);
    await nextTick();
    scrollToBottom();

    // Start timing for response
    const requestStartTime = Date.now();
    let firstChunkReceived = false;
    let responseTime = 0;

    if (!playgroundStartTime.value) {
        playgroundStartTime.value = new Date();
    }

    // Add empty assistant message for streaming
    const assistantMessage: Message = {
        id: `assistant-${Date.now()}`,
        content: '',
        role: 'assistant',
        timestamp: new Date(),
        responseTime: 0
    };

    messages.value.push(assistantMessage);
    streamingMessageIndex.value = messages.value.length - 1;
    await nextTick();
    scrollToBottom();

    // Start SSE streaming
    const stream = startStream(
        route('agents.playground.stream', { agent: props.agent.id, conversationId: conversationId.value }),
        JSON.stringify({
            model: selectedModelId.value,
            message: messageContent,
            playground: true,
            agent: props.agent.id
        }),
        {
            onMessage: (content) => {
                // Calculate response time on first chunk (time to first response)
                if (!firstChunkReceived) {
                    firstChunkReceived = true;
                    responseTime = (Date.now() - requestStartTime) / 1000;

                    if (streamingMessageIndex.value !== null && streamingMessageIndex.value < messages.value.length) {
                        messages.value[streamingMessageIndex.value].responseTime = responseTime;
                    }

                    // Update stats with time to first response
                    updateStats(responseTime);
                }

                if (streamingMessageIndex.value !== null && streamingMessageIndex.value < messages.value.length) {
                    messages.value[streamingMessageIndex.value].content += content;
                    scrollWhileStreaming();
                }
            },
            onDone: () => {
                // If no chunks were received, calculate response time here (error case)
                if (!firstChunkReceived) {
                    responseTime = (Date.now() - requestStartTime) / 1000;
                    if (streamingMessageIndex.value !== null && streamingMessageIndex.value < messages.value.length) {
                        messages.value[streamingMessageIndex.value].responseTime = responseTime;
                    }
                    updateStats(responseTime);
                }

                streamingMessageIndex.value = null;

                if (isAutoScrollEnabled.value) {
                    nextTick(() => {
                        scrollToBottom();
                    });
                }
            },
            onError: (error) => {
                console.error('SSE streaming error:', error);

                // Calculate response time for error case if not already done
                if (!firstChunkReceived) {
                    responseTime = (Date.now() - requestStartTime) / 1000;
                    if (streamingMessageIndex.value !== null && streamingMessageIndex.value < messages.value.length) {
                        messages.value[streamingMessageIndex.value].responseTime = responseTime;
                    }
                    updateStats(responseTime);
                }

                if (streamingMessageIndex.value !== null && streamingMessageIndex.value < messages.value.length) {
                    messages.value[streamingMessageIndex.value].content = 'Sorry, I encountered an error while processing your message. Please try again.';
                }

                streamingMessageIndex.value = null;
                scrollToBottom();
            },
            onMessageId: (messageId) => {
                if (streamingMessageIndex.value !== null && streamingMessageIndex.value < messages.value.length) {
                    messages.value[streamingMessageIndex.value].id = messageId;
                }
            }
        }
    );

    stream();
};

const stopStreaming = () => {
    stopStream();
    streamingMessageIndex.value = null;
};

const updateStats = (responseTime: number) => {
    stats.value.totalMessages++;

    if (stats.value.fastestResponse === 0 || responseTime < stats.value.fastestResponse) {
        stats.value.fastestResponse = responseTime;
    }

    if (responseTime > stats.value.slowestResponse) {
        stats.value.slowestResponse = responseTime;
    }

    // Calculate average response time
    const totalResponseTime = (stats.value.averageResponseTime * (stats.value.totalMessages - 1)) + responseTime;
    stats.value.averageResponseTime = totalResponseTime / stats.value.totalMessages;

    // Calculate total playground time
    if (playgroundStartTime.value) {
        stats.value.totalPlaygroundTime = (Date.now() - playgroundStartTime.value.getTime()) / 1000;
    }

    // Update model stats
    if (selectedModel.value) {
        const modelId = selectedModel.value.id;
        if (!stats.value.modelStats[modelId]) {
            stats.value.modelStats[modelId] = {
                name: selectedModel.value.name,
                totalMessages: 0,
                averageResponseTime: 0,
                fastestResponse: 0,
                slowestResponse: 0
            };
        }

        stats.value.modelStats[modelId].totalMessages++;

        if (stats.value.modelStats[modelId].fastestResponse === 0 || responseTime < stats.value.modelStats[modelId].fastestResponse) {
            stats.value.modelStats[modelId].fastestResponse = responseTime;
        }

        if (responseTime > stats.value.modelStats[modelId].slowestResponse) {
            stats.value.modelStats[modelId].slowestResponse = responseTime;
        }

        // Calculate average response time for the model
        const totalModelResponseTime = (stats.value.modelStats[modelId].averageResponseTime * (stats.value.modelStats[modelId].totalMessages - 1)) + responseTime;
        stats.value.modelStats[modelId].averageResponseTime = totalModelResponseTime / stats.value.modelStats[modelId].totalMessages;

        // Update best performing model
        if (!stats.value.bestPerformingModel || responseTime < stats.value.bestPerformingModel.averageResponseTime) {
            stats.value.bestPerformingModel = {
                id: modelId,
                name: selectedModel.value.name,
                averageResponseTime: responseTime
            };
        }
    }
};

const clearChat = async () => {
    // Stop any ongoing streaming
    stopStream();
    streamingMessageIndex.value = null;

    // Clear chat data
    messages.value = [];
    stats.value = {
        totalMessages: 0,
        averageResponseTime: 0,
        fastestResponse: 0,
        slowestResponse: 0,
        totalPlaygroundTime: 0,
        modelStats: {}
    };
    playgroundStartTime.value = null;

    // Reset scroll state
    userHasScrolledUp.value = false;
    isAutoScrollEnabled.value = true;

    // Create a new conversation for fresh start
    await createConversation();
};

const formatTime = (date: Date) => {
    return date.toLocaleTimeString('en-US', {
        hour12: false,
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit'
    });
};
</script>

<template>
    <AgentLayout :agent="agent" title="Agent Playground">
        <div class="space-y-6">
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Chat Interface -->
                <div class="lg:col-span-2 relative">
                    <Card class="h-[700px] flex flex-col">
                        <CardHeader class="flex-shrink-0">
                            <div class="flex items-center justify-between">
                                <CardTitle class="flex items-center gap-2">
                                    <MessageSquare class="h-5 w-5" />
                                    Playground
                                </CardTitle>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    @click="clearChat"
                                    :disabled="messages.length === 0"
                                >
                                    Clear Chat
                                </Button>
                            </div>
                        </CardHeader>

                        <CardContent class="flex-1 flex flex-col p-0">
                            <!-- Messages Area -->
                            <div class="flex-1 overflow-hidden">
                                <div
                                    ref="messagesContainer"
                                    class="h-full overflow-y-auto px-6 py-4"
                                    style="max-height: calc(500px);"
                                >
                                    <!-- Welcome message -->
                                    <div v-if="messages.length === 0" class="text-center py-8">
                                        <Bot class="h-12 w-12 mx-auto text-muted-foreground mb-4" />
                                        <h3 class="text-lg font-medium text-muted-foreground mb-2">Start Playing with {{ agent.name }}</h3>
                                        <p class="text-sm text-muted-foreground">Send a message to begin experimenting with the agent's responses and performance.</p>

                                        <!-- Playground Note -->

                                    </div>

                                    <!-- Messages -->
                                    <div v-for="(message, index) in messages" :key="message.id" class="flex gap-3 mb-4">
                                        <div class="flex-shrink-0">
                                            <div
                                                class="w-8 h-8 rounded-full flex items-center justify-center"
                                                :class="message.role === 'user' ? 'bg-primary text-primary-foreground' : 'bg-muted'"
                                            >
                                                <User v-if="message.role === 'user'" class="h-4 w-4" />
                                                <Bot v-else class="h-4 w-4" />
                                            </div>
                                        </div>
                                        <div class="flex-1 space-y-1">
                                            <div class="flex items-center gap-2">
                                                <span class="text-sm font-medium">
                                                    {{ message.role === 'user' ? 'You' : agent.name }}
                                                </span>
                                                <span class="text-xs text-muted-foreground">
                                                    {{ formatTime(message.timestamp) }}
                                                </span>
                                                <Badge v-if="message.responseTime" variant="secondary" class="text-xs">
                                                    {{ message.responseTime.toFixed(2) }}s
                                                </Badge>

                                            </div>
                                            <div class="text-sm text-foreground bg-muted rounded-lg p-3 prose prose-sm dark:prose-invert max-w-none">
                                                <div v-if="message.content" v-html="formatMessage(message.content)"></div>
                                                <div v-else-if="index === streamingMessageIndex && isStreaming" class="flex items-center gap-2 text-muted-foreground">
                                                    <Loader2 class="h-4 w-4 animate-spin" />
                                                    Generating response...
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Padding at the bottom for smooth scrolling -->
                                    <div class="pb-4"></div>
                                </div>
                            </div>

                            <!-- Input Area -->
                            <div class="flex-shrink-0 px-6 py-4 border-t space-y-3">
                                <!-- Model Selection -->
                                <div class="flex items-center gap-2">
                                    <Select v-model="selectedModelId" :disabled="isLoadingModels">
                                        <SelectTrigger class="w-64">
                                            <SelectValue placeholder="Select a model..." />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem
                                                v-for="model in availableModels"
                                                :key="model.id"
                                                :value="model.id"
                                            >
                                                <div class="flex items-center gap-2">
                                                    <span class="font-medium">{{ model.name }}</span>
                                                    <Badge variant="outline" class="text-xs">
                                                        {{ model.location }}
                                                    </Badge>
                                                </div>
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>

                                    <div v-if="isLoadingModels" class="flex items-center gap-2 text-sm text-muted-foreground">
                                        <Loader2 class="h-4 w-4 animate-spin" />
                                        Loading...
                                    </div>
                                </div>

                                <!-- Message Input -->
                                <form @submit.prevent="sendMessage" class="flex gap-2">
                                    <Input
                                        v-model="currentMessage"
                                        placeholder="Type your playground message..."
                                        :disabled="isStreaming"
                                        class="flex-1"
                                        @keydown.enter="sendMessage"
                                    />
                                    <Button
                                        v-if="isStreaming"
                                        @click="stopStreaming"
                                        size="sm"
                                        variant="outline"
                                        class="gap-1"
                                    >
                                        <Square class="h-4 w-4" />
                                        Stop
                                    </Button>
                                    <Button
                                        v-else
                                        type="submit"
                                        :disabled="!canSendMessage"
                                        size="sm"
                                    >
                                        <Send class="h-4 w-4" />
                                    </Button>
                                </form>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <!-- Stats Panel -->
                <div class="space-y-6">
                    <!-- Performance Stats -->
                    <Card>
                        <CardHeader>
                            <CardTitle class="flex items-center gap-2">
                                <BarChart3 class="h-5 w-5" />
                                Performance Stats
                            </CardTitle>
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <div class="grid grid-cols-1 gap-4">
                                <div class="flex items-center justify-between">
                                    <span class="text-sm text-muted-foreground">Total Messages</span>
                                    <Badge variant="secondary">{{ formattedStats.totalMessages }}</Badge>
                                </div>

                                <Separator />

                                <div class="flex items-center justify-between">
                                    <span class="text-sm text-muted-foreground">Avg Response Time</span>
                                    <Badge variant="outline" class="gap-1">
                                        <Clock class="h-3 w-3" />
                                        {{ formattedStats.averageResponseTime }}
                                    </Badge>
                                </div>

                                <div class="flex items-center justify-between">
                                    <span class="text-sm text-muted-foreground">Fastest Response</span>
                                    <Badge variant="outline" class="gap-1">
                                        <Zap class="h-3 w-3" />
                                        {{ formattedStats.fastestResponse }}
                                    </Badge>
                                </div>

                                <div class="flex items-center justify-between">
                                    <span class="text-sm text-muted-foreground">Slowest Response</span>
                                    <Badge variant="outline">{{ formattedStats.slowestResponse }}</Badge>
                                </div>

                                <Separator />

                                <div class="flex items-center justify-between">
                                    <span class="text-sm text-muted-foreground">Total Playground Time</span>
                                    <Badge variant="secondary">{{ formattedStats.totalPlaygroundTime }}</Badge>
                                </div>

                                <Separator />

                                <div v-if="stats.bestPerformingModel" class="flex items-center justify-between">
                                    <span class="text-sm text-muted-foreground">Fastest</span>
                                    <Badge variant="outline" class="gap-1">
                                        <TestTube class="h-3 w-3" />
                                        {{ stats.bestPerformingModel.name }} ({{ stats.bestPerformingModel.averageResponseTime.toFixed(2) }}s)
                                    </Badge>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Model Performance Breakdown -->
                    <Card v-if="Object.keys(stats.modelStats).length > 0">
                        <CardHeader>
                            <CardTitle class="flex items-center gap-2">
                                <Brain class="h-5 w-5" />
                                Model Performance
                            </CardTitle>
                        </CardHeader>
                        <CardContent class="space-y-3">
                            <div v-for="(modelStat, index) in sortedModelStats" :key="modelStat.modelId" class="space-y-2">
                                <div class="flex items-center justify-between">
                                    <span class="text-sm font-medium">{{ modelStat.name }}</span>
                                    <Badge variant="secondary" class="text-xs">{{ modelStat.totalMessages }} messages</Badge>
                                </div>
                                <div class="grid grid-cols-2 gap-2 text-xs">
                                    <div class="flex items-center justify-between">
                                        <span class="text-muted-foreground">Avg:</span>
                                        <span>{{ modelStat.averageResponseTime.toFixed(2) }}s</span>
                                    </div>
                                    <div class="flex items-center justify-between">
                                        <span class="text-muted-foreground">Latency:</span>
                                        <span>{{ modelStat.fastestResponse.toFixed(2) }}s</span>
                                    </div>
                                </div>
                                <div v-if="modelStat.modelId === stats.bestPerformingModel?.id" class="text-xs text-green-600 dark:text-green-400 font-medium">
                                    ⭐ Fastest
                                </div>
                                <Separator v-if="sortedModelStats.length > 1 && index < sortedModelStats.length - 1" />
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Agent Info -->
                    <Card>
                        <CardHeader>
                            <CardTitle class="flex items-center gap-2">
                                <Bot class="h-5 w-5" />
                                Agent Info
                            </CardTitle>
                        </CardHeader>
                        <CardContent class="space-y-3">
                            <div class="flex items-center justify-between">
                                <span class="text-sm text-muted-foreground">Name</span>
                                <span class="text-sm font-medium">{{ agent.name }}</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-sm text-muted-foreground">Type</span>
                                <Badge variant="outline" class="capitalize">{{ agent.type }}</Badge>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-sm text-muted-foreground">Selected Model</span>
                                <Badge v-if="selectedModel" variant="default" class="text-xs">
                                    {{ selectedModel.name }}
                                </Badge>
                                <span v-else class="text-xs text-muted-foreground">None</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-sm text-muted-foreground">Status</span>
                                <Badge :variant="agent.is_active ? 'default' : 'secondary'">
                                    {{ agent.is_active ? 'Active' : 'Inactive' }}
                                </Badge>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-sm text-muted-foreground">Visibility</span>
                                <Badge :variant="agent.is_public ? 'default' : 'outline'">
                                    {{ agent.is_public ? 'Public' : 'Private' }}
                                </Badge>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Quick Actions -->
                    <Card>
                        <CardHeader>
                            <CardTitle>Quick Actions</CardTitle>
                        </CardHeader>
                        <CardContent class="space-y-2">
                            <Button
                                variant="outline"
                                class="w-full justify-start gap-2"
                                as="a"
                                :href="agent.url + '/chat'"
                                target="_blank"
                            >
                                <Bot class="h-4 w-4" />
                                Open Full Agent
                            </Button>
                            <Button
                                variant="outline"
                                class="w-full justify-start gap-2"
                                @click="clearChat"
                                :disabled="messages.length === 0"
                            >
                                <MessageSquare class="h-4 w-4" />
                                Reset Playground Session
                            </Button>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </div>
    </AgentLayout>
</template>

<style scoped>
/* Prose customizations for markdown content */
:deep(.prose) {
  font-size: 0.875rem;
  line-height: 1.5rem;
  color: hsl(var(--foreground));
}

:deep(.prose p) {
  margin-top: 0.5rem;
  margin-bottom: 0.5rem;
}

:deep(.prose p:first-child) {
  margin-top: 0;
}

:deep(.prose p:last-child) {
  margin-bottom: 0;
}

:deep(.prose pre) {
  background-color: hsl(var(--background));
  border: 1px solid hsl(var(--border));
  margin: 0.75rem 0;
  position: relative;
  border-radius: 0.375rem;
  font-size: 0.75rem;
}

:deep(.prose code) {
  font-size: 0.75rem;
}

:deep(.prose :not(pre) > code) {
  background-color: hsl(var(--accent));
  padding: 0.125rem 0.25rem;
  border-radius: 0.25rem;
  font-size: 0.75rem;
}

/* Tables */
:deep(.prose table) {
  border-collapse: collapse;
  margin: 0.75rem 0;
  width: 100%;
  font-size: 0.75rem;
}

:deep(.prose thead) {
  border-bottom: 2px solid hsl(var(--border));
}

:deep(.prose th) {
  font-weight: 600;
  padding: 0.5rem;
  text-align: left;
}

:deep(.prose td) {
  padding: 0.5rem;
  border-bottom: 1px solid hsl(var(--border));
}

:deep(.prose tbody tr:last-child td) {
  border-bottom: none;
}

/* Lists */
:deep(.prose ul) {
  list-style-type: disc;
  padding-left: 1.25rem;
}

:deep(.prose ol) {
  list-style-type: decimal;
  padding-left: 1.25rem;
}

:deep(.prose li) {
  margin: 0.25rem 0;
}

/* Blockquotes */
:deep(.prose blockquote) {
  border-left: 3px solid hsl(var(--muted-foreground) / 0.3);
  padding-left: 0.75rem;
  margin: 0.75rem 0;
  font-style: italic;
  color: hsl(var(--muted-foreground));
}

/* Links */
:deep(.prose a) {
  color: hsl(var(--primary));
  text-decoration: underline;
  text-underline-offset: 2px;
}

:deep(.prose a:hover) {
  text-decoration: none;
}

/* Headings */
:deep(.prose h1),
:deep(.prose h2),
:deep(.prose h3),
:deep(.prose h4),
:deep(.prose h5),
:deep(.prose h6) {
  font-weight: 600;
  margin-top: 1rem;
  margin-bottom: 0.5rem;
}

:deep(.prose h1) { font-size: 1.125rem; }
:deep(.prose h2) { font-size: 1rem; }
:deep(.prose h3) { font-size: 0.875rem; }
:deep(.prose h4) { font-size: 0.875rem; }

/* Images */
:deep(.prose img) {
  border-radius: 0.375rem;
  margin: 0.75rem 0;
  max-width: 100%;
  height: auto;
}

/* Horizontal rule */
:deep(.prose hr) {
  border: none;
  border-top: 1px solid hsl(var(--border));
  margin: 1rem 0;
}

/* Alert styling */
:deep(.markdown-alert) {
  padding: 0.5rem 0.75rem;
  margin: 0.5rem 0;
  border-left-width: 3px;
  border-left-style: solid;
  border-radius: 0.25rem;
  font-size: 0.75rem;
}

:deep(.markdown-alert-note) {
  border-left-color: hsl(var(--primary));
  background-color: hsl(var(--primary) / 0.1);
}

:deep(.markdown-alert-tip) {
  border-left-color: hsl(var(--success));
  background-color: hsl(var(--success) / 0.1);
}

:deep(.markdown-alert-important) {
  border-left-color: hsl(var(--warning));
  background-color: hsl(var(--warning) / 0.1);
}

:deep(.markdown-alert-warning) {
  border-left-color: hsl(var(--warning));
  background-color: hsl(var(--warning) / 0.1);
}

:deep(.markdown-alert-caution) {
  border-left-color: hsl(var(--destructive));
  background-color: hsl(var(--destructive) / 0.1);
}

:deep(.markdown-alert-title) {
  display: flex;
  align-items: center;
  gap: 0.375rem;
  font-weight: 600;
  margin-bottom: 0.25rem;
}
</style>
