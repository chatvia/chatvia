<script lang="ts" setup>
/**
 * Hosted chat sidebar: brand header with the collapse control, "New chat" and
 * ⌘K search, the conversation list, and a footer with "Clear all" and the
 * chatter's account menu (profile, agents, log out). The open state is a
 * v-model shared with ChatLayout and remembered per device on desktop.
 */
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import ChatAgentsDialog from '@/components/chat/ChatAgentsDialog.vue';
import ChatConversationList from '@/components/chat/ChatConversationList.vue';
import ChatProfileDialog from '@/components/chat/ChatProfileDialog.vue';
import ChatSearchDialog from '@/components/chat/ChatSearchDialog.vue';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
    AlertDialogTrigger,
} from '@/components/ui/alert-dialog';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuSeparator, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import { chatPages } from '@/composables/chat/chatPages';
import { useChatConversations } from '@/composables/chat/useChatConversations';
import { useBrand } from '@/composables/useBrand';
import { useCurrentRoute } from '@/composables/useCurrentRoute';
import { useIsMobile } from '@/composables/useIsMobile';
import { safeStorage } from '@/lib/safeStorage';
import chat from '@/routes/chat';
import type { SharedData } from '@/types';
import type { ChatAppearance } from '@/types/chat';
import { Link, router, usePage } from '@inertiajs/vue3';
import { onKeyStroke } from '@vueuse/core';
import { Bot, Edit, LogOut, MoreVertical, PanelLeft, Search, Trash2, User } from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';

const props = withDefaults(
    defineProps<{
        appearance?: ChatAppearance;
        /** On the iframe-embed pages rather than the full hosted chat. */
        embedded?: boolean;
    }>(),
    {
        appearance: undefined,
        embedded: false,
    },
);

const isSidebarOpen = defineModel<boolean>('open', { default: false });
const { params: routeParams } = useCurrentRoute();

const SIDEBAR_STORAGE_KEY = 'sidebarOpen';

const { t } = useI18n();
const { brand } = useBrand();
const page = usePage<SharedData>();
const isMobileDevice = useIsMobile();
const { conversations, isLoading: isLoadingConversations, search, rename, remove, removeAll } = useChatConversations();

const currentUser = computed(() => page.props.auth?.user ?? null);
const searchDialogOpen = ref(false);
const profileDialogOpen = ref(false);
const agentsDialogOpen = ref(false);

// Platform-aware label for the Cmd/Ctrl+K search shortcut hint.
const searchShortcutHint = computed(() =>
    typeof navigator !== 'undefined' && /Mac|iPhone|iPad/i.test(navigator.platform || navigator.userAgent) ? '⌘K' : 'Ctrl K',
);

const visitFresh = { preserveState: false, preserveScroll: false };

onMounted(() => {
    if (isMobileDevice.value) {
        isSidebarOpen.value = false;
        return;
    }

    const saved = safeStorage.get(SIDEBAR_STORAGE_KEY);
    if (saved !== null) {
        isSidebarOpen.value = saved === 'true';
    } else {
        isSidebarOpen.value = true;
        safeStorage.set(SIDEBAR_STORAGE_KEY, 'true');
    }
});

// Cmd/Ctrl+K opens the chat search anywhere in the surface.
onKeyStroke(['k', 'K'], (event) => {
    if (!(event.metaKey || event.ctrlKey)) return;
    event.preventDefault();
    searchDialogOpen.value = true;
});

function toggleSidebar() {
    isSidebarOpen.value = !isSidebarOpen.value;
    safeStorage.set(SIDEBAR_STORAGE_KEY, String(isSidebarOpen.value));
}

/** On mobile the sidebar is a drawer: close it before navigating away. */
function closeDrawerOnMobile() {
    if (!isMobileDevice.value) return;
    safeStorage.set(SIDEBAR_STORAGE_KEY, 'false');
    isSidebarOpen.value = false;
}

function newConversation() {
    closeDrawerOnMobile();
    router.visit(chatPages(props.embedded).home(), visitFresh);
}

function goToConversation(id: string | number) {
    closeDrawerOnMobile();
    router.visit(chat.show.url(String(id)));
}

async function deleteConversation(id: string | number) {
    if (!(await remove(id))) return;

    // Deleting the open conversation sends the visitor to the new-chat screen.
    // Only the `conversationId` param matters: the route name differs per surface.
    const openConversationId = routeParams.value.conversationId;
    if (openConversationId !== undefined && openConversationId === String(id)) {
        router.visit(chatPages(props.embedded).home(), { ...visitFresh, replace: true });
    }
}

async function deleteAllConversations() {
    if (await removeAll()) {
        router.visit(chatPages(props.embedded).home(), { ...visitFresh, replace: true });
    }
}

function logout() {
    router.post(chat.logout.url());
}
</script>

<template>
    <!-- Mobile backdrop -->
    <Transition
        name="backdrop"
        enter-active-class="transition-opacity duration-300 ease-out"
        enter-from-class="opacity-0"
        enter-to-class="opacity-100"
        leave-active-class="transition-opacity duration-200 ease-in"
        leave-from-class="opacity-100"
        leave-to-class="opacity-0"
    >
        <div v-show="isSidebarOpen && isMobileDevice" class="bg-background/80 fixed inset-0 z-40 backdrop-blur-sm" @click="toggleSidebar"></div>
    </Transition>

    <div
        :class="[isMobileDevice ? 'sm:max-w-none' : 'relative', isSidebarOpen ? 'translate-x-0' : '-translate-x-full rtl:translate-x-full']"
        class="bg-sidebar border-sidebar-border fixed start-0 top-0 z-40 h-screen w-64 border-e transition-transform duration-300 ease-out"
    >
        <div class="flex h-full flex-col px-3 py-3">
            <div class="mb-2 flex items-center justify-between">
                <Link :href="chat.home.url()" class="hover:bg-accent flex items-center gap-2 rounded-lg p-2 transition-colors">
                    <img
                        v-if="appearance?.logo"
                        :src="appearance.logo"
                        :alt="appearance?.chat_window_title || brand.name"
                        class="h-8 object-contain"
                    />
                    <template v-else>
                        <AppLogoIcon class="h-7 w-7" />
                        <span class="text-foreground text-sm font-semibold tracking-tight">
                            {{ appearance?.chat_window_title || brand.name }}
                        </span>
                    </template>
                </Link>
                <!-- Collapse: on desktop the reopen control then appears in the top bar; on mobile it closes the drawer. -->
                <TooltipProvider>
                    <Tooltip>
                        <TooltipTrigger as-child>
                            <button
                                :aria-label="t('header.toggle_sidebar')"
                                class="text-muted-foreground hover:text-foreground hover:bg-accent cursor-pointer rounded-lg p-2 transition-colors"
                                @click="toggleSidebar"
                            >
                                <PanelLeft class="h-5 w-5 rtl:scale-x-[-1]" />
                            </button>
                        </TooltipTrigger>
                        <TooltipContent side="bottom">{{ t('header.toggle_sidebar') }}</TooltipContent>
                    </Tooltip>
                </TooltipProvider>
            </div>

            <nav class="mb-4 space-y-1">
                <button
                    @click="newConversation"
                    class="text-foreground hover:bg-accent flex w-full cursor-pointer items-center gap-3 rounded-lg px-3 py-2.5 text-sm transition-colors"
                >
                    <Edit class="h-5 w-5" />
                    <span>{{ t('sidebar.new_chat') }}</span>
                </button>
                <button
                    @click="searchDialogOpen = true"
                    class="text-foreground hover:bg-accent flex w-full cursor-pointer items-center gap-3 rounded-lg px-3 py-2.5 text-sm transition-colors"
                >
                    <Search class="h-5 w-5" />
                    <span>{{ t('sidebar.search_chats') }}</span>
                    <kbd
                        class="text-muted-foreground border-border bg-background ms-auto hidden rounded border px-1.5 py-0.5 font-mono text-[10px] leading-none md:inline-block"
                        >{{ searchShortcutHint }}</kbd
                    >
                </button>
            </nav>

            <div class="flex min-h-0 flex-1 flex-col">
                <div class="flex-shrink-0 px-3 py-2">
                    <span class="text-muted-foreground text-[11px] font-medium tracking-wider uppercase">{{ t('sidebar.your_chats') }}</span>
                </div>

                <ChatConversationList
                    :conversations="conversations"
                    :loading="isLoadingConversations"
                    :rename="rename"
                    @delete="deleteConversation"
                />
            </div>

            <div class="border-sidebar-border bg-sidebar/50 -mx-3 -mb-3 flex-shrink-0 space-y-2 border-t px-3 py-3 backdrop-blur-sm">
                <AlertDialog>
                    <AlertDialogTrigger asChild>
                        <button
                            class="text-muted-foreground hover:text-destructive hover:bg-accent flex w-full cursor-pointer items-center gap-3 rounded-lg px-3 py-2.5 text-sm transition-colors"
                        >
                            <Trash2 class="h-5 w-5" />
                            <span>{{ t('sidebar.clear_all_chats') }}</span>
                        </button>
                    </AlertDialogTrigger>
                    <AlertDialogContent>
                        <AlertDialogHeader>
                            <AlertDialogTitle>{{ t('sidebar.delete_all_title') }}</AlertDialogTitle>
                            <AlertDialogDescription>{{ t('sidebar.delete_all_description') }}</AlertDialogDescription>
                        </AlertDialogHeader>
                        <AlertDialogFooter>
                            <AlertDialogCancel>{{ t('common.cancel') }}</AlertDialogCancel>
                            <AlertDialogAction
                                class="bg-destructive text-destructive-foreground hover:bg-destructive/90"
                                @click="deleteAllConversations"
                            >
                                {{ t('sidebar.delete_all_confirm') }}
                            </AlertDialogAction>
                        </AlertDialogFooter>
                    </AlertDialogContent>
                </AlertDialog>

                <DropdownMenu v-if="currentUser">
                    <DropdownMenuTrigger asChild>
                        <button
                            class="text-foreground hover:bg-accent flex w-full cursor-pointer items-center gap-3 rounded-lg px-3 py-2.5 text-sm transition-colors"
                        >
                            <Avatar class="h-7 w-7">
                                <AvatarImage :src="currentUser.avatar || ''" />
                                <AvatarFallback class="text-xs">{{ currentUser.name?.charAt(0) || 'U' }}</AvatarFallback>
                            </Avatar>
                            <span class="flex-1 truncate text-start">{{ currentUser.name || t('sidebar.user_fallback') }}</span>
                            <MoreVertical class="text-muted-foreground h-4 w-4" />
                        </button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="start" side="top" class="w-56">
                        <DropdownMenuItem class="cursor-pointer" @click="profileDialogOpen = true">
                            <User class="me-2 h-4 w-4" />
                            <span>{{ t('sidebar.profile') }}</span>
                        </DropdownMenuItem>
                        <DropdownMenuItem class="cursor-pointer" @click="agentsDialogOpen = true">
                            <Bot class="me-2 h-4 w-4" />
                            <span>{{ t('sidebar.my_agents') }}</span>
                        </DropdownMenuItem>
                        <DropdownMenuSeparator />
                        <DropdownMenuItem class="cursor-pointer" @click="logout">
                            <LogOut class="me-2 h-4 w-4" />
                            <span>{{ t('sidebar.logout') }}</span>
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>
            </div>
        </div>
    </div>

    <ChatSearchDialog
        v-model:open="searchDialogOpen"
        :conversations="conversations"
        :search="search"
        @select="goToConversation"
        @new-chat="newConversation"
    />

    <ChatProfileDialog v-model:open="profileDialogOpen" />

    <ChatAgentsDialog v-model:open="agentsDialogOpen" />
</template>
