<script setup>
import LanguageSwitcher from '@/components/LanguageSwitcher.vue';
import ThemeSwitcher from '@/components/ThemeSwitcher.vue';
import { Button } from '@/components/ui/button';
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
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import { updateTheme } from '@/composables/useAppearance';
import { safeStorage } from '@/lib/safeStorage';
import { Maximize2, Minimize2, PanelLeft, Trash2 } from 'lucide-vue-next';
import { computed, inject, onMounted, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

const chatEventBus = inject('chatEventBus');

const isChatExpanded = computed(() => chatEventBus.isChatExpanded.value);

const props = defineProps({
    tokenCount: {
        type: Number,
        default: 0,
    },
    isSidebarOpen: {
        type: Boolean,
        default: false,
    },
    removeSidebar: {
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
    // Visitor-facing language picker. Off for the dashboard/playground; on for
    // the hosted/embedded chat surfaces (gated by embed.show_language_switcher).
    enableLanguageSwitcher: {
        type: Boolean,
        default: false,
    },
    // Allow-list of language codes for the switcher (Setup → Languages). Empty ⇒
    // every platform locale.
    enabledLanguages: {
        type: Array,
        default: () => [],
    },
});

const emit = defineEmits(['deleteConversation', 'toggleIncognito', 'toggleSidebar']);

const isIncognitoMode = ref(false);
const showDeleteDialog = ref(false);

onMounted(() => {
    // Initialize incognito mode from localStorage (safeStorage: the hosted chat
    // may run in an iframe where storage is blocked).
    const savedIncognitoMode = safeStorage.get('isIncognitoMode');
    if (savedIncognitoMode !== null) {
        isIncognitoMode.value = savedIncognitoMode === 'true';
        emit('toggleIncognito', isIncognitoMode.value);
    }

    // Apply default theme if theme switcher is disabled
    if (!props.enableThemeSwitcher) {
        const themeValue = props.defaultTheme === 'auto' ? 'system' : props.defaultTheme;
        updateTheme(themeValue);
    }
});

// Watch for changes to theme props
watch([() => props.enableThemeSwitcher, () => props.defaultTheme], ([enableSwitcher, defaultTheme]) => {
    if (!enableSwitcher) {
        const themeValue = defaultTheme === 'auto' ? 'system' : defaultTheme;
        updateTheme(themeValue);
    }
});

const handleConfirm = () => {
    emit('deleteConversation', route().params.conversationId);
    showDeleteDialog.value = false;
};

function _toggleIncognitoMode() {
    isIncognitoMode.value = !isIncognitoMode.value;
    safeStorage.set('isIncognitoMode', isIncognitoMode.value.toString());
    emit('toggleIncognito', isIncognitoMode.value);
}

function toggleSidebar() {
    emit('toggleSidebar');
}
function toggleChatExpand() {
    chatEventBus.toggleChatExpand();
}
</script>

<template>
    <header class="bg-background/95 border-border/60 sticky top-0 z-10 w-full border-b backdrop-blur-sm">
        <TooltipProvider>
            <div class="flex h-14 items-center justify-between px-4">
                <div class="flex items-center gap-1">
                    <!-- Reopen sidebar. The collapse control now lives inside the
                         sidebar header (ChatGPT-style); this top-bar button only
                         appears once the sidebar is collapsed, to bring it back. -->
                    <Tooltip v-if="!removeSidebar && !isSidebarOpen">
                        <TooltipTrigger as-child>
                            <Button
                                variant="ghost"
                                @click="toggleSidebar"
                                size="icon"
                                class="text-muted-foreground hover:text-foreground hover:bg-muted/80 h-9 w-9 rounded-lg transition-colors"
                                :aria-label="t('header.toggle_sidebar')"
                            >
                                <PanelLeft class="h-[18px] w-[18px] rtl:scale-x-[-1]" />
                            </Button>
                        </TooltipTrigger>
                        <TooltipContent side="bottom">{{ t('header.toggle_sidebar') }}</TooltipContent>
                    </Tooltip>

                    <!-- Expand/Collapse chat content -->
                    <Tooltip v-if="enableChatExpand">
                        <TooltipTrigger as-child>
                            <Button
                                variant="ghost"
                                @click="toggleChatExpand"
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
                    <!-- Incognito mode indicator -->
                    <Transition name="slide-fade">
                        <div v-if="isIncognitoMode" class="me-2 flex items-center gap-1.5 rounded-full bg-purple-500/10 px-2.5 py-1">
                            <div class="h-1.5 w-1.5 animate-pulse rounded-full bg-purple-500"></div>
                            <span class="text-[11px] font-medium text-purple-600 dark:text-purple-400">{{ t('header.incognito_mode') }}</span>
                        </div>
                    </Transition>

                    <!-- Delete conversation button -->
                    <AlertDialog v-model:open="showDeleteDialog">
                        <Tooltip>
                            <TooltipTrigger as-child>
                                <AlertDialogTrigger as-child>
                                    <Button
                                        v-show="route().current() === 'chat.show'"
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
                                <Button variant="destructive" @click="handleConfirm" class="rounded-lg">{{ t('common.delete') }}</Button>
                            </AlertDialogFooter>
                        </AlertDialogContent>
                    </AlertDialog>

                    <!-- Visitor language switcher -->
                    <LanguageSwitcher v-if="enableLanguageSwitcher" :codes="enabledLanguages" />

                    <!-- Theme switcher -->
                    <ThemeSwitcher v-if="enableThemeSwitcher" />
                </div>
            </div>
        </TooltipProvider>
    </header>
</template>

<style scoped>
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
