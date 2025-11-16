<script lang="ts" setup>
import { computed, onMounted, watch } from 'vue';
import { Head, Link, usePage, router } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarMenuSub,
    SidebarMenuSubButton,
    SidebarMenuSubItem,
    SidebarProvider,
    SidebarTrigger
} from '@/components/ui/sidebar';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger
} from '@/components/ui/dropdown-menu';
import { toast } from 'vue-sonner';
import { Toaster } from '@/components/ui/sonner';
import type { Features } from '@/types';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import UserMenu from '@/components/UserMenu.vue';
import ThemeSwitcher from '@/components/ThemeSwitcher.vue';
import UpgradeButton from '@/components/UpgradeButton.vue';
import AgentSourcesMenu from '@/components/AgentSourcesMenu.vue';

import {
    Bot,
    ChevronRight,
    Clock,
    Database,
    LoaderCircle,
    MessageSquare,
    Palette,
    Trash2,
    Users,
    BarChart3,
    Globe,
    Lock,
    Eye,
    BotIcon,
    Brain,
    Workflow,
    Zap,
    Code,
    FileText,
    Settings,
    Menu,
    UserCheck,
    Edit,
    ChevronDown,
    File,
    Video,
    Music,
    Youtube,
    FileCode,
    Pencil,
    AlertTriangle,
} from 'lucide-vue-next';
import { cn } from '@/lib/utils';
import { ref } from 'vue';
import axios from 'axios';

const props = defineProps({
    agent: {
        type: Object,
        required: true,
    },
    title: {
        type: String,
        default: 'Agent Settings',
    },
    description: {
        type: String,
        default: '',
    }
})

const page = usePage();
const currentRoute = computed(() => page.component);

// Get features from page props
const features = page.props.features as Features;

// Load sidebar state from localStorage
const getSidebarStateFromStorage = (): boolean => {
    const saved = localStorage.getItem('agent-sidebar-open');
    return saved !== null ? saved === 'true' : true; // Default to open
};

const sidebarOpen = ref(getSidebarStateFromStorage());

// Watch for sidebar state changes and save to localStorage
watch(sidebarOpen, (newValue) => {
    localStorage.setItem('agent-sidebar-open', newValue.toString());
});

// Toggle sidebar function
const toggleSidebar = () => {
    sidebarOpen.value = !sidebarOpen.value;
};


// Remove breadcrumbs as we're creating a knowledge_base layout

const uiNavigationItems = computed(() => {
    const baseItems = [];

    // Only show Playground for non-widget agents
    if (props.agent.type !== 'widget') {
        baseItems.push({
            id: 'playground',
            label: 'Playground',
            icon: BotIcon,
            route: 'agents.playground',
            tooltip: 'Test your agent in the playground'
        });
    }

    // Show Widget or Embed based on agent type
    baseItems.push({
        id: 'embed',
        label: props.agent.type === 'widget' ? 'Widget' : 'Embed',
        icon: props.agent.type === 'widget' ? Globe : Code,
        route: props.agent.type === 'widget' ? 'agents.widget' : 'agents.embed',
        tooltip: props.agent.type === 'widget' ? 'Configure and preview chat widget' : 'Generate embed code for your website'
    });

    return baseItems;
});

const controlPanelNavigationItems = computed(() => {
    const baseItems = [
        { id: 'stats', label: 'Statistics', icon: BarChart3, route: 'agents.stats', tooltip: 'View usage statistics and analytics' },
    ];

    if ((page.props.permissions as any)?.persona?.view === true) {
        baseItems.push(
            { id: 'persona', label: 'Persona', icon: Bot, route: 'agents.persona', tooltip: 'Configure agent personality and behavior' },
        )
    }

    baseItems.push(
        { id: 'models', label: 'AI Models', icon: Brain, route: 'agents.models', tooltip: 'Manage AI models and settings' },
    )

    // Always add Sources after models (or after persona if models is not available)
    baseItems.push(
        { id: 'knowledge', label: 'Sources', icon: Database, route: 'agents.sources', tooltip: 'Manage agent knowledge and training data' },
    );

    baseItems.push(
        ...[
            { id: 'history', label: 'History', icon: Clock, route: 'agents.history', tooltip: 'View all agent conversations' },
            { id: 'leads', label: 'Leads', icon: UserCheck, route: 'agents.leads', tooltip: 'Manage leads captured by your agent' },
            { id: 'logs', label: 'Chat Log', icon: MessageSquare, route: 'agents.logs', tooltip: 'Inspect raw chat messages by session' },
        ]
    )

    // Only add members if the agent is not a widget
    if (props.agent.type !== 'widget') {
        baseItems.push({ id: 'team', label: 'Members', icon: Users, route: 'agents.team', tooltip: 'Manage team members and permissions' });
    }

    // Add rename for non-widget agents
    if (props.agent.type !== 'widget') {
        baseItems.push({ id: 'rename', label: 'Rename', icon: Pencil, action: 'rename', tooltip: 'Rename this agent' });
    }

    // Only add workflow if the feature is enabled
    if (features.workflow) {
        // Insert workflow after models (at index 3)
        baseItems.splice(3, 0, { id: 'workflow', label: 'Workflow', icon: Workflow, route: 'agents.workflow', tooltip: 'Create and manage agent workflows' });
    }

    // Only add actions if the feature is enabled
    if (features.actions) {
        // Insert actions after workflow (or after models if workflow is disabled)
        const insertIndex = features.workflow ? 4 : 3;
        baseItems.splice(insertIndex, 0, { id: 'actions', label: 'Sentiment', icon: Zap, route: 'agents.actions', tooltip: 'Configure sentiment analysis and response rules' });
    }

    return baseItems;
});

// Danger Zone navigation items
const dangerZoneNavigationItems = computed(() => {
    return [
        { id: 'delete', label: 'Delete Agent', icon: Trash2, action: 'delete', tooltip: 'Permanently delete this agent' }
    ];
});

// Removed Sources submenu - now handled within the component

const showDeleteDialog = ref(false);
const isDeleting = ref(false);
const agentNameConfirmation = ref('');

const showRenameDialog = ref(false);
const isRenaming = ref(false);
const newAgentName = ref('');

const deleteAgent = () => {
    if (agentNameConfirmation.value !== props.agent.name) {
        return;
    }

    isDeleting.value = true;

    router.delete(route('agents.delete', { agent: props.agent.id }), {
        onSuccess: () => {
            isDeleting.value = false;
            showDeleteDialog.value = false;
            agentNameConfirmation.value = '';
            toast.success('Agent deleted successfully');
        },
        onError: (errors) => {
            isDeleting.value = false;
            toast.error('Failed to delete agent. Please try again.');
        }
    });
};

const renameAgent = () => {
    if (!newAgentName.value.trim()) {
        return;
    }

    isRenaming.value = true;

    router.patch(route('agents.rename', { agent: props.agent.id }), {
        name: newAgentName.value.trim(),
    }, {
        preserveScroll: true,
        onSuccess: () => {
            isRenaming.value = false;
            showRenameDialog.value = false;
            newAgentName.value = '';
            toast.success('Agent renamed successfully');
        },
        onError: (errors) => {
            isRenaming.value = false;
            if (errors.name) {
                toast.error(errors.name[0]);
            } else {
                toast.error('Failed to rename agent. Please try again.');
            }
        }
    });
};

// Watch for rename dialog opening to set default name
watch(showRenameDialog, (newValue) => {
    if (newValue) {
        newAgentName.value = props.agent.name;
    }
});

const canDelete = computed(() => {
    return agentNameConfirmation.value === props.agent.name;
});

const isActiveRoute = (routeName: string) => {
    const routeParts = routeName.split('.'); // Extract parts like ['agents', 'knowledge', 'web']

    // Special handling for knowledge base routes - if checking for agents.knowledge,
    // also match any sub-routes like agents.knowledge.web or agents.knowledge.text
    if (routeName === 'agents.sources') {
        return currentRoute.value.startsWith('agents/Sources');
    }

    // Handle special cases for component naming
    let componentName;
    if (routeName === 'agents.embed') {
        componentName = 'Embed';
    } else if (routeName === 'agents.playground') {
        componentName = 'Playground';
    } else if (routeName === 'agents.settings') {
        componentName = 'Persona';  // Now maps to Persona component file
    } else if (routeName === 'agents.sources') {
        componentName = 'Sources';  // Maps to Sources component file
    } else if (routeName === 'agents.sources.web') {
        componentName = 'Sources/Web';  // Maps to Sources/Web component file
    } else if (routeName === 'agents.sources.text') {
        componentName = 'Sources/Text';  // Maps to Sources/Text component file
    } else if (routeName === 'agents.sources.pdf') {
        componentName = 'Sources/Pdf';  // Maps to Sources/Pdf component file
    } else if (routeName === 'agents.sources.word') {
        componentName = 'Sources/Word';  // Maps to Sources/Word component file
    } else if (routeName === 'agents.sources.video') {
        componentName = 'Sources/Video';  // Maps to Sources/Video component file
    } else if (routeName === 'agents.sources.audio') {
        componentName = 'Sources/Audio';  // Maps to Sources/Audio component file
    } else if (routeName === 'agents.sources.youtube') {
        componentName = 'Sources/Youtube';  // Maps to Sources/Youtube component file
    } else if (routeName === 'agents.sources.markdown') {
        componentName = 'Sources/Markdown';  // Maps to Sources/Markdown component file
    } else if (routeName === 'agents.workflow') {
        componentName = 'Workflow';  // Maps to Workflow component file
    } else if (routeName === 'agents.actions') {
        componentName = 'Sentiment';  // Maps to Sentiment component file
    } else if (routeName === 'agents.leads') {
        componentName = 'Leads';  // Maps to Leads component file
    } else if (routeName === 'agents.logs') {
        componentName = 'ChatLog'; // Maps to ChatLog component file
    } else {
        const routeSuffix = routeParts[routeParts.length - 1]; // Get last part
        componentName = routeSuffix.charAt(0).toUpperCase() + routeSuffix.slice(1);
    }

    return currentRoute.value === `agents/${componentName}`;
};

// Sources submenu state - auto-open if on sources page
const sourcesSubmenuOpen = ref(
    currentRoute.value.startsWith('agents/Sources') ||
    isActiveRoute('agents.sources') ||
    isActiveRoute('agents.sources.web') ||
    isActiveRoute('agents.sources.text') ||
    isActiveRoute('agents.sources.pdf') ||
    isActiveRoute('agents.sources.word') ||
    isActiveRoute('agents.sources.video') ||
    isActiveRoute('agents.sources.audio') ||
    isActiveRoute('agents.sources.youtube') ||
    isActiveRoute('agents.sources.markdown')
);

// Source stats
const sourceStats = ref<any>(null);

// Fetch source stats
const fetchSourceStats = async () => {
    try {
        const response = await axios.get(route('agents.sources.stats', { agent: props.agent.id }));
        sourceStats.value = response.data;
    } catch (error) {
        console.error('Failed to fetch source stats:', error);
    }
};

// Get stat count for a source type
const getSourceCount = (statsKey: string): string => {
    if (!sourceStats.value || !(statsKey in sourceStats.value)) {
        return '';
    }
    const count = sourceStats.value[statsKey] || 0;
    return count > 0 ? ` (${count})` : '';
};

// Fetch stats on mount
onMounted(() => {
    fetchSourceStats();
});

// Sources dropdown state for collapsed sidebar (click-based)
// No need for state management - DropdownMenu handles it internally

</script>

<template>
    <Toaster position="bottom-center" :duration="4000" />
    <Head :title="title" />

    <SidebarProvider v-model:open="sidebarOpen">
        <div class="flex min-h-screen w-full bg-background">
            <!-- Left Sidebar - Full Height -->
            <Sidebar collapsible="icon" class="border-r">
                <!-- Sidebar Header with Logo and Toggle -->
                <div class="border-b border-border">
                    <!-- Expanded State -->
                    <div class="flex items-center justify-between h-16 px-4 group-data-[collapsible=icon]:hidden">
                        <Link
                            :href="route('dashboard')"
                            class="flex items-center gap-2 min-w-0 overflow-hidden"
                        >
                            <div class="h-8 w-8 flex items-center justify-center flex-shrink-0">
                                <AppLogoIcon class="h-8 w-8" />
                            </div>
                        </Link>

                        <!-- Expanded state: Show trigger button on the right -->
                        <SidebarTrigger class="hidden md:flex flex-shrink-0" />
                    </div>

                    <!-- Collapsed State - Full clickable area -->
                    <button
                        @click="toggleSidebar"
                        class="hidden group-data-[collapsible=icon]:flex items-center justify-center w-full h-16 hover:bg-accent transition-colors cursor-pointer"
                        aria-label="Expand sidebar"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4">
                            <rect width="18" height="18" x="3" y="3" rx="2"/>
                            <path d="M9 3v18"/>
                            <path d="m14 9 3 3-3 3"/>
                        </svg>
                    </button>
                </div>

                <!-- Sidebar Content -->
                <SidebarContent class="!gap-6 overflow-y-auto">
                    <!-- Interface Section -->
                    <SidebarGroup class="py-0">
                        <SidebarGroupLabel class="mb-2 px-2">Interface</SidebarGroupLabel>
                        <SidebarMenu class="space-y-1">
                            <SidebarMenuItem v-for="item in uiNavigationItems" :key="item.id">
                                <SidebarMenuButton
                                    as-child
                                    :is-active="isActiveRoute(item.route)"
                                    :tooltip="item.tooltip"
                                    class="data-[active=true]:bg-neutral-900 data-[active=true]:text-white dark:data-[active=true]:bg-neutral-100 dark:data-[active=true]:text-neutral-900 data-[active=true]:font-semibold [&[data-active=true]_svg]:text-white dark:[&[data-active=true]_svg]:text-neutral-900"
                                >
                                    <Link :href="route(item.route, { agent: agent.id })">
                                        <component :is="item.icon" />
                                        <span>{{ item.label }}</span>
                                    </Link>
                                </SidebarMenuButton>
                            </SidebarMenuItem>
                        </SidebarMenu>
                    </SidebarGroup>

                    <!-- Management Section -->
                    <SidebarGroup class="py-0">
                        <SidebarGroupLabel class="mb-2 px-2">Management</SidebarGroupLabel>
                        <SidebarMenu class="space-y-1">
                            <template v-for="item in controlPanelNavigationItems" :key="item.id">
                                <!-- Sources item with submenu -->
                                <SidebarMenuItem v-if="item.id === 'knowledge'">
                                    <!-- Collapsed state - show dropdown on click -->
                                    <DropdownMenu>
                                        <DropdownMenuTrigger as-child>
                                            <div class="hidden group-data-[collapsible=icon]:block">
                                                <SidebarMenuButton
                                                    :tooltip="item.tooltip"
                                                    class="cursor-pointer"
                                                >
                                                    <component :is="item.icon" />
                                                    <span>{{ item.label }}</span>
                                                </SidebarMenuButton>
                                            </div>
                                        </DropdownMenuTrigger>
                                        <DropdownMenuContent
                                            side="right"
                                            align="start"
                                            :side-offset="5"
                                            :align-offset="0"
                                            class="w-56"
                                        >
                                            <AgentSourcesMenu
                                                :agent-id="agent.id"
                                                :source-stats="sourceStats"
                                                variant="dropdown"
                                                :is-active-route="isActiveRoute"
                                            >
                                                <template #default="{ item, label }">
                                                    <DropdownMenuItem as-child>
                                                        <Link
                                                            :href="route(item.route, { agent: agent.id })"
                                                            class="flex items-center gap-2"
                                                        >
                                                            <component :is="item.icon" class="h-4 w-4" />
                                                            <span>{{ label }}</span>
                                                        </Link>
                                                    </DropdownMenuItem>
                                                </template>
                                            </AgentSourcesMenu>
                                        </DropdownMenuContent>
                                    </DropdownMenu>

                                    <!-- Expanded state - show regular submenu -->
                                    <div class="block group-data-[collapsible=icon]:hidden">
                                        <SidebarMenuButton
                                            @click="sourcesSubmenuOpen = !sourcesSubmenuOpen"
                                            :tooltip="item.tooltip"
                                            class="cursor-pointer"
                                        >
                                            <component :is="item.icon" />
                                            <span>{{ item.label }}</span>
                                            <ChevronDown
                                                class="ml-auto h-4 w-4 transition-transform duration-200"
                                                :class="{ 'rotate-180': sourcesSubmenuOpen }"
                                            />
                                        </SidebarMenuButton>
                                    </div>
                                    <SidebarMenuSub v-if="sourcesSubmenuOpen" class="mt-1 space-y-0.5 group-data-[collapsible=icon]:hidden">
                                        <AgentSourcesMenu
                                            :agent-id="agent.id"
                                            :source-stats="sourceStats"
                                            variant="submenu"
                                            :is-active-route="isActiveRoute"
                                        >
                                            <template #default="{ item, label, isActive }">
                                                <SidebarMenuSubItem>
                                                    <SidebarMenuSubButton
                                                        as-child
                                                        :is-active="isActive"
                                                        class="data-[active=true]:bg-neutral-900 data-[active=true]:text-white dark:data-[active=true]:bg-neutral-100 dark:data-[active=true]:text-neutral-900 data-[active=true]:font-semibold [&[data-active=true]_svg]:text-white dark:[&[data-active=true]_svg]:text-neutral-900"
                                                    >
                                                        <Link :href="route(item.route, { agent: agent.id })">
                                                            <component :is="item.icon" class="h-4 w-4" />
                                                            <span>{{ label }}</span>
                                                        </Link>
                                                    </SidebarMenuSubButton>
                                                </SidebarMenuSubItem>
                                            </template>
                                        </AgentSourcesMenu>
                                    </SidebarMenuSub>
                                </SidebarMenuItem>
                                <!-- Regular menu items -->
                                <SidebarMenuItem v-else>
                                    <!-- Action items (like rename) -->
                                    <SidebarMenuButton
                                        v-if="item.action === 'rename'"
                                        @click="showRenameDialog = true"
                                        :tooltip="item.tooltip"
                                        class="cursor-pointer hover:bg-neutral-100 dark:hover:bg-neutral-800"
                                    >
                                        <component :is="item.icon" />
                                        <span>{{ item.label }}</span>
                                    </SidebarMenuButton>
                                    <!-- Route items -->
                                    <SidebarMenuButton
                                        v-else
                                        as-child
                                        :is-active="isActiveRoute(item.route)"
                                        :tooltip="item.tooltip"
                                        class="data-[active=true]:bg-neutral-900 data-[active=true]:text-white dark:data-[active=true]:bg-neutral-100 dark:data-[active=true]:text-neutral-900 data-[active=true]:font-semibold [&[data-active=true]_svg]:text-white dark:[&[data-active=true]_svg]:text-neutral-900"
                                    >
                                        <Link :href="route(item.route, { agent: agent.id })">
                                            <component :is="item.icon" />
                                            <span>{{ item.label }}</span>
                                        </Link>
                                    </SidebarMenuButton>
                                </SidebarMenuItem>
                            </template>
                        </SidebarMenu>
                    </SidebarGroup>

                    <!-- Separator before Danger Zone -->
                    <div class="px-2 py-2">
                        <div class="border-t border-border"></div>
                    </div>

                    <!-- Danger Zone Section -->
                    <SidebarGroup class="py-0">
                        <SidebarGroupLabel class="mb-2 px-2 text-destructive">Danger Zone</SidebarGroupLabel>
                        <SidebarMenu class="space-y-1">
                            <SidebarMenuItem v-for="item in dangerZoneNavigationItems" :key="item.id">
                                <SidebarMenuButton
                                    @click="showDeleteDialog = true"
                                    :tooltip="item.tooltip"
                                    class="cursor-pointer text-destructive hover:text-destructive hover:bg-destructive/10"
                                >
                                    <component :is="item.icon" />
                                    <span>{{ item.label }}</span>
                                </SidebarMenuButton>
                            </SidebarMenuItem>
                        </SidebarMenu>
                    </SidebarGroup>
                </SidebarContent>
            </Sidebar>

            <!-- Main Content Area - Full Height with Header Inside -->
            <div class="flex flex-1 flex-col">
                <!-- Top Navigation Bar -->
                <header class="bg-background border-b border-border sticky top-0 z-40">
                    <div class="px-4 lg:px-6">
                        <div class="flex items-center justify-between h-16">
                            <!-- Left side - Breadcrumbs -->
                            <div class="flex items-center gap-4">
                                <!-- Mobile sidebar toggle -->
                                <SidebarTrigger class="md:hidden" />

                                <!-- Breadcrumbs -->
                                <div class="flex items-center gap-2 text-sm">
                                    <Link :href="route('dashboard')" class="text-muted-foreground hover:text-foreground transition-colors hidden md:inline">
                                        Dashboard
                                    </Link>
                                    <ChevronRight class="h-4 w-4 text-muted-foreground hidden md:block" />
                                    <Link :href="route('agents.index')" class="text-muted-foreground hover:text-foreground transition-colors hidden md:inline">
                                        Agents
                                    </Link>
                                    <ChevronRight class="h-4 w-4 text-muted-foreground hidden md:block" />
                                    <span class="text-foreground font-medium">{{ agent.name }}</span>
                                </div>
                            </div>

                            <!-- Right side - Actions and user menu -->
                            <div class="flex items-center gap-3">
                                <!-- Utilities: Upgrade, Theme, User -->
                                <div class="hidden lg:flex items-center gap-2">
                                    <UpgradeButton />
                                    <ThemeSwitcher />
                                    <UserMenu />
                                </div>
                            </div>
                        </div>
                    </div>
                </header>

                <!-- Page Content Wrapper -->
                <div class="flex-1 flex flex-col overflow-hidden">
                    <!-- Page Header -->
                    <div class="bg-background border-b border-border px-4 lg:px-6 py-4">
                        <div class="flex items-center justify-between">
                            <div class="min-w-0 flex-1">
                                <h1 class="text-lg lg:text-xl font-semibold text-foreground truncate flex items-center gap-2">
                                    <slot name="header-icon" />
                                    {{ title }}
                                </h1>
                                <p v-if="description" class="text-xs lg:text-sm text-muted-foreground mt-1 hidden sm:block">
                                    {{ description }}
                                </p>
                            </div>

                            <div class="flex gap-2 lg:gap-4 flex-shrink-0">
                                <div class="flex items-center gap-2">
                                    <slot name="header-actions" />
                                </div>

                                <Badge
                                    v-if="agent.type === 'knowledge_base'"
                                    :variant="agent.is_public ? 'destructive' : 'secondary'"
                                    :class="[
                                        'text-xs',
                                        !agent.is_public ? 'bg-green-100 text-green-700 border-green-200 dark:bg-green-900/20 dark:text-green-400 dark:border-green-800' : ''
                                    ]"
                                >
                                    <component
                                        :is="agent.is_public ? Eye : Lock"
                                        class="h-3 w-3"
                                    />
                                </Badge>
                            </div>
                        </div>
                    </div>

                    <!-- Page Content -->
                    <div class="flex-1 overflow-auto bg-muted/30">
                        <div class="p-3 sm:p-4 lg:p-6">
                            <slot />
                        </div>
                    </div>
                </div>
            </div>
        </div>


        <!-- Delete Agent Dialog -->
        <Dialog v-model:open="showDeleteDialog">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Delete Agent</DialogTitle>
                    <DialogDescription>
                        This action cannot be undone. This will permanently delete the agent
                        <strong>{{ agent.name }}</strong> and all of its data.
                    </DialogDescription>
                </DialogHeader>

                <div class="space-y-4">
                    <div>
                        <label for="agent-name-confirmation" class="block text-sm font-medium text-foreground mb-2">
                            Please type <strong>{{ agent.name }}</strong> to confirm
                        </label>
                        <input
                            id="agent-name-confirmation"
                            v-model="agentNameConfirmation"
                            type="text"
                            class="w-full px-3 py-2 border border-input rounded-md bg-background text-foreground placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-ring focus:border-transparent"
                            placeholder="Enter agent name to confirm"
                            :class="{
                                'border-red-500 focus:ring-red-500': agentNameConfirmation && !canDelete,
                                'border-green-500 focus:ring-green-500': canDelete
                            }"
                        />
                        <p v-if="agentNameConfirmation && !canDelete" class="mt-1 text-sm text-red-500">
                            Agent name does not match
                        </p>
                    </div>
                </div>

                <DialogFooter>
                    <Button variant="outline" @click="showDeleteDialog = false">
                        Cancel
                    </Button>
                    <Button
                        variant="destructive"
                        @click="deleteAgent"
                        :disabled="isDeleting || !canDelete"
                    >
                        <LoaderCircle v-if="isDeleting" class="mr-2 h-4 w-4 animate-spin" />
                        Delete Agent
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Rename Agent Dialog -->
        <Dialog v-model:open="showRenameDialog">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Rename Agent</DialogTitle>
                    <DialogDescription>
                        Enter a new name for your agent <strong>{{ agent.name }}</strong>.
                    </DialogDescription>
                </DialogHeader>

                <div class="space-y-4">
                    <div>
                        <label for="new-agent-name" class="block text-sm font-medium text-foreground mb-2">
                            Agent name
                        </label>
                        <input
                            id="new-agent-name"
                            v-model="newAgentName"
                            type="text"
                            class="w-full px-3 py-2 border border-input rounded-md bg-background text-foreground placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-ring focus:border-transparent"
                            placeholder="Enter new agent name"
                            @keyup.enter="renameAgent"
                        />
                    </div>
                </div>

                <DialogFooter>
                    <Button variant="outline" @click="showRenameDialog = false">
                        Cancel
                    </Button>
                    <Button
                        @click="renameAgent"
                        :disabled="isRenaming || !newAgentName.trim()"
                    >
                        <LoaderCircle v-if="isRenaming" class="mr-2 h-4 w-4 animate-spin" />
                        Rename Agent
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </SidebarProvider>
</template>

<style scoped>
/* Custom scrollbar for navigation */
nav::-webkit-scrollbar {
    width: 6px;
}

nav::-webkit-scrollbar-track {
    background: transparent;
}

nav::-webkit-scrollbar-thumb {
    background-color: rgba(156, 163, 175, 0.5);
    border-radius: 3px;
}

nav::-webkit-scrollbar-thumb:hover {
    background-color: rgba(156, 163, 175, 0.7);
}
</style>


