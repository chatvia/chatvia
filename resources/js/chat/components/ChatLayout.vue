<script setup lang="ts">
/**
 * Frame shared by the hosted chat pages: page title, toast outlet, the
 * collapsible conversation sidebar (persisted per device, a drawer on mobile)
 * and the header above the page's `content` slot.
 */
import { deleteConversation as deleteConversationRequest } from '@/chat/api/chat';
import Sidebar from '@/chat/components/ChatSidebar.vue';
import Header from '@/chat/components/Header.vue';
import { chatPages } from '@/chat/composables/chatPages';
import { Toaster } from '@/shared/ui/sonner';
import { useIsMobile } from '@/chat/composables/useIsMobile';
import { safeStorage } from '@/shared/lib/safeStorage';
import type { ChatAppearance, ChatDefaultTheme } from '@/shared/types/chat';
import { Head, router } from '@inertiajs/vue3';
import { onKeyStroke } from '@vueuse/core';
import { computed, onMounted, ref, watch } from 'vue';
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

/**
 * Every chat page mounts its own layout, so the open state is settled before
 * the first paint: starting closed and opening on mount would slide the
 * sidebar in again on each conversation the visitor opens. Mobile always
 * starts closed (a drawer); desktop remembers the last choice.
 */
function initialSidebarState(): boolean {
    if (props.removeSidebar || isMobileDevice.value) return false;
    if (props.hideSidebarOnPageLoad) {
        safeStorage.set(SIDEBAR_STORAGE_KEY, 'false');
        return false;
    }

    const saved = safeStorage.get(SIDEBAR_STORAGE_KEY);

    return saved === null ? true : saved === 'true';
}

const isSidebarOpen = ref(initialSidebarState());

// Transitions switch on after the first frame, so the initial state never animates.
const isAnimated = ref(false);

watch(isSidebarOpen, (open) => {
    if (!isMobileDevice.value) safeStorage.set(SIDEBAR_STORAGE_KEY, String(open));
});

// Crossing the breakpoint (a narrowed window, a rotated tablet): the drawer
// closes on mobile, and desktop gets back the visitor's last choice.
watch(isMobileDevice, (mobile) => {
    isSidebarOpen.value = mobile ? false : initialSidebarState();
});

onMounted(() => {
    requestAnimationFrame(() => {
        isAnimated.value = true;
    });
});

async function deleteConversation(conversationId: string) {
    await deleteConversationRequest(conversationId);
    router.visit(chatPages(props.embedded).home(), {
        preserveState: false,
        preserveScroll: false,
        replace: true,
    });
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

// Esc closes the mobile drawer, like the tap on its backdrop.
onKeyStroke('Escape', () => {
    if (isMobileDevice.value && isSidebarOpen.value) isSidebarOpen.value = false;
});
</script>

<template>
    <Head :title="pageTitle" />

    <!-- Toast outlet for the chat pages (upload errors, limits, etc.). Without
         this, every toast.* call from ChatComposer/ChatSidebar renders nowhere. -->
    <Toaster position="top-center" :duration="5000" />

    <div class="flex h-dvh">
        <!-- Mobile backdrop: a tap outside the drawer closes it. -->
        <Transition name="fade">
            <div
                v-if="!removeSidebar && isMobileDevice && isSidebarOpen"
                class="fixed inset-0 z-30 bg-black/40"
                aria-hidden="true"
                @click="isSidebarOpen = false"
            ></div>
        </Transition>

        <!-- Sidebar: slides on its own layer; on desktop the main column's
             margin moves with it on the same curve. -->
        <aside
            v-if="!removeSidebar"
            class="fixed start-0 top-0 z-40 h-dvh w-64"
            :class="[
                { 'transition-[transform,visibility] duration-250 ease-[cubic-bezier(0.2,0,0,1)] motion-reduce:transition-none': isAnimated },
                isSidebarOpen ? 'visible translate-x-0' : 'invisible -translate-x-full rtl:translate-x-full',
                isMobileDevice && isSidebarOpen ? 'shadow-2xl' : '',
            ]"
            :inert="!isSidebarOpen"
        >
            <Sidebar :appearance="appearance" v-model:open="isSidebarOpen" :embedded="embedded" />
        </aside>

        <div
            class="flex h-dvh min-w-0 flex-1 flex-col overflow-hidden"
            :class="[
                { 'transition-[margin] duration-250 ease-[cubic-bezier(0.2,0,0,1)] motion-reduce:transition-none': isAnimated },
                { 'md:ms-64': !removeSidebar && isSidebarOpen },
            ]"
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
