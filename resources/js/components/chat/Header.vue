<script setup>
import { ref, onMounted, inject, computed } from 'vue';
import { cn } from '@/lib/utils';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import ThemeSwitcher from '@/components/ThemeSwitcher.vue';
import { Trash2, EyeOff, Eye, PanelLeftClose, PanelLeftOpen, Maximize2, Minimize2 } from 'lucide-vue-next';

const chatEventBus = inject('chatEventBus')

const isChatExpanded = computed(() => chatEventBus.isChatExpanded.value);

const props = defineProps({
  tokenCount: {
    type: Number,
    default: 0
  },
  selectedModel: {
    type: Object,
    default: () => ({})
  },
    isSidebarOpen: {
        type: Boolean,
        default: false
    },
});

const emit = defineEmits(['update:selectedModel', 'deleteConversation', 'toggleIncognito', 'toggleSidebar']);


const isIncognitoMode = ref(false);
const showDeleteDialog = ref(false);

onMounted(() => {
    // Initialize incognito mode from localStorage
    const savedIncognitoMode = localStorage.getItem('isIncognitoMode');
    if (savedIncognitoMode !== null) {
        isIncognitoMode.value = savedIncognitoMode === 'true';
        emit('toggleIncognito', isIncognitoMode.value);
    }
});

const handleConfirm = () => {
    emit('deleteConversation', route().params.conversationId);
    showDeleteDialog.value = false;
};

function toggleIncognitoMode() {
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
    <header class="sticky top-0 z-10 w-full backdrop-blur-xl bg-background/80 border-b border-border/40 shadow-sm">
        <div class="flex h-16 items-center justify-between px-4 sm:px-6 md:px-8">
            <div class="flex items-center gap-2">
                <!-- Collapse/Expand icon with animation -->
                <Button
                    variant="ghost"
                    @click="toggleSidebar"
                    size="icon"
                    class="group relative overflow-hidden rounded-xl transition-all duration-200 hover:scale-105 hover:bg-accent/80"
                    title="Toggle sidebar"
                >
                    <div class="absolute inset-0 bg-gradient-to-r from-primary/10 to-primary/5 opacity-0 group-hover:opacity-100 transition-opacity duration-300"></div>
                    <Transition name="rotate" mode="out-in">
                        <PanelLeftClose v-if="isSidebarOpen" class="h-5 w-5 relative z-10" />
                        <PanelLeftOpen v-else class="h-5 w-5 relative z-10" />
                    </Transition>
                </Button>

                <!-- Expand/Collapse chat content icon with animation -->
                <Button
                    variant="ghost"
                    @click="toggleChatExpand"
                    size="icon"
                    class="group relative overflow-hidden rounded-xl transition-all duration-200 hover:scale-105 hover:bg-accent/80"
                    title="Toggle fullscreen"
                >
                    <div class="absolute inset-0 bg-gradient-to-r from-primary/10 to-primary/5 opacity-0 group-hover:opacity-100 transition-opacity duration-300"></div>
                    <Transition name="scale" mode="out-in">
                        <Maximize2 v-if="!isChatExpanded" class="h-5 w-5 relative z-10" />
                        <Minimize2 v-else class="h-5 w-5 relative z-10" />
                    </Transition>
                </Button>
            </div>

            <div class="flex items-center gap-3">
                <!-- Incognito mode indicator with animation -->
                <Transition name="slide-fade">
                    <div v-if="isIncognitoMode" class="flex items-center gap-2 px-3 py-1.5 rounded-full bg-purple-500/10 border border-purple-500/20">
                        <div class="w-2 h-2 rounded-full bg-purple-500 animate-pulse"></div>
                        <span class="text-xs font-medium text-purple-600 dark:text-purple-400">
                            Incognito Mode
                        </span>
                    </div>
                </Transition>

                <!-- Incognito toggle button with enhanced styling -->
<!--                <Button-->
<!--                    @click="toggleIncognitoMode"-->
<!--                    variant="ghost"-->
<!--                    size="icon"-->
<!--                    :class="cn(-->
<!--                        'group relative overflow-hidden rounded-xl transition-all duration-200 hover:scale-105',-->
<!--                        isIncognitoMode-->
<!--                            ? 'bg-purple-500/10 text-purple-600 hover:bg-purple-500/20 dark:text-purple-400'-->
<!--                            : 'hover:bg-accent/80'-->
<!--                    )"-->
<!--                    :title="isIncognitoMode ? 'Disable incognito mode' : 'Enable incognito mode'"-->
<!--                >-->
<!--                    <div class="absolute inset-0 bg-gradient-to-r from-purple-500/20 to-purple-500/10 opacity-0 group-hover:opacity-100 transition-opacity duration-300"></div>-->
<!--                    <Transition name="rotate" mode="out-in">-->
<!--                        <EyeOff v-if="isIncognitoMode" class="h-5 w-5 relative z-10" />-->
<!--                        <Eye v-else class="h-5 w-5 relative z-10" />-->
<!--                    </Transition>-->
<!--                </Button>-->

                <!-- Delete conversation button with enhanced hover state -->
                <Dialog v-model:open="showDeleteDialog">
                    <DialogTrigger as-child>
                        <Button
                            v-show="route().current() === 'chat.show'"
                            variant="ghost"
                            size="icon"
                            class="group relative overflow-hidden rounded-xl transition-all duration-200 hover:scale-105 hover:bg-red-500/10"
                            title="Delete conversation"
                        >
                            <div class="absolute inset-0 bg-gradient-to-r from-red-500/20 to-red-500/10 opacity-0 group-hover:opacity-100 transition-opacity duration-300"></div>
                            <Trash2 class="h-5 w-5 relative z-10 group-hover:text-red-600 dark:group-hover:text-red-400 transition-colors duration-200" />
                        </Button>
                    </DialogTrigger>
                    <DialogContent class="sm:max-w-[425px]">
                        <DialogHeader>
                            <DialogTitle class="text-xl font-semibold">Delete Conversation?</DialogTitle>
                            <DialogDescription class="text-muted-foreground mt-2">
                                Are you sure you want to delete this conversation? This action cannot be undone.
                            </DialogDescription>
                        </DialogHeader>
                        <DialogFooter class="mt-6 gap-2 sm:gap-0">
                            <Button
                                variant="outline"
                                @click="showDeleteDialog = false"
                                class="hover:bg-accent transition-colors duration-200"
                            >
                                Cancel
                            </Button>
                            <Button
                                variant="destructive"
                                @click="handleConfirm"
                                class="bg-red-600 hover:bg-red-700 transition-colors duration-200"
                            >
                                Delete
                            </Button>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>

                <!-- Theme switcher with divider -->
                <div class="flex items-center gap-3">
                    <div class="h-6 w-px bg-border/50"></div>
                    <ThemeSwitcher />
                </div>
            </div>
        </div>
    </header>
</template>

<style scoped>
/* Transition animations */
.rotate-enter-active,
.rotate-leave-active {
    transition: all 0.2s ease;
}

.rotate-enter-from {
    transform: rotate(-90deg) scale(0.8);
    opacity: 0;
}

.rotate-leave-to {
    transform: rotate(90deg) scale(0.8);
    opacity: 0;
}

.scale-enter-active,
.scale-leave-active {
    transition: all 0.2s ease;
}

.scale-enter-from,
.scale-leave-to {
    transform: scale(0.6);
    opacity: 0;
}

.slide-fade-enter-active {
    transition: all 0.3s ease-out;
}

.slide-fade-leave-active {
    transition: all 0.2s ease-in;
}

.slide-fade-enter-from {
    transform: translateX(-20px);
    opacity: 0;
}

.slide-fade-leave-to {
    transform: translateX(20px);
    opacity: 0;
}

/* Subtle glow effect on hover */
@keyframes glow {
    0%, 100% {
        box-shadow: 0 0 0 0 rgba(var(--primary), 0.2);
    }
    50% {
        box-shadow: 0 0 20px 5px rgba(var(--primary), 0.1);
    }
}

.group:hover {
    animation: glow 2s infinite;
}
</style>
