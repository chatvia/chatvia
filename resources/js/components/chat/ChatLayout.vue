<script setup lang="ts">
import Sidebar from '@/components/chat/ChatSidebar.vue';
import Header from '@/components/chat/Header.vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import { vOnClickOutside } from '@vueuse/components';
import axios from 'axios';
import isMobile from 'is-mobile';
import { onMounted, ref, watch } from 'vue';

type AIModel = App.Data.Chat.LlmModelData;

interface ModelSettings {
    temperature: number;
    top_k: number;
    top_p: number;
    presence_penalty: number;
    frequency_penalty: number;
}

const props = defineProps({
    appearance: {
        type: Object,
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
        validator: (value: string) => ['auto', 'light', 'dark'].includes(value),
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

interface ModelsResponse {
    models: AIModel[];
    settings: ModelSettings;
    default_settings: ModelSettings;
}

const isMobileDevice = isMobile();

const isSidebarOpen = ref(false);

const page = usePage();
const isIncognitoMode = ref(false);

const selectedModel = ref<AIModel | null>(null);
const availableModels = ref<AIModel[]>([]);
const isLoadingModels = ref(true);

// Fetch models from API
const fetchModels = async () => {
    try {
        isLoadingModels.value = true;
        const response = await axios.get<ModelsResponse>(route('chat.models.index'));
        availableModels.value = response.data.models;
    } catch (error) {
        console.error('Failed to fetch models:', error);
    } finally {
        isLoadingModels.value = false;
    }
};

const findModelById = (modelId: string): AIModel | undefined => {
    return availableModels.value.find((m) => m.id === modelId);
};

// Save sidebar state to localStorage when it changes
watch(isSidebarOpen, (newValue) => {
    if (!isMobileDevice) {
        localStorage.setItem('sidebarOpen', String(newValue));
    }
});

function checkScreenSize() {
    const isDesktop = window.innerWidth >= 768;
    if (isDesktop && !isMobileDevice) {
        // Only auto-open on desktop
        isSidebarOpen.value = true;
    } else if (isMobileDevice) {
        // Always close on mobile when resizing
        isSidebarOpen.value = false;
    }
}

const getQueryParam = (param: string): string | null => {
    const urlParams = new URLSearchParams(window.location.search);
    return urlParams.get(param);
};

onMounted(async () => {
    // Fetch models first
    await fetchModels();

    const modelId = getQueryParam('model');

    if (modelId) {
        const foundModel = findModelById(modelId);
        if (foundModel) {
            selectedModel.value = foundModel;
        } else if (availableModels.value.length > 0) {
            selectedModel.value = availableModels.value[0];
        }
    } else if (availableModels.value.length > 0) {
        selectedModel.value = availableModels.value[0];
        // Redirect to include model in URL
        router.visit(route(route().current()!, { model: selectedModel.value.id }));
    }

    // Initialize sidebar state from localStorage or hideSidebarOnPageLoad prop
    if (!isMobileDevice && !props.removeSidebar) {
        if (props.hideSidebarOnPageLoad) {
            // If hideSidebarOnPageLoad prop is true, start with sidebar closed
            isSidebarOpen.value = false;
            localStorage.setItem('sidebarOpen', 'false');
        } else {
            // Use saved state or default behavior
            const savedSidebarState = localStorage.getItem('sidebarOpen');
            if (savedSidebarState !== null) {
                isSidebarOpen.value = savedSidebarState === 'true';
            } else {
                isSidebarOpen.value = window.innerWidth >= 768;
                localStorage.setItem('sidebarOpen', String(isSidebarOpen.value));
            }
        }
    }

    window.addEventListener('resize', checkScreenSize);
});

const deleteConversation = (conversationId: string) => {
    axios.delete(route('conversations.delete', { conversationId })).then(() => {
        if (availableModels.value.length > 0) {
            router.visit(route(props.newChatRoute, { model: availableModels.value[0].id }), {
                preserveState: false,
                preserveScroll: false,
                replace: true,
            });
        }
    });
};

const closeSidebarOnMobile = () => {
    if (isMobileDevice) {
        isSidebarOpen.value = false;
    }
};

const toggleSidebar = () => {
    isSidebarOpen.value = !isSidebarOpen.value;
};

const handleToggleIncognito = (value: boolean) => {
    isIncognitoMode.value = value;
};
</script>

<template>
    <Head :title="props.appearance?.chat_window_title || 'Chat'" />

    <div class="flex h-screen">
        <!-- Mobile overlay -->
        <Transition name="fade">
            <div
                v-if="!removeSidebar && isMobileDevice && isSidebarOpen"
                class="fixed inset-0 z-30 bg-black/40 backdrop-blur-sm"
                @click="isSidebarOpen = false"
            ></div>
        </Transition>

        <!-- Sidebar -->
        <div
            v-if="!removeSidebar"
            class="fixed top-0 left-0 z-40 h-screen w-64 transition-transform duration-300 ease-in-out"
            :class="{ '-translate-x-full': !isSidebarOpen }"
            v-on-click-outside="closeSidebarOnMobile"
        >
            <Sidebar
                :appearance="appearance"
                v-model:open="isSidebarOpen"
                :models="availableModels"
                :defaultModel="selectedModel || undefined"
                :isLoadingModels="isLoadingModels"
                :newChatRoute="newChatRoute"
                class="bg-sidebar h-full w-full"
            />
        </div>

        <div
            class="flex h-screen flex-1 flex-col overflow-hidden transition-all duration-300 ease-in-out"
            :class="{ 'md:ml-64': !removeSidebar && isSidebarOpen }"
        >
            <Header
                :token-count="0"
                :selected-model="selectedModel || { id: '', name: 'Loading...', context: 0, description: '' }"
                class="w-full flex-shrink-0"
                @deleteConversation="deleteConversation"
                @toggleIncognito="handleToggleIncognito"
                @toggleSidebar="toggleSidebar"
                :isSidebarOpen="isSidebarOpen"
                :removeSidebar="removeSidebar"
                :enableThemeSwitcher="enableThemeSwitcher"
                :defaultTheme="defaultTheme"
                :enableChatExpand="enableChatExpand"
            />

            <main class="flex-1 overflow-hidden">
                <slot name="content" />
            </main>
        </div>
    </div>
</template>
