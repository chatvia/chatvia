<script setup lang="ts">
import Sidebar from '@/components/chat/ChatSidebar.vue';
import Header from '@/components/chat/Header.vue';
import { Toaster } from '@/components/ui/sonner';
import { Head, router, usePage } from '@inertiajs/vue3';
import { vOnClickOutside } from '@vueuse/components';
import { onKeyStroke } from '@vueuse/core';
import axios from 'axios';
import { useIsMobile } from '@/composables/useIsMobile';
import { computed, onMounted, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';

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
    enableLanguageSwitcher: {
        type: Boolean,
        default: false,
    },
    enabledLanguages: {
        type: Array,
        default: () => [],
    },
    newChatRoute: {
        type: String,
        default: 'chat.home',
    },
});

const { t } = useI18n();

const isMobileDevice = useIsMobile();

const pageTitle = computed(() => props.appearance?.chat_window_title || t('shared.chat_title_fallback'));

const isSidebarOpen = ref(false);

const _page = usePage();
const isIncognitoMode = ref(false);

// Save sidebar state to localStorage when it changes
watch(isSidebarOpen, (newValue) => {
    if (!isMobileDevice.value) {
        localStorage.setItem('sidebarOpen', String(newValue));
    }
});

function checkScreenSize() {
    if (!isMobileDevice.value) {
        // Only auto-open on desktop
        isSidebarOpen.value = true;
    } else {
        // Always close on mobile
        isSidebarOpen.value = false;
    }
}

onMounted(() => {
    // Initialize sidebar state from localStorage or hideSidebarOnPageLoad prop
    if (!isMobileDevice.value && !props.removeSidebar) {
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
                isSidebarOpen.value = !isMobileDevice.value;
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
    if (isMobileDevice.value) {
        isSidebarOpen.value = false;
    }
};

const toggleSidebar = () => {
    isSidebarOpen.value = !isSidebarOpen.value;
};

// Cmd/Ctrl+B toggles the conversation sidebar (the convention the shadcn
// Sidebar primitive uses). Cmd/Ctrl+K (search) is owned by ChatSidebar.
onKeyStroke('b', (e) => {
    if (props.removeSidebar || !(e.metaKey || e.ctrlKey)) return;
    e.preventDefault();
    toggleSidebar();
});

const handleToggleIncognito = (value: boolean) => {
    isIncognitoMode.value = value;
};
</script>

<template>
    <Head :title="pageTitle" />

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
                :enableLanguageSwitcher="enableLanguageSwitcher"
                :enabledLanguages="enabledLanguages"
            />

            <main class="flex-1 overflow-hidden">
                <slot name="content" />
            </main>
        </div>
    </div>
</template>
