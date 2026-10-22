<script setup lang="ts">
/**
 * Top bar of the hosted chat: reopen-sidebar and expand toggles on one side;
 * incognito badge, delete-conversation, language and theme switchers on the
 * other. When the theme switcher is hidden it applies the owner's default theme.
 */
import LanguageSwitcher from '@/components/LanguageSwitcher.vue';
import ThemeSwitcher from '@/components/ThemeSwitcher.vue';
import {
    AlertDialog,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
    AlertDialogTrigger,
} from '@/components/ui/alert-dialog';
import { Button } from '@/components/ui/button';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import { updateTheme } from '@/composables/useAppearance';
import { useCurrentRoute } from '@/composables/useCurrentRoute';
import { safeStorage } from '@/lib/safeStorage';
import type { ChatDefaultTheme } from '@/types/chat';
import { useChatEventBus } from '@/utils/eventBus';
import { Maximize2, Minimize2, PanelLeft, Trash2 } from 'lucide-vue-next';
import { computed, onMounted, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';

interface Props {
    isSidebarOpen?: boolean;
    removeSidebar?: boolean;
    enableThemeSwitcher?: boolean;
    defaultTheme?: ChatDefaultTheme;
    enableChatExpand?: boolean;
    /** Visitor language picker: off for the dashboard playground, on for hosted chat when enabled. */
    enableLanguageSwitcher?: boolean;
    /** Language codes offered by the switcher (Setup → Languages). Empty means every locale. */
    enabledLanguages?: string[];
}

const props = withDefaults(defineProps<Props>(), {
    isSidebarOpen: false,
    removeSidebar: false,
    enableThemeSwitcher: true,
    defaultTheme: 'auto',
    enableChatExpand: true,
    enableLanguageSwitcher: false,
    enabledLanguages: () => [],
});

const emit = defineEmits<{
    deleteConversation: [conversationId: string];
    toggleIncognito: [enabled: boolean];
    toggleSidebar: [];
}>();

const { t } = useI18n();
const bus = useChatEventBus();
const { name: routeName, params: routeParams } = useCurrentRoute();

const isChatExpanded = computed(() => bus.isChatExpanded.value);
const isIncognitoMode = ref(false);
const showDeleteDialog = ref(false);

function applyDefaultTheme(enableSwitcher: boolean, defaultTheme: ChatDefaultTheme) {
    if (enableSwitcher) return;
    updateTheme(defaultTheme === 'auto' ? 'system' : defaultTheme);
}

onMounted(() => {
    // safeStorage: the hosted chat may run in an iframe where storage is blocked.
    const savedIncognitoMode = safeStorage.get('isIncognitoMode');
    if (savedIncognitoMode !== null) {
        isIncognitoMode.value = savedIncognitoMode === 'true';
        emit('toggleIncognito', isIncognitoMode.value);
    }

    applyDefaultTheme(props.enableThemeSwitcher, props.defaultTheme);
});

watch([() => props.enableThemeSwitcher, () => props.defaultTheme], ([enableSwitcher, defaultTheme]) => {
    applyDefaultTheme(enableSwitcher, defaultTheme);
});

function confirmDelete() {
    emit('deleteConversation', String(routeParams.value.conversationId));
    showDeleteDialog.value = false;
}
</script>

<template>
    <header class="bg-background/95 border-border/60 sticky top-0 z-10 w-full border-b backdrop-blur-sm">
        <TooltipProvider>
            <div class="flex h-14 items-center justify-between px-4">
                <div class="flex items-center gap-1">
                    <!-- Only shown once the sidebar is collapsed; the collapse
                         control itself lives in the sidebar header. It fades in
                         as the sidebar slides away and leaves at once. -->
                    <Transition name="sidebar-toggle">
                        <div v-if="!removeSidebar && !isSidebarOpen" class="flex">
                            <Tooltip>
                                <TooltipTrigger as-child>
                                    <Button
                                        variant="ghost"
                                        @click="emit('toggleSidebar')"
                                        size="icon"
                                        class="text-muted-foreground hover:text-foreground hover:bg-muted/80 h-9 w-9 rounded-lg transition-colors"
                                        :aria-label="t('header.toggle_sidebar')"
                                    >
                                        <PanelLeft class="h-[18px] w-[18px] rtl:scale-x-[-1]" />
                                    </Button>
                                </TooltipTrigger>
                                <TooltipContent side="bottom">{{ t('header.toggle_sidebar') }}</TooltipContent>
                            </Tooltip>
                        </div>
                    </Transition>

                    <Tooltip v-if="enableChatExpand">
                        <TooltipTrigger as-child>
                            <Button
                                variant="ghost"
                                @click="bus.toggleChatExpand()"
                                size="icon"
                                class="text-muted-foreground hover:text-foreground hover:bg-muted/80 h-9 w-9 rounded-lg transition-colors"
                                :aria-label="t('header.toggle_expand')"
                            >
                                <Maximize2 v-if="!isChatExpanded" class="h-[18px] w-[18px]" />
                                <Minimize2 v-else class="h-[18px] w-[18px]" />
                            </Button>
                        </TooltipTrigger>
                        <TooltipContent side="bottom">{{ t('header.toggle_expand') }}</TooltipContent>
                    </Tooltip>
                </div>

                <div class="flex items-center gap-1">
                    <Transition name="slide-fade">
                        <div v-if="isIncognitoMode" class="me-2 flex items-center gap-1.5 rounded-full bg-purple-500/10 px-2.5 py-1">
                            <div class="h-1.5 w-1.5 animate-pulse rounded-full bg-purple-500"></div>
                            <span class="text-[11px] font-medium text-purple-600 dark:text-purple-400">{{ t('header.incognito_mode') }}</span>
                        </div>
                    </Transition>

                    <AlertDialog v-model:open="showDeleteDialog">
                        <Tooltip>
                            <TooltipTrigger as-child>
                                <AlertDialogTrigger as-child>
                                    <Button
                                        v-show="routeName === 'chat.show'"
                                        variant="ghost"
                                        size="icon"
                                        class="text-muted-foreground h-9 w-9 rounded-lg transition-colors hover:bg-red-500/10 hover:text-red-600"
                                        :aria-label="t('header.delete_conversation')"
                                    >
                                        <Trash2 class="h-[18px] w-[18px]" />
                                    </Button>
                                </AlertDialogTrigger>
                            </TooltipTrigger>
                            <TooltipContent side="bottom">{{ t('header.delete_conversation') }}</TooltipContent>
                        </Tooltip>
                        <AlertDialogContent class="rounded-2xl sm:max-w-[400px]">
                            <AlertDialogHeader>
                                <AlertDialogTitle class="text-lg font-semibold">{{ t('header.delete_dialog_title') }}</AlertDialogTitle>
                                <AlertDialogDescription class="text-muted-foreground mt-1 text-sm">{{
                                    t('header.delete_dialog_description')
                                }}</AlertDialogDescription>
                            </AlertDialogHeader>
                            <AlertDialogFooter class="mt-4 gap-2 sm:gap-2">
                                <AlertDialogCancel class="rounded-lg">{{ t('common.cancel') }}</AlertDialogCancel>
                                <Button variant="destructive" @click="confirmDelete" class="rounded-lg">{{ t('common.delete') }}</Button>
                            </AlertDialogFooter>
                        </AlertDialogContent>
                    </AlertDialog>

                    <LanguageSwitcher v-if="enableLanguageSwitcher" :codes="enabledLanguages" />

                    <ThemeSwitcher v-if="enableThemeSwitcher" />
                </div>
            </div>
        </TooltipProvider>
    </header>
</template>

<style scoped>
.sidebar-toggle-enter-active {
    transition: opacity 0.2s ease-out 0.1s;
}
.sidebar-toggle-enter-from {
    opacity: 0;
}
@media (prefers-reduced-motion: reduce) {
    .sidebar-toggle-enter-active {
        transition: none;
    }
}

.slide-fade-enter-active {
    transition: all 0.2s ease-out;
}
.slide-fade-leave-active {
    transition: all 0.15s ease-in;
}
.slide-fade-enter-from,
.slide-fade-leave-to {
    opacity: 0;
    transform: scale(0.95);
}
</style>
