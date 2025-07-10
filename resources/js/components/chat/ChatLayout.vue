<script setup>
import {ref, onMounted, nextTick, provide, watch} from 'vue';
import Sidebar from '@/components/chat/ChatSidebar.vue';
import Header from '@/components/chat/Header.vue';
import axios from "axios";
import { router } from '@inertiajs/vue3';
import { usePage } from '@inertiajs/vue3'
import { vOnClickOutside } from '@vueuse/components'
import isMobile from 'is-mobile'
import { Head } from '@inertiajs/vue3'

const isMobileDevice = isMobile()

const isSidebarOpen = ref(false);

const page = usePage();
const isIncognitoMode = ref(false);

const selectedModel = ref({
    id: 'gpt-4o-mini',
    name: '',
    context: 0,
    description: ''
});

const availableModels = ref(page.props.models);

const findModelIdx = (modelId => {
    return availableModels.value.find(m => m.external_id === modelId) || page.props.defaultModel;
})

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

const getQueryParam = (param) => {
    const urlParams = new URLSearchParams(window.location.search);
    return urlParams.get(param);
};

onMounted(() => {
    let model = getQueryParam('model');

    if (!model) {
        model = findModelIdx(model) || page.props.defaultModel;
        router.visit(
            route(route().current(), { model: model.external_id }),
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

const deleteConversation = (conversationId) => {
    axios.delete(route('conversations.delete', { conversationId })).then(() => {
        router.visit(route('chat.home', { model: page.props.defaultModel.external_id }));
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

const handleToggleIncognito = (value) => {
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
                class="h-full w-full bg-white dark:bg-gray-800 border-r border-gray-200 dark:border-gray-700"
            />
        </div>

        <div
            class="flex flex-col flex-1 transition-all duration-300 ease-in-out h-screen overflow-hidden"
            :class="{ 'md:ml-64': isSidebarOpen }"
        >
            <Header
                :token-count="0"
                :selected-model="selectedModel"
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
