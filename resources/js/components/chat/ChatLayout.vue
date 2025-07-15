<script setup lang="ts">
import {ref, onMounted, nextTick, provide, watch} from 'vue';
import Sidebar from '@/components/chat/ChatSidebar.vue';
import Header from '@/components/chat/Header.vue';
import axios from "axios";
import { router } from '@inertiajs/vue3';
import { usePage } from '@inertiajs/vue3'
import { vOnClickOutside } from '@vueuse/components'
import isMobile from 'is-mobile'
import { Head } from '@inertiajs/vue3'

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

interface ModelSettings {
    temperature: number;
    top_k: number;
    top_p: number;
    presence_penalty: number;
    frequency_penalty: number;
}

interface ModelsResponse {
    models: AIModel[];
    settings: ModelSettings;
    default_settings: ModelSettings;
}

const isMobileDevice = isMobile()

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
        availableModels.value = response.data.models.filter(model => model.user_allowed_to_use);
    } catch (error) {
        console.error('Failed to fetch models:', error);
    } finally {
        isLoadingModels.value = false;
    }
};

const findModelById = (modelId: string): AIModel | undefined => {
    return availableModels.value.find(m => m.id === modelId);
}

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
        router.visit(
            route(route().current()!, { model: selectedModel.value.id }),
        );
    }

    // Initialize sidebar state from localStorage
    if (!isMobileDevice) {
        const savedSidebarState = localStorage.getItem('sidebarOpen');
        if (savedSidebarState !== null) {
            isSidebarOpen.value = savedSidebarState === 'true';
        } else {
            isSidebarOpen.value = window.innerWidth >= 768;
            localStorage.setItem('sidebarOpen', String(isSidebarOpen.value));
        }
    }

    window.addEventListener('resize', checkScreenSize);
});

const deleteConversation = (conversationId: string) => {
    axios.delete(route('conversations.delete', { conversationId })).then(() => {
        if (availableModels.value.length > 0) {
            router.visit(route('chat.home', { model: availableModels.value[0].id }));
        }
    })
}

const closeSidebarOnMobile = () => {
    if (isMobileDevice) {
        isSidebarOpen.value = false;
    }
}

const toggleSidebar = () => {
    isSidebarOpen.value = !isSidebarOpen.value;
}

const handleToggleIncognito = (value: boolean) => {
    isIncognitoMode.value = value;
}
</script>

<template>
    <Head title="Chat" />

    <div class="flex h-screen">
        <!-- Mobile overlay -->
        <div
            v-if="isMobileDevice && isSidebarOpen"
            class="fixed inset-0 z-30 bg-black/50 transition-opacity duration-300"
            @click="isSidebarOpen = false"
        ></div>

        <!-- Sidebar -->
        <div
            class="fixed left-0 top-0 z-40 h-screen w-64 transition-transform duration-300 ease-in-out"
            :class="{ '-translate-x-full': !isSidebarOpen }"
            v-on-click-outside="closeSidebarOnMobile"
        >
            <Sidebar
                v-model:open="isSidebarOpen"
                :models="availableModels"
                :defaultModel="selectedModel"
                :isLoadingModels="isLoadingModels"
                class="h-full w-full bg-white dark:bg-gray-800 border-r border-gray-200 dark:border-gray-700"
            />
        </div>

        <div
            class="flex flex-col flex-1 transition-all duration-300 ease-in-out h-screen overflow-hidden"
            :class="{ 'md:ml-64': isSidebarOpen }"
        >
            <Header
                :token-count="0"
                :selected-model="selectedModel || { id: '', name: 'Loading...', context: 0, description: '' }"
                class="w-full border-b bg-white dark:bg-gray-900 flex-shrink-0"
                @deleteConversation="deleteConversation"
                @toggleIncognito="handleToggleIncognito"
                @toggleSidebar="toggleSidebar"
                :isSidebarOpen="isSidebarOpen"
            />

            <main class="flex-1 overflow-hidden">
                <slot name="content" />
            </main>
        </div>
    </div>
</template>
