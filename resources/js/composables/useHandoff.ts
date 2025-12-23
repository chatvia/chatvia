import axios from 'axios';
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';
import { computed, onUnmounted, ref, watch } from 'vue';

// Types
interface HandoffState {
    status: 'ai' | 'pending' | 'human' | 'returned';
    adminId: string | null;
    adminName: string | null;
    handoffAt: string | null;
}

interface PresenceMember {
    id: string;
    name: string;
    type: 'user' | 'agent';
}

interface TypingState {
    isTyping: boolean;
    typerType: 'user' | 'agent';
    typerName: string | null;
}

// Make Pusher globally available
// @ts-ignore
window.Pusher = Pusher;

// Singleton Echo instance for presence channels
let echoInstance: Echo<'reverb'> | null = null;

function getEcho(): Echo<'reverb'> {
    if (!echoInstance) {
        echoInstance = new Echo({
            broadcaster: 'reverb',
            key: import.meta.env.VITE_REVERB_APP_KEY,
            wsHost: import.meta.env.VITE_REVERB_HOST,
            wsPort: import.meta.env.VITE_REVERB_PORT ?? 80,
            wssPort: import.meta.env.VITE_REVERB_PORT ?? 443,
            forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
            enabledTransports: ['ws', 'wss'],
        });
    }
    return echoInstance;
}

export function useHandoff(agentId: () => number | null, conversationId: () => string | null) {
    // State
    const handoffState = ref<HandoffState>({
        status: 'ai',
        adminId: null,
        adminName: null,
        handoffAt: null,
    });

    const presenceMembers = ref<PresenceMember[]>([]);
    const typingState = ref<TypingState | null>(null);
    const isConnected = ref(false);
    const isLoading = ref(false);
    const error = ref<string | null>(null);
    const adminMessage = ref('');
    const isSendingMessage = ref(false);
    const newMessageReceived = ref<any>(null); // Reactive trigger for new messages

    // Current channel reference
    let currentChannel: any = null;
    let currentChannelName: string | null = null;
    let typingTimeout: ReturnType<typeof setTimeout> | null = null;
    let onMessageCallback: ((data: any) => void) | null = null;

    // Computed
    const isHandedOff = computed(() => handoffState.value.status === 'human');
    const canTakeOver = computed(() => handoffState.value.status !== 'human');
    const canReturn = computed(() => handoffState.value.status === 'human');

    const userIsOnline = computed(() => {
        return presenceMembers.value.some((m) => m.type === 'user');
    });

    const onlineUser = computed(() => {
        return presenceMembers.value.find((m) => m.type === 'user');
    });

    const userIsTyping = computed(() => {
        return typingState.value?.isTyping && typingState.value.typerType === 'user';
    });

    // Handle typing events (from server broadcast or client whisper)
    function handleTypingEvent(data: any) {
        if (data.typer_type === 'user') {
            typingState.value = {
                isTyping: data.is_typing,
                typerType: data.typer_type,
                typerName: data.typer_name,
            };

            // Clear typing after 3 seconds if no update
            if (typingTimeout) clearTimeout(typingTimeout);
            if (data.is_typing) {
                typingTimeout = setTimeout(() => {
                    if (typingState.value?.typerType === 'user') {
                        typingState.value = null;
                    }
                }, 3000);
            }
        }
    }

    // Join presence channel for a conversation
    function joinChannel(convId: string) {
        if (!convId) return;

        const channelName = `chat.${convId}`;

        // Don't rejoin if we're already on this channel
        if (currentChannelName === channelName && currentChannel && isConnected.value) {
            return;
        }

        leaveChannel();

        const echo = getEcho();
        currentChannelName = channelName;

        currentChannel = echo
            .join(channelName)
            .here((members: PresenceMember[]) => {
                presenceMembers.value = members;
                isConnected.value = true;
            })
            .joining((member: PresenceMember) => {
                if (!presenceMembers.value.find((m) => m.id === member.id)) {
                    presenceMembers.value.push(member);
                }
            })
            .leaving((member: PresenceMember) => {
                presenceMembers.value = presenceMembers.value.filter((m) => m.id !== member.id);
            })
            .listen('.message', (data: any) => {
                newMessageReceived.value = { ...data, receivedAt: Date.now() };
                if (onMessageCallback) {
                    onMessageCallback(data);
                }
            })
            .listen('.typing', (data: any) => {
                handleTypingEvent(data);
            })
            .listenForWhisper('typing', (data: any) => {
                handleTypingEvent(data);
            })
            .listen('.handoff', (data: any) => {
                handoffState.value = {
                    status: data.status,
                    adminId: data.admin_id,
                    adminName: data.admin_name,
                    handoffAt: data.timestamp,
                };
            })
            .error((err: any) => {
                error.value = 'Failed to connect to chat channel';
                isConnected.value = false;
            });
    }

    function leaveChannel() {
        if (currentChannel && currentChannelName) {
            getEcho().leave(currentChannelName);
            currentChannel = null;
            currentChannelName = null;
            isConnected.value = false;
            presenceMembers.value = [];
            typingState.value = null;
        }
    }

    // API calls
    async function takeOver() {
        const agId = agentId();
        const convId = conversationId();
        if (!agId || !convId) return;

        isLoading.value = true;
        error.value = null;

        try {
            const response = await axios.post(
                route('agents.handoff.takeover', {
                    agent: agId,
                    conversation: convId,
                }),
            );

            handoffState.value = {
                status: 'human',
                adminId: response.data.admin_id,
                adminName: response.data.admin_name,
                handoffAt: new Date().toISOString(),
            };

            // Join channel after takeover
            joinChannel(convId);

            return response.data;
        } catch (err: any) {
            error.value = err.response?.data?.message || 'Failed to take over conversation';
            throw err;
        } finally {
            isLoading.value = false;
        }
    }

    async function returnToAi() {
        const agId = agentId();
        const convId = conversationId();
        if (!agId || !convId) return;

        isLoading.value = true;
        error.value = null;

        try {
            const response = await axios.post(
                route('agents.handoff.return', {
                    agent: agId,
                    conversation: convId,
                }),
            );

            handoffState.value = {
                status: 'ai',
                adminId: null,
                adminName: null,
                handoffAt: null,
            };

            leaveChannel();

            return response.data;
        } catch (err: any) {
            error.value = err.response?.data?.message || 'Failed to return conversation to AI';
            throw err;
        } finally {
            isLoading.value = false;
        }
    }

    async function sendMessage(content: string) {
        const agId = agentId();
        const convId = conversationId();
        if (!agId || !convId || !content.trim()) return;

        isSendingMessage.value = true;
        error.value = null;

        try {
            const response = await axios.post(
                route('agents.handoff.message', {
                    agent: agId,
                    conversation: convId,
                }),
                { content },
            );

            adminMessage.value = '';
            return response.data.message;
        } catch (err: any) {
            error.value = err.response?.data?.message || 'Failed to send message';
            throw err;
        } finally {
            isSendingMessage.value = false;
        }
    }

    async function sendTyping(isTyping: boolean) {
        const agId = agentId();
        const convId = conversationId();
        if (!agId || !convId) return;

        try {
            await axios.post(
                route('agents.handoff.typing', {
                    agent: agId,
                    conversation: convId,
                }),
                { is_typing: isTyping },
            );
        } catch (err) {
            // Silently fail for typing indicators
            console.warn('Failed to send typing indicator:', err);
        }
    }

    async function markMessagesAsRead() {
        const agId = agentId();
        const convId = conversationId();
        if (!agId || !convId) return;

        try {
            await axios.post(
                route('agents.handoff.mark-read', {
                    agent: agId,
                    conversation: convId,
                }),
            );
        } catch (err) {
            // Silently fail
            console.warn('Failed to mark messages as read:', err);
        }
    }

    // Watch for conversation changes
    watch(
        () => conversationId(),
        (newId, oldId) => {
            if (oldId) {
                leaveChannel();
            }

            // Reset state
            handoffState.value = {
                status: 'ai',
                adminId: null,
                adminName: null,
                handoffAt: null,
            };
            presenceMembers.value = [];
            typingState.value = null;
            error.value = null;

            // If handed off, join the channel
            // This will be triggered after takeOver or when viewing a handed-off conversation
        },
    );

    // Set callback for new messages
    function onMessage(callback: (data: any) => void) {
        onMessageCallback = callback;
    }

    // Cleanup on unmount
    onUnmounted(() => {
        leaveChannel();
        if (typingTimeout) clearTimeout(typingTimeout);
        onMessageCallback = null;
    });

    return {
        // State
        handoffState,
        presenceMembers,
        typingState,
        isConnected,
        isLoading,
        error,
        adminMessage,
        isSendingMessage,
        newMessageReceived,

        // Computed
        isHandedOff,
        canTakeOver,
        canReturn,
        userIsOnline,
        onlineUser,
        userIsTyping,

        // Actions
        takeOver,
        returnToAi,
        sendMessage,
        sendTyping,
        markMessagesAsRead,
        joinChannel,
        leaveChannel,
        onMessage,
    };
}
