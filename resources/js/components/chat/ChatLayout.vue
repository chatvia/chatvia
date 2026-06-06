<script setup lang="ts">
import Sidebar from '@/components/chat/ChatSidebar.vue';
import Header from '@/components/chat/Header.vue';
import { Toaster } from '@/components/ui/sonner';
import { Head, router, usePage } from '@inertiajs/vue3';
import { vOnClickOutside } from '@vueuse/components';
import axios from 'axios';
import isMobile from 'is-mobile';
import { onMounted, ref, watch } from 'vue';

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

const isMobileDevice = isMobile();

const isSidebarOpen = ref(false);

const _page = usePage();
const isIncognitoMode = ref(false);

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

onMounted(() => {
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
        router.visit(route(props.newChatRoute), {
            preserveState: false,
            preserveScroll: false,
            replace: true,
        });
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

    <!-- Toast outlet for the chat pages (upload errors, limits, etc.). Without
         this, every toast.* call from ChatComposer/ChatSidebar renders nowhere. -->
    <Toaster position="top-center" :duration="5000" />

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
            class="fixed start-0 top-0 z-40 h-screen w-64 transition-transform duration-300 ease-in-out"
            :class="{ '-translate-x-full rtl:translate-x-full': !isSidebarOpen }"
            v-on-click-outside="closeSidebarOnMobile"
        >
            <Sidebar :appearance="appearance" v-model:open="isSidebarOpen" :newChatRoute="newChatRoute" class="bg-sidebar h-full w-full" />
        </div>

        <div
            class="flex h-screen flex-1 flex-col overflow-hidden transition-all duration-300 ease-in-out"
            :class="{ 'md:ms-64': !removeSidebar && isSidebarOpen }"
        >
            <Header
                :token-count="0"
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
