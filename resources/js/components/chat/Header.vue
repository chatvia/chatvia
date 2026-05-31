<script setup>
import ThemeSwitcher from '@/components/ThemeSwitcher.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { updateTheme } from '@/composables/useAppearance';
import { Maximize2, Minimize2, PanelLeftClose, PanelLeftOpen, Trash2 } from 'lucide-vue-next';
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
});

const emit = defineEmits(['deleteConversation', 'toggleIncognito', 'toggleSidebar']);

const isIncognitoMode = ref(false);
const showDeleteDialog = ref(false);

onMounted(() => {
    // Initialize incognito mode from localStorage
    const savedIncognitoMode = localStorage.getItem('isIncognitoMode');
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
    localStorage.setItem('isIncognitoMode', isIncognitoMode.value.toString());
    emit('toggleIncognito', isIncognitoMode.value);
}

function toggleSidebar() {
    emit('toggleSidebar');
}
function toggleChatExpand() {
    const result = chatEventBus.toggleChatExpand();
    console.log('Chat expanded:', result);
}
</script>

<template>
    <header class="bg-background/95 border-border/30 sticky top-0 z-10 w-full border-b backdrop-blur-sm">
        <div class="flex h-14 items-center justify-between px-4">
            <div class="flex items-center gap-1">
                <!-- Collapse/Expand sidebar -->
                <Button
                    v-if="!removeSidebar"
                    variant="ghost"
                    @click="toggleSidebar"
                    size="icon"
                    class="text-muted-foreground hover:text-foreground hover:bg-muted/80 h-9 w-9 rounded-lg transition-colors"
                    :title="t('header.toggle_sidebar')"
                >
                    <PanelLeftClose v-if="isSidebarOpen" class="h-[18px] w-[18px]" />
                    <PanelLeftOpen v-else class="h-[18px] w-[18px]" />
                </Button>

                <!-- Expand/Collapse chat content -->
                <Button
                    v-if="enableChatExpand"
                    variant="ghost"
                    @click="toggleChatExpand"
                    size="icon"
                    class="text-muted-foreground hover:text-foreground hover:bg-muted/80 h-9 w-9 rounded-lg transition-colors"
                    :title="t('header.toggle_expand')"
                >
                    <Maximize2 v-if="!isChatExpanded" class="h-[18px] w-[18px]" />
                    <Minimize2 v-else class="h-[18px] w-[18px]" />
                </Button>
            </div>

            <div class="flex items-center gap-1">
                <!-- Incognito mode indicator -->
                <Transition name="slide-fade">
                    <div v-if="isIncognitoMode" class="mr-2 flex items-center gap-1.5 rounded-full bg-purple-500/10 px-2.5 py-1">
                        <div class="h-1.5 w-1.5 animate-pulse rounded-full bg-purple-500"></div>
                        <span class="text-[11px] font-medium text-purple-600 dark:text-purple-400">{{ t('header.incognito_mode') }}</span>
                    </div>
                </Transition>

                <!-- Delete conversation button -->
                <Dialog v-model:open="showDeleteDialog">
                    <DialogTrigger as-child>
                        <Button
                            v-show="route().current() === 'chat.show'"
                            variant="ghost"
                            size="icon"
                            class="text-muted-foreground h-9 w-9 rounded-lg transition-colors hover:bg-red-500/10 hover:text-red-600"
                            :title="t('header.delete_conversation')"
                        >
                            <Trash2 class="h-[18px] w-[18px]" />
                        </Button>
                    </DialogTrigger>
                    <DialogContent class="rounded-2xl sm:max-w-[400px]">
                        <DialogHeader>
                            <DialogTitle class="text-lg font-semibold">{{ t('header.delete_dialog_title') }}</DialogTitle>
                            <DialogDescription class="text-muted-foreground mt-1 text-sm">{{
                                t('header.delete_dialog_description')
                            }}</DialogDescription>
                        </DialogHeader>
                        <DialogFooter class="mt-4 gap-2 sm:gap-2">
                            <Button variant="ghost" @click="showDeleteDialog = false" class="rounded-lg">{{ t('common.cancel') }}</Button>
                            <Button variant="destructive" @click="handleConfirm" class="rounded-lg">{{ t('common.delete') }}</Button>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>

                <!-- Theme switcher -->
                <ThemeSwitcher v-if="enableThemeSwitcher" />
            </div>
        </div>
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
