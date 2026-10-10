<script setup lang="ts">
/**
 * Frame shared by the hosted chat pages: page title, toast outlet, the
 * collapsible conversation sidebar (persisted per device, a drawer on mobile)
 * and the header above the page's `content` slot.
 */
import { deleteConversation as deleteConversationRequest } from '@/api/chat';
import Sidebar from '@/components/chat/ChatSidebar.vue';
import Header from '@/components/chat/Header.vue';
import { chatPages } from '@/composables/chat/chatPages';
import { Toaster } from '@/components/ui/sonner';
import { useIsMobile } from '@/composables/useIsMobile';
import { safeStorage } from '@/lib/safeStorage';
import type { ChatAppearance, ChatDefaultTheme } from '@/types/chat';
import { Head, router } from '@inertiajs/vue3';
import { vOnClickOutside } from '@vueuse/components';
import { onKeyStroke } from '@vueuse/core';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';

interface Props {
    appearance?: ChatAppearance;
    removeSidebar?: boolean;
    hideSidebarOnPageLoad?: boolean;
    enableThemeSwitcher?: boolean;
    defaultTheme?: ChatDefaultTheme;
    enableChatExpand?: boolean;
    enableLanguageSwitcher?: boolean;
    enabledLanguages?: string[];
    /** On the iframe-embed pages rather than the full hosted chat. */
    embedded?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    appearance: undefined,
    removeSidebar: false,
    hideSidebarOnPageLoad: false,
    enableThemeSwitcher: true,
    defaultTheme: 'auto',
    enableChatExpand: true,
    enableLanguageSwitcher: false,
    enabledLanguages: () => [],
    embedded: false,
});

const SIDEBAR_STORAGE_KEY = 'sidebarOpen';

const { t } = useI18n();
const isMobileDevice = useIsMobile();

const pageTitle = computed(() => props.appearance?.chat_window_title || t('shared.chat_title_fallback'));

const isSidebarOpen = ref(false);

// Desktop remembers the sidebar state; mobile always starts closed.
watch(isSidebarOpen, (open) => {
    if (!isMobileDevice.value) {
        safeStorage.set(SIDEBAR_STORAGE_KEY, String(open));
    }
});

function syncSidebarToViewport() {
    isSidebarOpen.value = !isMobileDevice.value;
}

function restoreSidebarState() {
    if (props.hideSidebarOnPageLoad) {
        isSidebarOpen.value = false;
        safeStorage.set(SIDEBAR_STORAGE_KEY, 'false');
        return;
    }

    const saved = safeStorage.get(SIDEBAR_STORAGE_KEY);
    if (saved !== null) {
        isSidebarOpen.value = saved === 'true';
        return;
    }

    isSidebarOpen.value = !isMobileDevice.value;
    safeStorage.set(SIDEBAR_STORAGE_KEY, String(isSidebarOpen.value));
}

onMounted(() => {
    if (!isMobileDevice.value && !props.removeSidebar) {
        restoreSidebarState();
    }

    window.addEventListener('resize', syncSidebarToViewport);
});

onUnmounted(() => {
    window.removeEventListener('resize', syncSidebarToViewport);
});

async function deleteConversation(conversationId: string) {
    await deleteConversationRequest(conversationId);
    router.visit(chatPages(props.embedded).home(), {
        preserveState: false,
        preserveScroll: false,
        replace: true,
    });
}

function closeSidebarOnMobile() {
    if (isMobileDevice.value) {
        isSidebarOpen.value = false;
    }
}

function toggleSidebar() {
    isSidebarOpen.value = !isSidebarOpen.value;
}

// Cmd/Ctrl+B toggles the conversation sidebar (the shadcn Sidebar convention).
// Cmd/Ctrl+K (search) is owned by ChatSidebar.
onKeyStroke('b', (e) => {
    if (props.removeSidebar || !(e.metaKey || e.ctrlKey)) return;
    e.preventDefault();
    toggleSidebar();
});
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
            <Sidebar :appearance="appearance" v-model:open="isSidebarOpen" :embedded="embedded" />
        </div>

        <div
            class="flex h-screen flex-1 flex-col overflow-hidden transition-all duration-300 ease-in-out"
            :class="{ 'md:ms-64': !removeSidebar && isSidebarOpen }"
        >
            <Header
                class="w-full flex-shrink-0"
                @deleteConversation="deleteConversation"
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
