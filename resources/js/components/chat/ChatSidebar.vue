<script lang="ts" setup>
import { Link, router, usePage, useForm } from '@inertiajs/vue3';
import axios from 'axios';
import isMobile from 'is-mobile';
import { Bot, Check, Download, FileText, FileDown, LogOut, MoreVertical, PanelLeft, Edit, Settings, Trash2, User, X, Eye, EyeOff, Mail, Lock, PlusCircle } from 'lucide-vue-next';
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
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuSeparator, DropdownMenuSub, DropdownMenuSubContent, DropdownMenuSubTrigger, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { ScrollArea } from '@/components/ui/scroll-area';
import { Separator } from '@/components/ui/separator';    // TODO: Implement agent switching functionality

import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import InputError from '@/components/InputError.vue';

interface Conversation {
    id: string | number;
    title: string;
}

interface Agent {
    id: number;
    name: string;
    sub_domain: string;
    url: string;
    chat_url: string;
}

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
    appearance: {
        type: Object,
    },
    newChatRoute: {
        type: String,
        default: 'chat.home'
    }
});

const isMobileDevice = isMobile();
const page = usePage();
const conversations = ref<Conversation[]>([]);
const chatEventBus = inject('chatEventBus') as any;
const emit = defineEmits(['update:open']);
const isSidebarOpen = ref(props.open);
const defaultModel = ref(props.defaultModel);
const editingId = ref<string | number | null>(null);
const editingTitle = ref('');
const deleteDialogOpen = ref(false);
const conversationToDelete = ref<string | number | null>(null);

// Profile dialog state
const profileDialogOpen = ref(false);
const profileForm = useForm({
    name: '',
    email: '',
    current_password: '',
    password: '',
    password_confirmation: ''
});
const showCurrentPassword = ref(false);
const showNewPassword = ref(false);
const showConfirmPassword = ref(false);

// Agents dialog state
const agentsDialogOpen = ref(false);
const selectedAgent = ref<Agent | null>(null);
const agents = ref<Agent[]>([]);
const isLoadingAgents = ref(false);

// Agent switching form
const agentSwitchForm = useForm({});


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
    router.post(route('chat.logout'));
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

    router.visit(route(props.newChatRoute, { model: props.defaultModel?.id }), {
        preserveState: false,
        preserveScroll: false,
    });
};

const deleteAllConversations = async () => {
    try {
        await axios.delete(route('conversations.delete.all'));

        // After clearing all conversations, redirect to chat home
        router.visit(route(props.newChatRoute, { model: defaultModel.value?.id }));
    } catch (error) {
        console.error('Failed to delete all conversations:', error);
    }
};

const editConversation = (id: string | number) => {
    const conversation = conversations.value.find((chat) => chat.id === id);
    if (conversation) {
        editingId.value = id;
        editingTitle.value = conversation.title;

        // Focus the input field after the DOM updates
        nextTick(() => {
            const inputElement = document.getElementById(`edit-conversation-${id}`) as HTMLInputElement;
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

const handleKeyDown = (event: KeyboardEvent) => {
    if (event.key === 'Enter') {
        saveEditedConversation();
    } else if (event.key === 'Escape') {
        cancelEditing();
    }
};

const exportConversation = async (id: string | number, format: string) => {
    try {
        const url = route('conversations.export', {
            conversationId: id,
            type: format,
        })
        window.open(url, '_blank')
    } catch (error) {
        console.error('Failed to export', error)
    }

};

const openDeleteDialog = (id: string | number) => {
    conversationToDelete.value = id;
    deleteDialogOpen.value = true;
};

const deleteConversation = async () => {
    if (!conversationToDelete.value) return;

    try {
        const deletedConversationId = conversationToDelete.value;
        await axios.delete(route('conversations.delete', { conversationId: deletedConversationId }));

        // Refresh the conversations list after successful deletion
        loadConversations();

        // Check if we're currently viewing the conversation we just deleted
        const currentRoute = route().current();
        const currentConversationId = route().params?.conversationId;

        if (currentRoute === props.newChatRoute && currentConversationId === deletedConversationId) {
            // Redirect to chat home if we deleted the current conversation
            router.visit(route(props.newChatRoute, { model: defaultModel.value?.id }));
        }
    } catch (error) {
        console.error('Failed to delete conversation:', error);
    } finally {
        deleteDialogOpen.value = false;
        conversationToDelete.value = null;
    }
};

const cancelDelete = () => {
    deleteDialogOpen.value = false;
    conversationToDelete.value = null;
};

// Profile dialog functions
const openProfileDialog = () => {
    const auth = page.props.auth as any;
    profileForm.name = auth?.member?.name || '';
    profileForm.email = auth?.member?.email || '';
    profileForm.current_password = '';
    profileForm.password = '';
    profileForm.password_confirmation = '';
    profileDialogOpen.value = true;
};

const closeProfileDialog = () => {
    profileDialogOpen.value = false;
    showCurrentPassword.value = false;
    showNewPassword.value = false;
    showConfirmPassword.value = false;
};

const saveProfile = () => {
    profileForm.patch(route('chat.profile.update'), {
        preserveState: true,
        preserveScroll: true,
        onSuccess: () => {
            closeProfileDialog();
            // Clear password fields
            profileForm.current_password = '';
            profileForm.password = '';
            profileForm.password_confirmation = '';
        },
        onError: () => {
            // Inertia will handle validation errors automatically
        }
    });
};

const getCurrentSubdomain = () => {
    if (typeof window === 'undefined') return null;

    const hostname = window.location.hostname;
    const parts = hostname.split('.');

    // Return the subdomain if it exists (e.g., "translation" from "translation.chatvia.test")
    return parts.length > 2 ? parts[0] : null;
};

const loadAgents = async () => {
    if (isLoadingAgents.value) return;

    isLoadingAgents.value = true;
    try {
        const response = await axios.get(route('chat.member.agents'));
        agents.value = response.data;

        // Set the current agent as selected based on subdomain
        if (!selectedAgent.value && agents.value.length > 0) {
            const currentSubdomain = getCurrentSubdomain();

            if (currentSubdomain) {
                // Find agent matching current subdomain
                const currentAgent = agents.value.find(agent => agent.sub_domain === currentSubdomain);
                selectedAgent.value = currentAgent || agents.value[0];
            } else {
                // Fallback to first agent if no subdomain
                selectedAgent.value = agents.value[0];
            }
        }
    } catch (error) {
        console.error('Failed to load agents:', error);
    } finally {
        isLoadingAgents.value = false;
    }
};

// Agents dialog functions
const openAgentsDialog = () => {
    loadAgents();
    agentsDialogOpen.value = true;
};

const closeAgentsDialog = () => {
    agentsDialogOpen.value = false;
};

const selectAgent = (agent: Agent) => {
    selectedAgent.value = agent;
    // TODO: Implement agent switching functionality
    console.log('Switching to agent:', agent.name);
};

const switchToSelectedAgent = () => {
    if (!selectedAgent.value || agentSwitchForm.processing) return;

    console.log('Sending switch request for agent:', selectedAgent.value.id);

    agentSwitchForm.post(route('chat.member.agents.switch', {
        agent: selectedAgent.value.id
    }), {
        preserveState: true,
        preserveScroll: true,
        onSuccess: () => {
            console.log('Successfully switched to agent:', selectedAgent.value?.name);
            closeAgentsDialog();
        },
        onError: (errors) => {
            console.error('Failed to switch agent:', errors);
        },
        onFinish: () => {
            console.log('Agent switch request completed');
        }
    });
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
                    <Link :href="route('chat.home')">
                        <img v-if="props.appearance.logo"
                             :src="props.appearance.logo"
                             :alt="props.appearance.chat_window_title || 'ChatVia'"
                             class="h-15 object-cover text-red"
                        />
                        <AppLogoIcon v-else class="h-10" />
                    </Link>
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
                                            <DropdownMenuItem class="text-destructive focus:text-destructive" @click="openDeleteDialog(chat.id)">
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

                <template v-if="(page.props.auth as any)?.member">
                    <Separator/>
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <Avatar class="h-8 w-8">
                                <AvatarImage :src="(page.props.auth as any)?.member?.avatar || ''"/>
                                <AvatarFallback>{{ (page.props.auth as any)?.member?.name?.charAt(0) || 'U' }}</AvatarFallback>
                            </Avatar>
                            <div class="max-w-[140px] truncate text-sm font-medium">
                                {{ (page.props.auth as any)?.member?.name || 'User' }}
                            </div>
                        </div>
                        <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                                <Button class="h-8 w-8" size="icon" variant="ghost">
                                    <Settings class="h-4 w-4"/>
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end">
                                <DropdownMenuItem @click="openProfileDialog">
                                    <User class="mr-2 h-4 w-4"/>
                                    <span>Profile</span>
                                </DropdownMenuItem>
                                <DropdownMenuItem @click="openAgentsDialog">
                                    <Bot class="mr-2 h-4 w-4"/>
                                    <span>My Agents</span>
                                </DropdownMenuItem>
                                <DropdownMenuSeparator/>

                                <DropdownMenuItem @click="handleLogout">
                                    <LogOut class="mr-2 h-4 w-4"/>
                                    <span>Log out</span>
                                </DropdownMenuItem>
                            </DropdownMenuContent>
                        </DropdownMenu>
                    </div>
                </template>
            </div>
        </div>
    </div>

    <!-- Delete Conversation Confirmation Dialog -->
    <AlertDialog v-model:open="deleteDialogOpen">
        <AlertDialogContent>
            <AlertDialogHeader>
                <AlertDialogTitle>Delete Conversation</AlertDialogTitle>
                <AlertDialogDescription>
                    Are you sure you want to delete this conversation? This action cannot be undone.
                </AlertDialogDescription>
            </AlertDialogHeader>
            <AlertDialogFooter>
                <AlertDialogCancel @click="cancelDelete">Cancel</AlertDialogCancel>
                <AlertDialogAction
                    class="bg-destructive text-destructive-foreground hover:bg-destructive/90"
                    @click="deleteConversation"
                >
                    Delete
                </AlertDialogAction>
            </AlertDialogFooter>
        </AlertDialogContent>
    </AlertDialog>

    <!-- Profile Dialog -->
    <Dialog v-model:open="profileDialogOpen">
        <DialogContent class="sm:max-w-[600px]">
            <DialogHeader>
                <DialogTitle>Profile Settings</DialogTitle>
                <DialogDescription>
                    Update your account information and password.
                </DialogDescription>
            </DialogHeader>
            <div class="grid gap-6 py-4">
                <div class="grid grid-cols-4 items-center gap-4">
                    <Label for="name" class="text-right">Name</Label>
                    <div class="col-span-3 space-y-1">
                        <div class="relative">
                            <User class="absolute left-3 top-1/2 transform -translate-y-1/2 h-4 w-4 text-muted-foreground" />
                            <Input id="name" v-model="profileForm.name" class="pl-10" placeholder="Enter your name" />
                        </div>
                        <InputError :message="profileForm.errors.name" class="text-xs" />
                    </div>
                </div>
                <div class="grid grid-cols-4 items-center gap-4">
                    <Label for="email" class="text-right">Email</Label>
                    <div class="col-span-3 relative">
                        <Mail class="absolute left-3 top-1/2 transform -translate-y-1/2 h-4 w-4 text-muted-foreground" />
                        <Input
                            id="email"
                            v-model="profileForm.email"
                            type="email"
                            class="pl-10 bg-muted cursor-not-allowed"
                            placeholder="Enter your email"
                            readonly
                            disabled
                        />
                    </div>
                </div>
                <Separator />
                <div class="grid grid-cols-4 items-center gap-4">
                    <Label for="current-password" class="text-right">Current Password</Label>
                    <div class="col-span-3 space-y-1">
                        <div class="relative">
                            <Lock class="absolute left-3 top-1/2 transform -translate-y-1/2 h-4 w-4 text-muted-foreground" />
                            <Input
                                id="current-password"
                                v-model="profileForm.current_password"
                                :type="showCurrentPassword ? 'text' : 'password'"
                                class="pl-10 pr-10"
                                placeholder="Enter current password"
                            />
                            <button
                                type="button"
                                class="absolute right-3 top-1/2 transform -translate-y-1/2 text-muted-foreground hover:text-foreground"
                                @click="showCurrentPassword = !showCurrentPassword"
                            >
                                <Eye v-if="!showCurrentPassword" class="h-4 w-4" />
                                <EyeOff v-else class="h-4 w-4" />
                            </button>
                        </div>
                        <InputError :message="profileForm.errors.current_password" class="text-xs" />
                    </div>
                </div>
                <div class="grid grid-cols-4 items-center gap-4">
                    <Label for="new-password" class="text-right">New Password</Label>
                    <div class="col-span-3 space-y-1">
                        <div class="relative">
                            <Lock class="absolute left-3 top-1/2 transform -translate-y-1/2 h-4 w-4 text-muted-foreground" />
                            <Input
                                id="new-password"
                                v-model="profileForm.password"
                                :type="showNewPassword ? 'text' : 'password'"
                                class="pl-10 pr-10"
                                placeholder="Enter new password"
                            />
                            <button
                                type="button"
                                class="absolute right-3 top-1/2 transform -translate-y-1/2 text-muted-foreground hover:text-foreground"
                                @click="showNewPassword = !showNewPassword"
                            >
                                <Eye v-if="!showNewPassword" class="h-4 w-4" />
                                <EyeOff v-else class="h-4 w-4" />
                            </button>
                        </div>
                        <InputError :message="profileForm.errors.password" class="text-xs" />
                    </div>
                </div>
                <div class="grid grid-cols-4 items-center gap-4">
                    <Label for="confirm-password" class="text-right">Confirm Password</Label>
                    <div class="col-span-3 space-y-1">
                        <div class="relative">
                            <Lock class="absolute left-3 top-1/2 transform -translate-y-1/2 h-4 w-4 text-muted-foreground" />
                            <Input
                                id="confirm-password"
                                v-model="profileForm.password_confirmation"
                                :type="showConfirmPassword ? 'text' : 'password'"
                                class="pl-10 pr-10"
                                placeholder="Confirm new password"
                            />
                            <button
                                type="button"
                                class="absolute right-3 top-1/2 transform -translate-y-1/2 text-muted-foreground hover:text-foreground"
                                @click="showConfirmPassword = !showConfirmPassword"
                            >
                                <Eye v-if="!showConfirmPassword" class="h-4 w-4" />
                                <EyeOff v-else class="h-4 w-4" />
                            </button>
                        </div>
                        <InputError :message="profileForm.errors.password_confirmation" class="text-xs" />
                    </div>
                </div>
            </div>
            <DialogFooter>
                <Button variant="outline" @click="closeProfileDialog">Cancel</Button>
                <Button @click="saveProfile" :disabled="profileForm.processing">
                    {{ profileForm.processing ? 'Saving...' : 'Save Changes' }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>

    <!-- Agents Dialog -->
    <Dialog v-model:open="agentsDialogOpen">
        <DialogContent class="sm:max-w-[600px]">
            <DialogHeader>
                <DialogTitle>My Agents</DialogTitle>
                <DialogDescription>
                    Manage your AI agents and their configurations.
                </DialogDescription>
            </DialogHeader>
            <ScrollArea class="max-h-[400px] py-4">
                                <div class="space-y-4">
                    <div v-if="isLoadingAgents" class="text-center py-8">
                        <svg class="h-6 w-6 mx-auto animate-spin text-muted-foreground mb-4" fill="none" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" fill="currentColor"></path>
                        </svg>
                        <p class="text-muted-foreground text-sm">Loading agents...</p>
                    </div>
                    <div v-else-if="agents.length === 0" class="text-center py-8">
                        <Bot class="h-12 w-12 mx-auto text-muted-foreground mb-4" />
                        <p class="text-muted-foreground">No agents found.</p>
                    </div>
                    <div v-else class="space-y-3">
                        <div v-for="agent in agents" :key="agent.id"
                             :class="[
                                 'flex items-center justify-between rounded-lg border p-4 cursor-pointer transition-colors',
                                 selectedAgent?.id === agent.id ? 'border-primary bg-primary/5' : 'hover:bg-accent'
                             ]"
                             @click="selectAgent(agent)">
                            <div class="flex items-center gap-3 flex-1">
                                <div :class="[
                                    'w-4 h-4 rounded-full border-2',
                                    selectedAgent?.id === agent.id
                                        ? 'border-primary bg-primary'
                                        : 'border-muted-foreground'
                                ]">
                                    <div v-if="selectedAgent?.id === agent.id" class="w-2 h-2 bg-white rounded-full m-0.5"></div>
                                </div>
                                <div class="flex-1">
                                    <h4 class="font-semibold">{{ agent.name }}</h4>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </ScrollArea>
                         <DialogFooter class="flex-col sm:flex-row gap-2">
                                 <Button
                    @click="switchToSelectedAgent"
                    :disabled="!selectedAgent || agentSwitchForm.processing"
                    class="w-full sm:w-auto">
                    <svg v-if="agentSwitchForm.processing" class="h-4 w-4 mr-2 animate-spin" fill="none" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 4 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" fill="currentColor"></path>
                    </svg>
                    <Bot v-else class="h-4 w-4 mr-2" />
                    {{ agentSwitchForm.processing ? 'Switching...' : `Switch to ${selectedAgent?.name || 'Selected Agent'}` }}
                </Button>
                 <Button variant="outline" @click="closeAgentsDialog" class="w-full sm:w-auto">Close</Button>
             </DialogFooter>
        </DialogContent>
    </Dialog>
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
