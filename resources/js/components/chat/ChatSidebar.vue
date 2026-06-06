<script lang="ts" setup>
import { Link, router, useForm, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import isMobile from 'is-mobile';
import { Bot, Check, Edit, Eye, EyeOff, Loader2, Lock, LogOut, Mail, MoreVertical, PanelLeft, Trash2, Upload, User, X } from 'lucide-vue-next';
import { computed, inject, nextTick, onMounted, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';

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
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuSeparator, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { ScrollArea } from '@/components/ui/scroll-area';
import { Separator } from '@/components/ui/separator'; // TODO: Implement agent switching functionality

import AppLogoIcon from '@/components/AppLogoIcon.vue';
import InputError from '@/components/InputError.vue';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import { getInitials } from '@/composables/useInitials';

interface Conversation {
    id: string | number;
    title: string;
}

interface Agent {
    id: number;
    external_id: string;
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
    appearance: {
        type: Object,
    },
    newChatRoute: {
        type: String,
        default: 'chat.home',
    },
});

const isMobileDevice = isMobile();
const page = usePage();
const conversations = ref<Conversation[]>([]);
const chatEventBus = inject('chatEventBus') as any;
const { t } = useI18n();
const emit = defineEmits(['update:open']);
const isSidebarOpen = ref(props.open);
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
    password_confirmation: '',
});
const showCurrentPassword = ref(false);
const showNewPassword = ref(false);
const showConfirmPassword = ref(false);

// Profile photo state
const currentUser = computed(() => (page.props.auth as any)?.user);
const avatarInput = ref<HTMLInputElement | null>(null);
const photoForm = useForm({
    photo: null as File | null,
});
const removingPhoto = ref(false);
const removePhotoDialogOpen = ref(false);

// Agents dialog state
const agentsDialogOpen = ref(false);
const selectedAgent = ref<Agent | null>(null);
const agents = ref<Agent[]>([]);
const isLoadingAgents = ref(false);

// Agent switching form
const agentSwitchForm = useForm({});

const loadConversations = () => {
    isLoadingConversations.value = true;

    axios.get(route('conversations.index')).then((response) => {
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

    router.visit(route(props.newChatRoute), {
        preserveState: false,
        preserveScroll: false,
    });
};

const deleteAllConversations = async () => {
    try {
        await axios.delete(route('conversations.delete.all'));

        // After clearing all conversations, redirect to chat home
        router.visit(route(props.newChatRoute), {
            preserveState: false,
            preserveScroll: false,
            replace: true,
        });
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

        // If we just deleted the conversation we're currently viewing, send
        // the user back to the agent's home (new-chat) screen. We only care
        // about the `conversationId` param — its presence already means
        // we're on a chat.show-style route; the route name varies between
        // surfaces (regular chat, embed, …).
        const currentConversationId = route().params?.conversationId;

        if (currentConversationId !== undefined && String(currentConversationId) === String(deletedConversationId)) {
            router.visit(route(props.newChatRoute), {
                preserveState: false,
                preserveScroll: false,
                replace: true,
            });
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
    profileForm.name = auth?.user?.name || '';
    profileForm.email = auth?.user?.email || '';
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

const openAvatarPicker = () => {
    if (photoForm.processing) return;
    avatarInput.value?.click();
};

const onAvatarSelected = (event: Event) => {
    const target = event.target as HTMLInputElement;
    const file = target.files?.[0];

    if (!file) return;

    photoForm.photo = file;

    photoForm.patch(route('chat.profile.update'), {
        forceFormData: true,
        preserveState: true,
        preserveScroll: true,
        onFinish: () => {
            photoForm.reset('photo');
            if (avatarInput.value) {
                avatarInput.value.value = '';
            }
        },
    });
};

const confirmRemoveAvatar = () => {
    router.delete(route('chat.profile.photo.destroy'), {
        preserveState: true,
        preserveScroll: true,
        onStart: () => {
            removingPhoto.value = true;
        },
        onFinish: () => {
            removingPhoto.value = false;
            removePhotoDialogOpen.value = false;
        },
    });
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
        },
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
        const response = await axios.get(route('chat.agents.index'));
        agents.value = response.data;

        // Set the current agent as selected based on subdomain
        if (!selectedAgent.value && agents.value.length > 0) {
            const currentSubdomain = getCurrentSubdomain();

            if (currentSubdomain) {
                // Find agent matching current subdomain
                const currentAgent = agents.value.find((agent) => agent.sub_domain === currentSubdomain);
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

    agentSwitchForm.post(
        route('chat.agents.switch', {
            agent: selectedAgent.value.external_id,
        }),
        {
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
            },
        },
    );
};
</script>

<template>
    <Button
        v-if="!isSidebarOpen"
        :aria-label="t('sidebar.open_sidebar')"
        class="bg-background/80 fixed start-4 top-4 z-50 shadow-sm backdrop-blur-sm"
        size="icon"
        variant="ghost"
        @click="toggleSidebar"
    >
        <PanelLeft class="h-4 w-4 rtl:scale-x-[-1]" />
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
        <div v-show="isSidebarOpen && isMobileDevice" class="bg-background/80 fixed inset-0 z-40 backdrop-blur-sm" @click="toggleSidebar"></div>
    </Transition>

    <div
        :class="[isMobileDevice ? 'sm:max-w-none' : 'relative', isSidebarOpen ? 'translate-x-0' : '-translate-x-full rtl:translate-x-full']"
        class="bg-sidebar border-sidebar-border fixed start-0 top-0 z-40 h-screen w-64 border-e transition-transform duration-300 ease-out"
    >
        <div class="flex h-full flex-col px-3 py-3">
            <!-- Header -->
            <div class="mb-2 flex items-center justify-between">
                <Link :href="route('chat.home')" class="hover:bg-accent rounded-lg p-2 transition-colors">
                    <img
                        v-if="props.appearance?.logo"
                        :src="props.appearance.logo"
                        :alt="props.appearance?.chat_window_title || 'ChatVia'"
                        class="h-8 object-contain"
                    />
                    <AppLogoIcon v-else class="h-8 w-8" />
                </Link>
                <!-- Close button for mobile only -->
                <button
                    v-if="showMobileClose"
                    :aria-label="t('common.close')"
                    class="hover:bg-accent rounded-lg p-2 transition-colors"
                    @click="toggleSidebar"
                >
                    <X class="h-5 w-5" />
                </button>
            </div>

            <!-- Menu Items -->
            <nav class="mb-4 space-y-1">
                <button
                    @click="newConversation"
                    class="text-foreground hover:bg-accent flex w-full cursor-pointer items-center gap-3 rounded-lg px-3 py-2.5 text-sm transition-colors"
                >
                    <Edit class="h-5 w-5" />
                    <span>{{ t('sidebar.new_chat') }}</span>
                </button>
            </nav>

            <!-- Chats Section -->
            <div class="flex min-h-0 flex-1 flex-col">
                <div class="flex-shrink-0 px-3 py-2">
                    <span class="text-muted-foreground text-xs font-medium">{{ t('sidebar.your_chats') }}</span>
                </div>

                <ScrollArea class="h-full flex-1">
                    <!-- Loading indicator -->
                    <div v-if="isLoadingConversations" class="flex justify-center py-8">
                        <div class="border-muted-foreground/20 border-t-muted-foreground h-5 w-5 animate-spin rounded-full border-2"></div>
                    </div>

                    <!-- No conversations message -->
                    <div v-else-if="conversations.length === 0" class="px-3 py-8 text-center">
                        <p class="text-muted-foreground/60 text-sm">{{ t('sidebar.no_conversations') }}</p>
                    </div>

                    <!-- Conversations list -->
                    <div v-else class="space-y-0.5 px-1">
                        <div v-for="chat in conversations" :key="chat.id" class="group relative">
                            <div v-if="editingId !== chat.id">
                                <TooltipProvider>
                                    <Tooltip :disabled="chat.title.length < 30">
                                        <TooltipTrigger asChild>
                                            <Link
                                                :class="[
                                                    'flex w-full cursor-pointer items-center rounded-lg px-3 py-2.5 text-sm transition-colors',
                                                    route().current('chat.show', { conversationId: chat.id })
                                                        ? 'text-foreground bg-gray-200 dark:bg-gray-700'
                                                        : 'text-foreground/80 hover:bg-gray-100 dark:hover:bg-gray-800',
                                                ]"
                                                :href="route('chat.show', { conversationId: chat.id })"
                                            >
                                                <span class="flex-1 truncate">{{ chat.title }}</span>
                                            </Link>
                                        </TooltipTrigger>
                                        <TooltipContent v-if="chat.title.length >= 30" side="right" :sideOffset="10">
                                            {{ chat.title }}
                                        </TooltipContent>
                                    </Tooltip>
                                </TooltipProvider>

                                <!-- Hover actions -->
                                <div
                                    class="bg-accent/80 absolute top-1/2 right-1 flex -translate-y-1/2 items-center gap-0.5 rounded-md px-0.5 opacity-0 transition-opacity group-hover:opacity-100"
                                >
                                    <DropdownMenu>
                                        <DropdownMenuTrigger asChild>
                                            <Button aria-label="More options" class="h-7 w-7" size="icon" variant="ghost" @click.prevent.stop>
                                                <MoreVertical class="h-4 w-4" />
                                            </Button>
                                        </DropdownMenuTrigger>
                                        <DropdownMenuContent align="end" class="w-40">
                                            <DropdownMenuItem class="cursor-pointer" @click="editConversation(chat.id)">
                                                <Edit class="mr-2 h-4 w-4" />
                                                <span>{{ t('sidebar.rename_conversation') }}</span>
                                            </DropdownMenuItem>
                                            <DropdownMenuSeparator />
                                            <DropdownMenuItem
                                                class="text-destructive focus:text-destructive cursor-pointer"
                                                @click="openDeleteDialog(chat.id)"
                                            >
                                                <Trash2 class="mr-2 h-4 w-4" />
                                                <span>{{ t('common.delete') }}</span>
                                            </DropdownMenuItem>
                                        </DropdownMenuContent>
                                    </DropdownMenu>
                                </div>
                            </div>

                            <!-- Editing mode -->
                            <div v-else class="bg-accent flex w-full items-center gap-2 rounded-lg px-2 py-1.5">
                                <Input
                                    :id="`edit-conversation-${chat.id}`"
                                    v-model="editingTitle"
                                    class="h-8 flex-1 text-sm"
                                    @blur="saveEditedConversation"
                                    @keydown="handleKeyDown"
                                />
                                <Button
                                    class="h-7 w-7 text-green-600 hover:bg-green-500/10"
                                    size="icon"
                                    variant="ghost"
                                    @click="saveEditedConversation"
                                >
                                    <Check class="h-4 w-4" />
                                </Button>
                                <Button
                                    class="text-muted-foreground hover:text-foreground h-7 w-7"
                                    size="icon"
                                    variant="ghost"
                                    @click="cancelEditing"
                                >
                                    <X class="h-4 w-4" />
                                </Button>
                            </div>
                        </div>
                    </div>
                </ScrollArea>
            </div>

            <!-- Sidebar footer -->
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

                <DropdownMenu v-if="(page.props.auth as any)?.user">
                    <DropdownMenuTrigger asChild>
                        <button
                            class="text-foreground hover:bg-accent flex w-full cursor-pointer items-center gap-3 rounded-lg px-3 py-2.5 text-sm transition-colors"
                        >
                            <Avatar class="h-7 w-7">
                                <AvatarImage :src="(page.props.auth as any)?.user?.avatar || ''" />
                                <AvatarFallback class="text-xs">{{ (page.props.auth as any)?.user?.name?.charAt(0) || 'U' }}</AvatarFallback>
                            </Avatar>
                            <span class="flex-1 truncate text-left">{{ (page.props.auth as any)?.user?.name || 'User' }}</span>
                            <MoreVertical class="text-muted-foreground h-4 w-4" />
                        </button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="start" side="top" class="w-56">
                        <DropdownMenuItem class="cursor-pointer" @click="openProfileDialog">
                            <User class="mr-2 h-4 w-4" />
                            <span>{{ t('sidebar.profile') }}</span>
                        </DropdownMenuItem>
                        <DropdownMenuItem class="cursor-pointer" @click="openAgentsDialog">
                            <Bot class="mr-2 h-4 w-4" />
                            <span>{{ t('sidebar.my_agents') }}</span>
                        </DropdownMenuItem>
                        <DropdownMenuSeparator />
                        <DropdownMenuItem class="cursor-pointer" @click="handleLogout">
                            <LogOut class="mr-2 h-4 w-4" />
                            <span>{{ t('sidebar.logout') }}</span>
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>
            </div>
        </div>
    </div>

    <!-- Delete Conversation Confirmation Dialog -->
    <AlertDialog v-model:open="deleteDialogOpen">
        <AlertDialogContent>
            <AlertDialogHeader>
                <AlertDialogTitle>{{ t('sidebar.delete_conversation_title') }}</AlertDialogTitle>
                <AlertDialogDescription>{{ t('sidebar.delete_conversation_description') }}</AlertDialogDescription>
            </AlertDialogHeader>
            <AlertDialogFooter>
                <AlertDialogCancel @click="cancelDelete">{{ t('common.cancel') }}</AlertDialogCancel>
                <AlertDialogAction class="bg-destructive text-destructive-foreground hover:bg-destructive/90" @click="deleteConversation">
                    {{ t('common.delete') }}
                </AlertDialogAction>
            </AlertDialogFooter>
        </AlertDialogContent>
    </AlertDialog>

    <!-- Remove Profile Photo Confirmation Dialog -->
    <AlertDialog v-model:open="removePhotoDialogOpen">
        <AlertDialogContent>
            <AlertDialogHeader>
                <AlertDialogTitle>{{ t('sidebar.remove_photo_title') }}</AlertDialogTitle>
                <AlertDialogDescription>{{ t('sidebar.remove_photo_description') }}</AlertDialogDescription>
            </AlertDialogHeader>
            <AlertDialogFooter>
                <AlertDialogCancel :disabled="removingPhoto">{{ t('common.cancel') }}</AlertDialogCancel>
                <AlertDialogAction
                    class="bg-destructive text-destructive-foreground hover:bg-destructive/90"
                    :disabled="removingPhoto"
                    @click.prevent="confirmRemoveAvatar"
                >
                    <Loader2 v-if="removingPhoto" class="mr-2 size-4 animate-spin" />
                    {{ t('sidebar.remove_photo') }}
                </AlertDialogAction>
            </AlertDialogFooter>
        </AlertDialogContent>
    </AlertDialog>

    <!-- Profile Dialog -->
    <Dialog v-model:open="profileDialogOpen">
        <DialogContent class="sm:max-w-[600px]">
            <DialogHeader>
                <DialogTitle>{{ t('sidebar.profile_settings_title') }}</DialogTitle>
                <DialogDescription>{{ t('sidebar.profile_settings_description') }}</DialogDescription>
            </DialogHeader>
            <div class="grid gap-6 py-4">
                <div class="flex flex-col items-center gap-2">
                    <button
                        type="button"
                        :disabled="photoForm.processing"
                        :aria-label="currentUser?.avatar ? t('sidebar.change_photo') : t('sidebar.upload_photo')"
                        class="group focus-visible:ring-primary/50 relative cursor-pointer rounded-full focus:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 disabled:cursor-wait"
                        @click="openAvatarPicker"
                    >
                        <Avatar :key="currentUser?.avatar ?? 'no-avatar'" class="border-background size-20 border-2 shadow-sm">
                            <AvatarImage v-if="currentUser?.avatar" :src="currentUser.avatar" :alt="currentUser?.name" />
                            <AvatarFallback class="bg-primary/10 text-primary text-lg font-semibold">
                                {{ getInitials(currentUser?.name || '') }}
                            </AvatarFallback>
                        </Avatar>
                        <div
                            class="pointer-events-none absolute inset-0 flex items-center justify-center rounded-full bg-black/55 text-white opacity-0 transition-opacity duration-200 group-hover:opacity-100 group-focus-visible:opacity-100"
                            :class="{ '!opacity-100': photoForm.processing }"
                        >
                            <Loader2 v-if="photoForm.processing" class="size-5 animate-spin" />
                            <Upload v-else class="size-5" />
                        </div>
                    </button>
                    <input
                        ref="avatarInput"
                        type="file"
                        accept="image/png,image/jpeg,image/webp,image/gif"
                        class="hidden"
                        @change="onAvatarSelected"
                    />
                    <button
                        v-if="currentUser?.avatar"
                        type="button"
                        :disabled="removingPhoto || photoForm.processing"
                        class="text-muted-foreground hover:text-destructive inline-flex cursor-pointer items-center gap-1 text-xs transition-colors disabled:cursor-wait disabled:opacity-60"
                        @click="removePhotoDialogOpen = true"
                    >
                        <Trash2 class="size-3" />
                        {{ t('sidebar.remove_photo') }}
                    </button>
                    <InputError :message="photoForm.errors.photo" class="text-xs" />
                </div>
                <Separator />
                <div class="grid grid-cols-4 items-center gap-4">
                    <Label for="name" class="text-right">{{ t('sidebar.profile_name') }}</Label>
                    <div class="col-span-3 space-y-1">
                        <div class="relative">
                            <User class="text-muted-foreground absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 transform" />
                            <Input id="name" v-model="profileForm.name" class="pl-10" :placeholder="t('sidebar.name_input_placeholder')" />
                        </div>
                        <InputError :message="profileForm.errors.name" class="text-xs" />
                    </div>
                </div>
                <div class="grid grid-cols-4 items-center gap-4">
                    <Label for="email" class="text-right">{{ t('sidebar.profile_email') }}</Label>
                    <div class="relative col-span-3">
                        <Mail class="text-muted-foreground absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 transform" />
                        <Input
                            id="email"
                            v-model="profileForm.email"
                            type="email"
                            class="bg-muted cursor-not-allowed pl-10"
                            :placeholder="t('sidebar.email_input_placeholder')"
                            readonly
                            disabled
                        />
                    </div>
                </div>
                <Separator />
                <div class="grid grid-cols-4 items-center gap-4">
                    <Label for="current-password" class="text-right">{{ t('sidebar.current_password') }}</Label>
                    <div class="col-span-3 space-y-1">
                        <div class="relative">
                            <Lock class="text-muted-foreground absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 transform" />
                            <Input
                                id="current-password"
                                v-model="profileForm.current_password"
                                :type="showCurrentPassword ? 'text' : 'password'"
                                class="pr-10 pl-10"
                                :placeholder="t('sidebar.current_password_placeholder')"
                            />
                            <button
                                type="button"
                                class="text-muted-foreground hover:text-foreground absolute top-1/2 right-3 -translate-y-1/2 transform"
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
                    <Label for="new-password" class="text-right">{{ t('sidebar.new_password') }}</Label>
                    <div class="col-span-3 space-y-1">
                        <div class="relative">
                            <Lock class="text-muted-foreground absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 transform" />
                            <Input
                                id="new-password"
                                v-model="profileForm.password"
                                :type="showNewPassword ? 'text' : 'password'"
                                class="pr-10 pl-10"
                                :placeholder="t('sidebar.new_password_placeholder')"
                            />
                            <button
                                type="button"
                                class="text-muted-foreground hover:text-foreground absolute top-1/2 right-3 -translate-y-1/2 transform"
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
                    <Label for="confirm-password" class="text-right">{{ t('sidebar.confirm_password') }}</Label>
                    <div class="col-span-3 space-y-1">
                        <div class="relative">
                            <Lock class="text-muted-foreground absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 transform" />
                            <Input
                                id="confirm-password"
                                v-model="profileForm.password_confirmation"
                                :type="showConfirmPassword ? 'text' : 'password'"
                                class="pr-10 pl-10"
                                :placeholder="t('sidebar.confirm_password_placeholder')"
                            />
                            <button
                                type="button"
                                class="text-muted-foreground hover:text-foreground absolute top-1/2 right-3 -translate-y-1/2 transform"
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
                <Button variant="outline" @click="closeProfileDialog">{{ t('common.cancel') }}</Button>
                <Button @click="saveProfile" :disabled="profileForm.processing">
                    {{ profileForm.processing ? t('common.saving') : t('sidebar.save_changes') }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>

    <!-- Agents Dialog -->
    <Dialog v-model:open="agentsDialogOpen">
        <DialogContent class="sm:max-w-[600px]">
            <DialogHeader>
                <DialogTitle>{{ t('sidebar.agents_dialog_title') }}</DialogTitle>
                <DialogDescription>{{ t('sidebar.agents_dialog_description') }}</DialogDescription>
            </DialogHeader>
            <ScrollArea class="max-h-[400px] py-4">
                <div class="space-y-4">
                    <div v-if="isLoadingAgents" class="py-8 text-center">
                        <svg
                            class="text-muted-foreground mx-auto mb-4 h-6 w-6 animate-spin"
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
                        <p class="text-muted-foreground text-sm">{{ t('sidebar.loading_agents') }}</p>
                    </div>
                    <div v-else-if="agents.length === 0" class="py-8 text-center">
                        <Bot class="text-muted-foreground mx-auto mb-4 h-12 w-12" />
                        <p class="text-muted-foreground">{{ t('sidebar.no_agents') }}</p>
                    </div>
                    <div v-else class="space-y-3">
                        <div
                            v-for="agent in agents"
                            :key="agent.id"
                            :class="[
                                'flex cursor-pointer items-center justify-between rounded-lg border p-4 transition-colors',
                                selectedAgent?.id === agent.id ? 'border-primary bg-primary/5' : 'hover:bg-accent',
                            ]"
                            role="button"
                            tabindex="0"
                            @click="selectAgent(agent)"
                            @keydown.enter="selectAgent(agent)"
                            @keydown.space.prevent="selectAgent(agent)"
                        >
                            <div class="flex flex-1 items-center gap-3">
                                <div
                                    :class="[
                                        'h-4 w-4 rounded-full border-2',
                                        selectedAgent?.id === agent.id ? 'border-primary bg-primary' : 'border-muted-foreground',
                                    ]"
                                >
                                    <div v-if="selectedAgent?.id === agent.id" class="m-0.5 h-2 w-2 rounded-full bg-white"></div>
                                </div>
                                <div class="flex-1">
                                    <h4 class="font-semibold">{{ agent.name }}</h4>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </ScrollArea>
            <DialogFooter class="flex-col gap-2 sm:flex-row">
                <Button @click="switchToSelectedAgent" :disabled="!selectedAgent || agentSwitchForm.processing" class="w-full sm:w-auto">
                    <svg
                        v-if="agentSwitchForm.processing"
                        class="mr-2 h-4 w-4 animate-spin"
                        fill="none"
                        viewBox="0 0 24 24"
                        xmlns="http://www.w3.org/2000/svg"
                    >
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path
                            class="opacity-75"
                            d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 4 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                            fill="currentColor"
                        ></path>
                    </svg>
                    <Bot v-else class="mr-2 h-4 w-4" />
                    {{
                        agentSwitchForm.processing ? t('sidebar.switching_agent') : t('sidebar.switch_to_agent', { name: selectedAgent?.name || '' })
                    }}
                </Button>
                <Button variant="outline" @click="closeAgentsDialog" class="w-full sm:w-auto">{{ t('common.close') }}</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>

<style scoped>
/* Smooth scrollbar */
:deep(.scroll-area-viewport) {
    scrollbar-width: thin;
    scrollbar-color: hsl(var(--muted-foreground) / 0.2) transparent;
}

:deep(.scroll-area-viewport::-webkit-scrollbar) {
    width: 4px;
}

:deep(.scroll-area-viewport::-webkit-scrollbar-track) {
    background: transparent;
}

:deep(.scroll-area-viewport::-webkit-scrollbar-thumb) {
    background: hsl(var(--muted-foreground) / 0.2);
    border-radius: 4px;
}

:deep(.scroll-area-viewport::-webkit-scrollbar-thumb:hover) {
    background: hsl(var(--muted-foreground) / 0.3);
}
</style>
