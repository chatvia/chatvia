<script lang="ts" setup>
import { Link, router, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import isMobile from 'is-mobile';
import { Bot, Check, Download, FileText, FileDown, LogOut, MoreVertical, PanelLeft, Pencil, Edit, PlusCircle, Settings, Trash2, User, X } from 'lucide-vue-next';
import { inject, nextTick, onMounted, ref, watch, computed } from 'vue';

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
import { Button } from '@/components/ui/button';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuSeparator, DropdownMenuSub, DropdownMenuSubContent, DropdownMenuSubTrigger, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { ScrollArea } from '@/components/ui/scroll-area';
import { Separator } from '@/components/ui/separator';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import AppLogoIcon from '@/components/AppLogoIcon.vue';

const props = defineProps({
    open: {
        type: Boolean,
        default: false,
    },
    class: {
        type: String,
        default: '',
    },
    models: {
        type: Array,
        default: () => [],
    },
    defaultModel: {
        type: Object,
        default: null,
    },
    isLoadingModels: {
        type: Boolean,
        default: true,
    },
});

const isMobileDevice = isMobile();
const page = usePage();
const conversations = ref([]);
const chatEventBus = inject('chatEventBus');
const emit = defineEmits(['update:open']);
const isSidebarOpen = ref(props.open);
const defaultModel = ref(props.defaultModel);
const editingId = ref(null);
const editingTitle = ref('');

// Watch for prop changes
watch(() => props.defaultModel, (newDefaultModel) => {
    defaultModel.value = newDefaultModel;
}, { immediate: true });

const loadConversations = () => {
    isLoadingConversations.value = true;

    axios.get(route('conversations.index')).then((response) => {
        console.log('Loading conversations...');
        conversations.value = response.data;
        isLoadingConversations.value = false;
    });
};

watch(
    () => props.open,
    (newValue) => {
        if (newValue !== isSidebarOpen.value) {
            isSidebarOpen.value = newValue;
        }
    },
);

function toggleSidebar() {
    isSidebarOpen.value = !isSidebarOpen.value;
    localStorage.setItem('sidebarOpen', String(isSidebarOpen.value));
    emit('update:open', isSidebarOpen.value);
}

watch(isSidebarOpen, (newValue) => {
    emit('update:open', newValue);
});

const handleLogout = () => {
    router.post(route('logout'));
};
const isLoadingConversations = ref(true);

// Add window width tracking
const windowWidth = ref(window.innerWidth);
const showMobileClose = computed(() => {
    return isMobileDevice || windowWidth.value < 768;
});

onMounted(() => {
    loadConversations();

    if (isMobile()) {
        isSidebarOpen.value = false;
    } else {
        const savedSidebarState = localStorage.getItem('sidebarOpen');
        if (savedSidebarState !== null) {
            isSidebarOpen.value = savedSidebarState === 'true';
        } else {
            isSidebarOpen.value = !isMobileDevice;
            localStorage.setItem('sidebarOpen', String(isSidebarOpen.value));
        }
        emit('update:open', isSidebarOpen.value);
    }

    chatEventBus.onConversationCreated(loadConversations);

    // Update window width on resize
    const handleResize = () => {
        windowWidth.value = window.innerWidth;
    };
    window.addEventListener('resize', handleResize);

    // Clean up on unmount
    return () => {
        window.removeEventListener('resize', handleResize);
    };
});

const newConversation = () => {
    if (isMobileDevice) {
        localStorage.setItem('sidebarOpen', String(false));
        isSidebarOpen.value = false;
    }

    router.visit(route('chat.home', { model: props.defaultModel?.id }), {
        preserveState: false,
        preserveScroll: false,
    });
};

const deleteAllConversations = async () => {
    try {
        await axios.delete(route('conversations.destroy-all'));
        router.reload({ only: ['conversations'] });
    } catch (error) {
        console.error('Failed to delete all conversations:', error);
    }
};

const editConversation = (id) => {
    const conversation = conversations.value.find((chat) => chat.id === id);
    if (conversation) {
        editingId.value = id;
        editingTitle.value = conversation.title;

        // Focus the input field after the DOM updates
        nextTick(() => {
            const inputElement = document.getElementById(`edit-conversation-${id}`);
            if (inputElement) {
                inputElement.focus();
                inputElement.select();
            }
        });
    }
};

const saveEditedConversation = async () => {
    if (!editingId.value || !editingTitle.value.trim()) return;

    try {
        await axios
            .patch(route('conversations.update', { conversationId: editingId.value }), {
                title: editingTitle.value.trim(),
            })
            .then(() => {
                // Update the conversation in the conversations array
                const conversationIndex = conversations.value.findIndex((chat) => chat.id === editingId.value);
                if (conversationIndex > -1) {
                    conversations.value[conversationIndex].title = editingTitle.value.trim();
                }
            });
    } catch (error) {
        console.error('Failed to update conversation title:', error);
    } finally {
        cancelEditing();
    }
};

const cancelEditing = () => {
    editingId.value = null;
    editingTitle.value = '';
};

const handleKeyDown = (event) => {
    if (event.key === 'Enter') {
        saveEditedConversation();
    } else if (event.key === 'Escape') {
        cancelEditing();
    }
};

const exportConversation = async (id, format) => {
    try {
        const response = await axios.get(route('conversations.export', { conversationId: id, format }), {
            responseType: format === 'pdf' ? 'blob' : 'text'
        });

        const conversation = conversations.value.find((chat) => chat.id === id);
        const filename = `${conversation?.title || 'conversation'}.${format}`;

        if (format === 'pdf') {
            // Handle PDF download
            const blob = new Blob([response.data], { type: 'application/pdf' });
            const url = window.URL.createObjectURL(blob);
            const link = document.createElement('a');
            link.href = url;
            link.download = filename;
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            window.URL.revokeObjectURL(url);
        } else {
            // Handle text/markdown download
            const blob = new Blob([response.data], {
                type: format === 'markdown' ? 'text/markdown' : 'text/plain'
            });
            const url = window.URL.createObjectURL(blob);
            const link = document.createElement('a');
            link.href = url;
            link.download = filename;
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            window.URL.revokeObjectURL(url);
        }
    } catch (error) {
        console.error(`Failed to export conversation as ${format}:`, error);
    }
};

const deleteConversation = async (id) => {
    try {
        await axios.delete(route('chat.destroy', { conversationId: id }));
        router.reload({ only: ['conversations'] });
    } catch (error) {
        console.error('Failed to delete conversation:', error);
    }
};
</script>

<template>
    <Button
        v-if="!isSidebarOpen"
        aria-label="Open sidebar"
        class="bg-background/80 fixed top-4 left-4 z-50 shadow-sm backdrop-blur-sm"
        size="icon"
        variant="ghost"
        @click="toggleSidebar"
    >
        <PanelLeft class="h-4 w-4" />
    </Button>

    <!-- Sidebar overlay (mobile only) -->
    <Transition
        name="backdrop"
        enter-active-class="transition-opacity duration-300 ease-out"
        enter-from-class="opacity-0"
        enter-to-class="opacity-100"
        leave-active-class="transition-opacity duration-200 ease-in"
        leave-from-class="opacity-100"
        leave-to-class="opacity-0"
    >
        <div
            v-show="isSidebarOpen && isMobileDevice"
            class="bg-background/80 fixed inset-0 z-40 backdrop-blur-sm"
            @click="toggleSidebar"
        ></div>
    </Transition>

    <div
        :class="[isMobileDevice ? 'sm:max-w-none' : 'relative', isSidebarOpen ? 'translate-x-0' : '-translate-x-full']"
        class="bg-background fixed top-0 left-0 z-40 h-screen w-72 border-r shadow-sm transition-transform duration-300 ease-out"
    >
        <div class="flex h-full flex-col">
            <!-- Header -->
            <div class="ml-4 flex h-16 items-center justify-between">
                <div class="flex items-center gap-2">
                    <AppLogoIcon class="h-20" />
                </div>
                <!-- Close button for mobile only -->
                <button
                    v-if="showMobileClose"
                    aria-label="Close sidebar"
                    class="mr-4 relative z-50 p-2 rounded-md"
                    @click="toggleSidebar"
                >
                    <X class="h-6 w-6 fill-current" />
                </button>
            </div>

            <!-- New Chat Button -->
            <div class="p-4">
                <Button class="w-full justify-start gap-2" variant="default" @click="newConversation">
                    <PlusCircle class="h-4 w-4" />
                    <span>New Chat</span>
                </Button>
            </div>

            <ScrollArea class="flex-1 overflow-y-auto">
                <div class="px-2 py-2">
                    <div class="mb-2 px-2">
                        <h3 class="text-muted-foreground text-xs font-medium">Recent Conversations</h3>
                    </div>

                    <!-- Loading indicator -->
                    <div v-if="isLoadingConversations" class="py-4 text-center">
                        <div class="inline-flex items-center gap-2">
                            <svg
                                class="text-muted-foreground h-4 w-4 animate-spin"
                                fill="none"
                                viewBox="0 0 24 24"
                                xmlns="http://www.w3.org/2000/svg"
                            >
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path
                                    class="opacity-75"
                                    d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                                    fill="currentColor"
                                ></path>
                            </svg>
                            <span class="text-muted-foreground text-xs">Loading conversations...</span>
                        </div>
                    </div>

                    <!-- No conversations message -->
                    <div v-else-if="conversations.length === 0" class="py-4 text-center">
                        <div class="text-muted-foreground text-xs">No conversations yet</div>
                    </div>

                    <TransitionGroup v-else class="space-y-1" name="conversation-list" tag="ul">
                        <li v-for="chat in conversations" :key="chat.id" class="group">
                            <div v-if="editingId !== chat.id">
                                <Link
                                    :class="[
                                        'flex w-full items-center justify-between rounded-md px-3 py-2 text-sm transition-colors',
                                        route().current('chat.show', { conversationId: chat.id })
                                            ? 'bg-accent text-accent-foreground font-medium'
                                            : 'hover:bg-accent/50 text-foreground/80 hover:text-foreground',
                                    ]"
                                    :href="route('chat.show', { conversationId: chat.id, model: defaultModel?.id })"
                                >
                                    <TooltipProvider>
                                        <Tooltip>
                                            <TooltipTrigger asChild>
                                                <span class="min-w-0 flex-grow truncate overflow-hidden">
                                                    {{ chat.title }}
                                                </span>
                                            </TooltipTrigger>
                                            <TooltipContent side="right">
                                                {{ chat.title }}
                                            </TooltipContent>
                                        </Tooltip>
                                    </TooltipProvider>

                                    <DropdownMenu>
                                        <DropdownMenuTrigger asChild>
                                            <Button
                                                aria-label="More options"
                                                class="h-7 w-7 opacity-0 transition-opacity group-hover:opacity-100"
                                                size="icon"
                                                variant="ghost"
                                            >
                                                <MoreVertical class="h-3.5 w-3.5" />
                                            </Button>
                                        </DropdownMenuTrigger>
                                        <DropdownMenuContent align="end" class="w-48">
                                            <DropdownMenuItem @click="editConversation(chat.id)">
                                                <Edit class="mr-2 h-4 w-4" />
                                                <span>Rename</span>
                                            </DropdownMenuItem>
                                            <DropdownMenuSub>
                                                <DropdownMenuSubTrigger>
                                                    <Download class="mr-4 h-4 w-4" />
                                                    <span>Export</span>
                                                </DropdownMenuSubTrigger>
                                                <DropdownMenuSubContent>
                                                    <DropdownMenuItem @click="exportConversation(chat.id, 'pdf')">
                                                        <FileDown class="mr-2 h-4 w-4" />
                                                        <span>PDF</span>
                                                    </DropdownMenuItem>
                                                    <DropdownMenuItem @click="exportConversation(chat.id, 'md')">
                                                        <FileText class="mr-2 h-4 w-4" />
                                                        <span>Markdown</span>
                                                    </DropdownMenuItem>
                                                    <DropdownMenuItem @click="exportConversation(chat.id, 'txt')">
                                                        <Download class="mr-2 h-4 w-4" />
                                                        <span>Text</span>
                                                    </DropdownMenuItem>
                                                </DropdownMenuSubContent>
                                            </DropdownMenuSub>
                                            <DropdownMenuSeparator />
                                            <DropdownMenuItem class="text-destructive focus:text-destructive" @click="deleteConversation(chat.id)">
                                                <Trash2 class="mr-2 h-4 w-4" />
                                                <span>Delete</span>
                                            </DropdownMenuItem>
                                        </DropdownMenuContent>
                                    </DropdownMenu>
                                </Link>
                            </div>

                            <!-- Editing mode -->
                            <div v-else class="bg-accent flex w-full items-center justify-between rounded-md px-3 py-2">
                                <Input
                                    :id="`edit-conversation-${chat.id}`"
                                    v-model="editingTitle"
                                    class="h-7 flex-1"
                                    size="sm"
                                    @blur="saveEditedConversation"
                                    @keydown="handleKeyDown"
                                />
                                <div class="ml-2 flex space-x-1">
                                    <Button
                                        class="hover:bg-background/20 h-7 w-7 text-green-500 hover:text-green-600"
                                        size="icon"
                                        variant="ghost"
                                        @click="saveEditedConversation"
                                    >
                                        <Check class="h-3.5 w-3.5" />
                                    </Button>
                                    <Button
                                        class="text-destructive hover:text-destructive hover:bg-background/20 h-7 w-7"
                                        size="icon"
                                        variant="ghost"
                                        @click="cancelEditing"
                                    >
                                        <X class="h-3.5 w-3.5" />
                                    </Button>
                                </div>
                            </div>
                        </li>
                    </TransitionGroup>
                </div>
            </ScrollArea>

            <!-- Sidebar footer -->
            <div class="space-y-4 border-t p-4">
                <AlertDialog>
                    <AlertDialogTrigger asChild>
                        <Button class="text-destructive border-destructive/20 hover:bg-destructive/10 w-full justify-start gap-2" variant="outline">
                            <Trash2 class="h-4 w-4" />
                            <span>Clear All Chats</span>
                        </Button>
                    </AlertDialogTrigger>
                    <AlertDialogContent>
                        <AlertDialogHeader>
                            <AlertDialogTitle>Delete all conversations?</AlertDialogTitle>
                            <AlertDialogDescription>
                                This action cannot be undone. All your conversations will be permanently deleted.
                            </AlertDialogDescription>
                        </AlertDialogHeader>
                        <AlertDialogFooter>
                            <AlertDialogCancel>Cancel</AlertDialogCancel>
                            <AlertDialogAction
                                class="bg-destructive text-destructive-foreground hover:bg-destructive/90"
                                @click="deleteAllConversations"
                            >
                                Delete All
                            </AlertDialogAction>
                        </AlertDialogFooter>
                    </AlertDialogContent>
                </AlertDialog>

                <Separator />

                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <Avatar class="h-8 w-8">
                            <AvatarImage :src="page.props.auth?.user?.avatar || ''" />
                            <AvatarFallback>{{ page.props.auth?.user?.name?.charAt(0) || 'U' }}</AvatarFallback>
                        </Avatar>
                        <div class="max-w-[140px] truncate text-sm font-medium">
                            {{ page.props.auth?.user?.name || 'User' }}
                        </div>
                    </div>
                    <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                            <Button class="h-8 w-8" size="icon" variant="ghost">
                                <Settings class="h-4 w-4" />
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end">
                            <DropdownMenuItem :href="route('profile.edit')" as="a">
                                <User class="mr-2 h-4 w-4" />
                                <span>Profile</span>
                            </DropdownMenuItem>
                            <DropdownMenuItem :href="route('agents.index')" as="a">
                                <Bot class="mr-2 h-4 w-4" />
                                <span>My Agents</span>
                            </DropdownMenuItem>
                            <DropdownMenuSeparator />

                            <DropdownMenuItem @click="handleLogout">
                                <LogOut class="mr-2 h-4 w-4" />
                                <span>Log out</span>
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
/* Enter and leave animations */
.conversation-list-enter-active,
.conversation-list-leave-active {
    transition: all 0.3s ease;
}

/* Starting and ending states for entering elements */
.conversation-list-enter-from {
    opacity: 0;
    transform: translateX(-20px);
}

/* Starting and ending states for leaving elements */
.conversation-list-leave-to {
    opacity: 0;
    transform: translateX(-20px);
}

/* Fix for space-y with transitions */
.conversation-list-move {
    transition: transform 0.3s ease;
}

/* Ensure items don't collapse during transition */
.conversation-list-leave-active {
    position: absolute;
}
</style>
