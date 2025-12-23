<script setup>
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import ChatComposer from '@/components/chat/ChatComposer.vue';
import ChatLayout from '@/components/chat/ChatLayout.vue';
import { useColorTheme } from '@/composables/useColorTheme';
import { Head, router } from '@inertiajs/vue3';
import axios from 'axios';
import { onMounted, onUnmounted, ref } from 'vue';

const props = defineProps({
    appearance: {
        type: Object,
        default: () => ({
            color_theme: 'black',
            welcome_message: null,
            chat_window_title: null,
            chat_footer_text: null,
            avatar: null,
            logo: null,
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
    chatRoute: {
        type: String,
        default: 'chat.show',
    },
    newChatRoute: {
        type: String,
        default: 'chat.home',
    },
});

const selectedModel = ref();
const selectedStarter = ref('');

const starters = ref([]);

// Models
const models = ref([]);
const defaultModel = ref(null);
const isLoadingModels = ref(true);

// Usage checking
const USAGE_CHECK_INTERVAL = 60000; // 1 minute
const canSendMessages = ref(true);
const usageCheckInterval = ref(null);

const getQueryParam = (param) => {
    const urlParams = new URLSearchParams(window.location.search);
    return urlParams.get(param);
};

// Fetch models
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
                router.visit(route(props.newChatRoute, { model: firstModel?.id }), { replace: true });
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

const { applyColorTheme } = useColorTheme();

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

onMounted(async () => {
    // Start usage checking
    startUsageChecking();

    // Apply color theme
    applyColorTheme(props.appearance);

    // Fetch models
    await fetchModels();

    // Fetch starters
    axios.get(route('chat.starters')).then((response) => {
        starters.value = response.data;
    });
});

onUnmounted(() => {
    // Stop usage checking
    stopUsageChecking();
});

function updateSelectedModel(newModel) {
    selectedModel.value = newModel;
    localStorage.setItem('selectedModel', newModel.id);
}

async function createNewConversation(message, model) {
    await axios.post(route('conversations.create'), { message: message, model: model.id }).then((response) => {
        router.visit(route(props.chatRoute, { conversationId: response.data.id, model: model.id, new: '1' }));
    });
}

function handleStarterClick(starter) {
    selectedStarter.value = starter.text;
}
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
        <Head title="ChatVia" />
        <template #content>
            <div class="flex h-full items-center justify-center px-4 py-8">
                <div class="mx-auto w-full max-w-3xl">
                    <!-- Logo -->
                    <div class="mb-4 flex justify-center">
                        <div class="h-14 w-14 overflow-hidden">
                            <img v-if="props.appearance.logo" :src="props.appearance.logo" alt="Logo" class="h-full w-full object-contain" />
                            <AppLogoIcon v-else class="h-full w-full" />
                        </div>
                    </div>

                    <!-- Welcome message -->
                    <h1 class="text-foreground mb-10 text-center text-2xl font-semibold sm:text-3xl">
                        {{ props.appearance?.welcome_message || 'How can I help you today?' }}
                    </h1>

                    <!-- Conversation Starters -->
                    <div v-if="starters.length > 0" class="mb-8">
                        <div class="mx-auto grid max-w-2xl grid-cols-1 gap-3 sm:grid-cols-2">
                            <button
                                v-for="starter in starters"
                                :key="starter.id"
                                @click="handleStarterClick(starter)"
                                class="group border-border/60 bg-card hover:bg-muted/50 hover:border-border cursor-pointer rounded-xl border px-4 py-3 text-left transition-all duration-150"
                            >
                                <p class="text-foreground/80 group-hover:text-foreground line-clamp-2 text-sm leading-relaxed">
                                    {{ starter.text }}
                                </p>
                            </button>
                        </div>
                    </div>

                    <div class="mx-auto w-full max-w-2xl">
                        <!-- Show composer if user can send messages -->
                        <ChatComposer
                            v-if="canSendMessages"
                            :text="selectedStarter"
                            :placeholder="appearance.composer_placeholder"
                            :models="models"
                            :defaultModel="defaultModel"
                            :isLoadingModels="isLoadingModels"
                            @modelSelected="updateSelectedModel"
                            @messageAdded="createNewConversation"
                        />

                        <!-- Show limit message if user cannot send messages -->
                        <div
                            v-else
                            class="mx-auto flex max-w-md items-center justify-center rounded-lg border border-orange-200 bg-orange-50 p-4 dark:border-orange-800 dark:bg-orange-900/20"
                        >
                            <div class="text-center">
                                <p class="text-sm font-medium text-orange-800 dark:text-orange-200">You've reached your message limit</p>
                                <p class="mt-1 text-xs text-orange-600 dark:text-orange-300">Please upgrade your plan to continue chatting</p>
                            </div>
                        </div>

                        <!-- Footer text -->
                        <div v-if="props.appearance?.chat_footer_text" class="mt-4 text-center">
                            <p class="text-muted-foreground/60 text-[11px]">
                                {{ props.appearance.chat_footer_text }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </template>
    </ChatLayout>
</template>
