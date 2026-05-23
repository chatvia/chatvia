<script lang="ts" setup>
import AgentSourcesMenu from '@/components/AgentSourcesMenu.vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import ThemeSwitcher from '@/components/ThemeSwitcher.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import {
    Sidebar,
    SidebarContent,
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarMenuSub,
    SidebarMenuSubButton,
    SidebarMenuSubItem,
    SidebarProvider,
    SidebarTrigger,
} from '@/components/ui/sidebar';
import { Toaster } from '@/components/ui/sonner';
import UpgradeButton from '@/components/UpgradeButton.vue';
import UserMenu from '@/components/UserMenu.vue';
import { useTeamPermissions } from '@/composables/useTeamPermissions';
import type { Features, SharedData } from '@/types';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, onMounted, ref, watch } from 'vue';
import { toast } from 'vue-sonner';

import axios from 'axios';
import {
    BarChart3,
    BotIcon,
    ChevronDown,
    ChevronRight,
    Code,
    Database,
    Eye,
    FileText,
    Globe,
    Heart,
    LoaderCircle,
    Lock,
    MessageSquare,
    Pencil,
    SmilePlus,
    Sparkles,
    Trash2,
    UserCheck,
} from 'lucide-vue-next';

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
    },
    // Layout options
    hideHeader: {
        type: Boolean,
        default: false,
    },
    noPadding: {
        type: Boolean,
        default: false,
    },
    fullHeight: {
        type: Boolean,
        default: false,
    },
});

const page = usePage<SharedData>();
const currentRoute = computed(() => page.component);

// Get features from page props
const features = page.props.features as Features;

// Use team permissions composable
const { hasPermission, hasAnyPermission, isOwner } = useTeamPermissions();

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

    // Only show Playground for non-widget agents (owners only, or users with setup permission)
    if (props.agent.type !== 'widget' && hasPermission('agent.setup')) {
        baseItems.push({
            id: 'playground',
            label: 'Playground',
            icon: BotIcon,
            route: 'agents.playground',
            tooltip: 'Test your agent in the playground',
        });
    }

    // Show Widget or Embed based on agent type - only if user has setup permission
    if (hasPermission('agent.setup')) {
        baseItems.push({
            id: 'embed',
            label: 'Setup',
            icon: props.agent.type === 'widget' ? Globe : Code,
            route: props.agent.type === 'widget' ? 'agents.widget' : 'agents.embed',
            tooltip: props.agent.type === 'widget' ? 'Configure and preview chat widget' : 'Generate embed code for your website',
        });
    }

    return baseItems;
});

// Check if user has any source training permission
const hasAnySourcePermission = computed(() =>
    hasAnyPermission([
        'agent.sources.train.files',
        'agent.sources.train.text',
        'agent.sources.train.qa',
        'agent.sources.train.web',
        'agent.sources.train.audio',
        'agent.sources.train.video',
    ]),
);

// Check if user has any analytics permission
const hasAnyAnalyticsPermission = computed(() =>
    hasAnyPermission([
        'agent.analytics.chats',
        'agent.analytics.metrics',
        'agent.analytics.sentiment',
        'agent.analytics.tone',
        'agent.analytics.topics',
        'agent.analytics.sources',
    ]),
);

const controlPanelNavigationItems = computed(() => {
    const baseItems: any[] = [];

    // Analytics - only show if user has any analytics permission
    if (hasAnyAnalyticsPermission.value) {
        const analyticsSubItems = [];

        if (hasPermission('agent.analytics.chats')) {
            analyticsSubItems.push({ id: 'analytics-overview', label: 'Chats', icon: BarChart3, route: 'agents.analytics' });
        }

        if (hasPermission('agent.analytics.metrics')) {
            analyticsSubItems.push({ id: 'analytics-metrics', label: 'Metrics', icon: Sparkles, route: 'agents.analytics.metrics' });
        }

        if (hasPermission('agent.analytics.sentiment')) {
            analyticsSubItems.push({ id: 'analytics-sentiment', label: 'Sentiment', icon: SmilePlus, route: 'agents.analytics.sentiment' });
        }
        if (hasPermission('agent.analytics.tone')) {
            analyticsSubItems.push({ id: 'analytics-tone', label: 'Tone', icon: Heart, route: 'agents.analytics.tone' });
        }
        if (hasPermission('agent.analytics.topics')) {
            analyticsSubItems.push({ id: 'analytics-topics', label: 'Topics', icon: MessageSquare, route: 'agents.analytics.topics' });
        }
        if (hasPermission('agent.analytics.sources')) {
            analyticsSubItems.push({ id: 'analytics-sources', label: 'Sources', icon: FileText, route: 'agents.analytics.sources' });
        }

        if (analyticsSubItems.length > 0) {
            baseItems.push({
                id: 'analytics',
                label: 'Analytics',
                icon: BarChart3,
                route: analyticsSubItems[0].route,
                tooltip: 'View usage analytics and insights',
                subItems: analyticsSubItems,
            });
        }
    }

    // Sources - only show if user has any source training permission
    if (hasAnySourcePermission.value) {
        baseItems.push({
            id: 'knowledge',
            label: 'Sources',
            icon: Database,
            route: 'agents.sources',
            tooltip: 'Manage agent knowledge and training data',
        });
    }

    // Leads - only show if user has leads permission
    if (hasPermission('agent.leads.view')) {
        baseItems.push({ id: 'leads', label: 'Leads', icon: UserCheck, route: 'agents.leads.index', tooltip: 'Manage leads captured by your agent' });
    }

    // Chat/Logs - only show if user has chat view permission
    if (hasPermission('agent.chat.view')) {
        baseItems.push({ id: 'logs', label: 'Chat', icon: MessageSquare, route: 'agents.logs', tooltip: 'Inspect raw chat messages by session' });
    }

    // Rename - only show for team owners
    if (isOwner.value) {
        baseItems.push({ id: 'rename', label: 'Rename', icon: Pencil, action: 'rename', tooltip: 'Rename this agent' });
    }

    return baseItems;
});

// Danger Zone navigation items - only show for team owners
const dangerZoneNavigationItems = computed(() => {
    if (!isOwner.value) {
        return [];
    }
    return [{ id: 'delete', label: 'Delete Agent', icon: Trash2, action: 'delete', tooltip: 'Permanently delete this agent' }];
});

// Removed Sources submenu - now handled within the component

const showDeleteDialog = ref(false);
const isDeleting = ref(false);
const agentNameConfirmation = ref('');

const showRenameDialog = ref(false);

const renameForm = useForm({
    name: '',
    sub_domain: '',
});

const deleteAgent = () => {
    if (agentNameConfirmation.value !== props.agent.name) {
        return;
    }

    isDeleting.value = true;

    router.delete(route('agents.delete', { agent: props.agent.external_id }), {
        onSuccess: () => {
            isDeleting.value = false;
            showDeleteDialog.value = false;
            agentNameConfirmation.value = '';
            toast.success('Agent deleted successfully');
        },
        onError: (errors) => {
            isDeleting.value = false;
            toast.error('Failed to delete agent. Please try again.');
        },
    });
};

const renameAgent = () => {
    if (!renameForm.name.trim()) {
        return;
    }

    if (props.agent.type === 'knowledge_base' && !renameForm.sub_domain.trim()) {
        return;
    }

    // Update form data - trim and lowercase subdomain
    renameForm.name = renameForm.name.trim();
    if (props.agent.type === 'knowledge_base') {
        renameForm.sub_domain = renameForm.sub_domain.trim().toLowerCase();
    }

    renameForm.patch(route('agents.rename', { agent: props.agent.external_id }), {
        preserveScroll: true,
        onSuccess: () => {
            showRenameDialog.value = false;
            renameForm.reset();
            toast.success('Agent updated successfully');
        },
    });
};

// Watch for rename dialog opening to set default values
watch(showRenameDialog, (newValue) => {
    if (newValue) {
        renameForm.name = props.agent.name;
        renameForm.sub_domain = props.agent.sub_domain || '';
        renameForm.clearErrors();
    }
});

const canDelete = computed(() => {
    return agentNameConfirmation.value === props.agent.name;
});

const isActiveRoute = (routeName: string) => {
    const routeParts = routeName.split('.'); // Extract parts like ['agents', 'knowledge', 'web']

    // Special handling for knowledge base routes - if checking for agents.sources,
    // match sub-routes like agents/Sources/Web, agents/Sources/Text (with trailing slash)
    // but NOT the analytics sources page which is just 'agents/Sources'
    if (routeName === 'agents.sources') {
        return currentRoute.value.startsWith('agents/Sources/');
    }

    // Handle special cases for component naming
    let componentName;
    if (routeName === 'agents.embed') {
        componentName = 'Embed';
    } else if (routeName === 'agents.playground') {
        componentName = 'Playground';
    } else if (routeName === 'agents.settings') {
        componentName = 'Persona'; // Now maps to Persona component file
    } else if (routeName === 'agents.sources') {
        componentName = 'Sources'; // Maps to Sources component file
    } else if (routeName === 'agents.sources.web') {
        componentName = 'Sources/Web'; // Maps to Sources/Web component file
    } else if (routeName === 'agents.sources.text.index') {
        componentName = 'Sources/Text'; // Maps to Sources/Text component file
    } else if (routeName === 'agents.sources.files') {
        componentName = 'Sources/FilesSource'; // Maps to Sources/FilesSource component file
    } else if (routeName === 'agents.sources.video') {
        componentName = 'Sources/Video'; // Maps to Sources/Video component file
    } else if (routeName === 'agents.sources.audio') {
        componentName = 'Sources/Audio'; // Maps to Sources/Audio component file
    } else if (routeName === 'agents.sources.youtube.index') {
        componentName = 'Sources/Youtube'; // Maps to Sources/Youtube component file
    } else if (routeName === 'agents.sources.markdown') {
        componentName = 'Sources/Markdown'; // Maps to Sources/Markdown component file
    } else if (routeName === 'agents.sources.functions.index') {
        componentName = 'Sources/Functions/index'; // Maps to Sources/Functions/index.vue
    } else if (routeName === 'agents.workflow') {
        componentName = 'Workflow'; // Maps to Workflow component file
    } else if (routeName === 'agents.actions') {
        componentName = 'Sentiment'; // Maps to Sentiment component file
    } else if (routeName === 'agents.leads.index') {
        componentName = 'Leads'; // Maps to Leads component file
    } else if (routeName === 'agents.logs') {
        componentName = 'ChatLog'; // Maps to ChatLog folder with index.vue
    } else {
        const routeSuffix = routeParts[routeParts.length - 1]; // Get last part
        componentName = routeSuffix.charAt(0).toUpperCase() + routeSuffix.slice(1);
    }

    // Handle both direct component and index.vue in folder (e.g., 'agents/ChatLog' and 'agents/ChatLog/index')
    return currentRoute.value === `agents/${componentName}` || currentRoute.value === `agents/${componentName}/index`;
};

// Analytics submenu state - auto-open if on any analytics page
// Note: 'agents/Sources' (exact match) is the analytics sources page
const analyticsSubmenuOpen = ref(
    currentRoute.value.startsWith('agents/Analytics') ||
        currentRoute.value.startsWith('agents/Metrics') ||
        currentRoute.value.startsWith('agents/Sentiment') ||
        currentRoute.value.startsWith('agents/Tone') ||
        currentRoute.value.startsWith('agents/Topics') ||
        currentRoute.value === 'agents/Sources' ||
        isActiveRoute('agents.analytics') ||
        isActiveRoute('agents.analytics.metrics') ||
        isActiveRoute('agents.analytics.sentiment') ||
        isActiveRoute('agents.analytics.tone') ||
        isActiveRoute('agents.analytics.topics') ||
        isActiveRoute('agents.analytics.sources'),
);

// Sources submenu state - auto-open if on knowledge sources page
// Note: 'agents/Sources/' prefix (with slash) matches knowledge pages like Sources/Web
const sourcesSubmenuOpen = ref(
    currentRoute.value.startsWith('agents/Sources/') ||
        isActiveRoute('agents.sources') ||
        isActiveRoute('agents.sources.web') ||
        isActiveRoute('agents.sources.text.index') ||
        isActiveRoute('agents.sources.files') ||
        isActiveRoute('agents.sources.video') ||
        isActiveRoute('agents.sources.audio') ||
        isActiveRoute('agents.sources.youtube.index') ||
        isActiveRoute('agents.sources.markdown') ||
        isActiveRoute('agents.sources.functions.index'),
);

// Source stats
const sourceStats = ref<any>(null);

// Fetch source stats
const fetchSourceStats = async () => {
    try {
        const response = await axios.get(route('agents.sources.stats', { agent: props.agent.external_id }));
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
        <div class="bg-background flex min-h-screen w-full">
            <!-- Left Sidebar - Full Height -->
            <Sidebar collapsible="icon" class="border-r">
                <!-- Sidebar Header with Logo and Toggle -->
                <div class="border-border border-b">
                    <!-- Expanded State -->
                    <div class="flex h-16 items-center justify-between px-4 group-data-[collapsible=icon]:hidden">
                        <Link :href="route('dashboard')" class="flex min-w-0 items-center gap-2 overflow-hidden">
                            <div class="flex h-8 w-8 flex-shrink-0 items-center justify-center">
                                <AppLogoIcon class="h-8 w-8" />
                            </div>
                        </Link>

                        <!-- Expanded state: Show trigger button on the right -->
                        <SidebarTrigger class="hidden flex-shrink-0 md:flex" />
                    </div>

                    <!-- Collapsed State - Full clickable area -->
                    <button
                        @click="toggleSidebar"
                        class="hover:bg-accent hidden h-16 w-full cursor-pointer items-center justify-center transition-colors group-data-[collapsible=icon]:flex"
                        aria-label="Expand sidebar"
                    >
                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            width="16"
                            height="16"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            class="h-4 w-4"
                        >
                            <rect width="18" height="18" x="3" y="3" rx="2" />
                            <path d="M9 3v18" />
                            <path d="m14 9 3 3-3 3" />
                        </svg>
                    </button>
                </div>

                <!-- Sidebar Content -->
                <SidebarContent class="!gap-6 overflow-y-auto">
                    <!-- Navigation Section -->
                    <SidebarGroup class="py-0 pt-2">
                        <SidebarMenu class="space-y-1">
                            <SidebarMenuItem v-for="item in uiNavigationItems" :key="item.id">
                                <SidebarMenuButton
                                    as-child
                                    :is-active="isActiveRoute(item.route)"
                                    :tooltip="item.tooltip"
                                    class="data-[active=true]:bg-neutral-900 data-[active=true]:font-semibold data-[active=true]:text-white dark:data-[active=true]:bg-neutral-100 dark:data-[active=true]:text-neutral-900 [&[data-active=true]_svg]:text-white dark:[&[data-active=true]_svg]:text-neutral-900"
                                >
                                    <Link :href="route(item.route, { agent: agent.external_id })">
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
                                <!-- Analytics item with submenu -->
                                <SidebarMenuItem v-if="item.id === 'analytics'">
                                    <!-- Collapsed state - show dropdown on click -->
                                    <DropdownMenu>
                                        <DropdownMenuTrigger as-child>
                                            <div class="hidden group-data-[collapsible=icon]:block">
                                                <SidebarMenuButton :tooltip="item.tooltip" class="cursor-pointer">
                                                    <component :is="item.icon" />
                                                    <span>{{ item.label }}</span>
                                                </SidebarMenuButton>
                                            </div>
                                        </DropdownMenuTrigger>
                                        <DropdownMenuContent side="right" align="start" :side-offset="5" :align-offset="0" class="w-56">
                                            <DropdownMenuItem v-for="subItem in item.subItems" :key="subItem.id" as-child>
                                                <Link :href="route(subItem.route, { agent: agent.external_id })" class="flex items-center gap-2">
                                                    <component :is="subItem.icon" class="h-4 w-4" />
                                                    <span>{{ subItem.label }}</span>
                                                </Link>
                                            </DropdownMenuItem>
                                        </DropdownMenuContent>
                                    </DropdownMenu>

                                    <!-- Expanded state - show regular submenu -->
                                    <div class="block group-data-[collapsible=icon]:hidden">
                                        <SidebarMenuButton
                                            @click="analyticsSubmenuOpen = !analyticsSubmenuOpen"
                                            :tooltip="item.tooltip"
                                            class="cursor-pointer"
                                        >
                                            <component :is="item.icon" />
                                            <span>{{ item.label }}</span>
                                            <ChevronDown
                                                class="ml-auto h-4 w-4 transition-transform duration-200"
                                                :class="{ 'rotate-180': analyticsSubmenuOpen }"
                                            />
                                        </SidebarMenuButton>
                                    </div>
                                    <SidebarMenuSub v-if="analyticsSubmenuOpen" class="mt-1 space-y-0.5 group-data-[collapsible=icon]:hidden">
                                        <SidebarMenuSubItem v-for="subItem in item.subItems" :key="subItem.id">
                                            <SidebarMenuSubButton
                                                as-child
                                                :is-active="isActiveRoute(subItem.route)"
                                                class="data-[active=true]:bg-neutral-900 data-[active=true]:font-semibold data-[active=true]:text-white dark:data-[active=true]:bg-neutral-100 dark:data-[active=true]:text-neutral-900 [&[data-active=true]_svg]:text-white dark:[&[data-active=true]_svg]:text-neutral-900"
                                            >
                                                <Link :href="route(subItem.route, { agent: agent.external_id })">
                                                    <component :is="subItem.icon" class="h-4 w-4" />
                                                    <span>{{ subItem.label }}</span>
                                                </Link>
                                            </SidebarMenuSubButton>
                                        </SidebarMenuSubItem>
                                    </SidebarMenuSub>
                                </SidebarMenuItem>

                                <!-- Sources item with submenu -->
                                <SidebarMenuItem v-else-if="item.id === 'knowledge'">
                                    <!-- Collapsed state - show dropdown on click -->
                                    <DropdownMenu>
                                        <DropdownMenuTrigger as-child>
                                            <div class="hidden group-data-[collapsible=icon]:block">
                                                <SidebarMenuButton :tooltip="item.tooltip" class="cursor-pointer">
                                                    <component :is="item.icon" />
                                                    <span>{{ item.label }}</span>
                                                </SidebarMenuButton>
                                            </div>
                                        </DropdownMenuTrigger>
                                        <DropdownMenuContent side="right" align="start" :side-offset="5" :align-offset="0" class="w-56">
                                            <AgentSourcesMenu
                                                :agent-id="agent.external_id"
                                                :source-stats="sourceStats"
                                                variant="dropdown"
                                                :is-active-route="isActiveRoute"
                                            >
                                                <template #default="{ item, label }">
                                                    <DropdownMenuItem as-child>
                                                        <Link :href="route(item.route, { agent: agent.external_id })" class="flex items-center gap-2">
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
                                            :agent-id="agent.external_id"
                                            :source-stats="sourceStats"
                                            variant="submenu"
                                            :is-active-route="isActiveRoute"
                                        >
                                            <template #default="{ item, label, isActive }">
                                                <SidebarMenuSubItem>
                                                    <SidebarMenuSubButton
                                                        as-child
                                                        :is-active="isActive"
                                                        class="data-[active=true]:bg-neutral-900 data-[active=true]:font-semibold data-[active=true]:text-white dark:data-[active=true]:bg-neutral-100 dark:data-[active=true]:text-neutral-900 [&[data-active=true]_svg]:text-white dark:[&[data-active=true]_svg]:text-neutral-900"
                                                    >
                                                        <Link :href="route(item.route, { agent: agent.external_id })">
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
                                        class="data-[active=true]:bg-neutral-900 data-[active=true]:font-semibold data-[active=true]:text-white dark:data-[active=true]:bg-neutral-100 dark:data-[active=true]:text-neutral-900 [&[data-active=true]_svg]:text-white dark:[&[data-active=true]_svg]:text-neutral-900"
                                    >
                                        <Link :href="route(item.route, { agent: agent.external_id })">
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
                        <div class="border-border border-t"></div>
                    </div>

                    <!-- Danger Zone Section -->
                    <SidebarGroup class="py-0">
                        <SidebarGroupLabel class="text-destructive mb-2 px-2">Danger Zone</SidebarGroupLabel>
                        <SidebarMenu class="space-y-1">
                            <SidebarMenuItem v-for="item in dangerZoneNavigationItems" :key="item.id">
                                <SidebarMenuButton
                                    @click="showDeleteDialog = true"
                                    :tooltip="item.tooltip"
                                    class="text-destructive hover:text-destructive hover:bg-destructive/10 cursor-pointer"
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
            <div class="flex min-w-0 flex-1 flex-col overflow-x-hidden">
                <!-- Top Navigation Bar -->
                <header class="bg-background border-border sticky top-0 z-40 border-b">
                    <div class="px-4 lg:px-6">
                        <div class="flex h-16 items-center justify-between">
                            <!-- Left side - Breadcrumbs -->
                            <div class="flex items-center gap-4">
                                <!-- Mobile sidebar toggle -->
                                <SidebarTrigger class="md:hidden" />

                                <!-- Breadcrumbs -->
                                <div class="flex items-center gap-2 text-sm">
                                    <Link
                                        :href="route('dashboard')"
                                        class="text-muted-foreground hover:text-foreground hidden transition-colors md:inline"
                                    >
                                        Dashboard
                                    </Link>
                                    <ChevronRight class="text-muted-foreground hidden h-4 w-4 md:block" />
                                    <Link
                                        :href="route('agents.index')"
                                        class="text-muted-foreground hover:text-foreground hidden transition-colors md:inline"
                                    >
                                        Agents
                                    </Link>
                                    <ChevronRight class="text-muted-foreground hidden h-4 w-4 md:block" />
                                    <span class="text-foreground font-medium">{{ agent.name }}</span>
                                </div>
                            </div>

                            <!-- Right side - Actions and user menu -->
                            <div class="flex items-center gap-3">
                                <!-- Utilities: Upgrade, Theme, User -->
                                <div class="hidden items-center gap-2 lg:flex">
                                    <UpgradeButton />
                                    <ThemeSwitcher />
                                    <UserMenu />
                                </div>
                            </div>
                        </div>
                    </div>
                </header>

                <!-- Page Content Wrapper -->
                <div class="flex min-w-0 flex-1 flex-col overflow-hidden">
                    <!-- Page Header -->
                    <div v-if="!hideHeader" class="bg-background border-border border-b px-4 py-4 lg:px-6">
                        <div class="flex items-center justify-between">
                            <div class="min-w-0 flex-1">
                                <h1 class="text-foreground flex items-center gap-2 truncate text-lg font-semibold lg:text-xl">
                                    <slot name="header-icon" />
                                    {{ title }}
                                </h1>
                                <p v-if="description" class="text-muted-foreground mt-1 hidden text-xs sm:block lg:text-sm">
                                    {{ description }}
                                </p>
                            </div>

                            <div class="flex flex-shrink-0 gap-2 lg:gap-4">
                                <div class="flex items-center gap-2">
                                    <slot name="header-actions" />
                                </div>

                                <Badge
                                    v-if="agent.type === 'knowledge_base'"
                                    :variant="agent.is_public ? 'destructive' : 'secondary'"
                                    :class="[
                                        'text-xs',
                                        !agent.is_public
                                            ? 'border-green-200 bg-green-100 text-green-700 dark:border-green-800 dark:bg-green-900/20 dark:text-green-400'
                                            : '',
                                    ]"
                                >
                                    <component :is="agent.is_public ? Eye : Lock" class="h-3 w-3" />
                                </Badge>
                            </div>
                        </div>
                    </div>

                    <!-- Page Content -->
                    <div class="min-w-0 flex-1" :class="[fullHeight ? 'overflow-hidden' : 'overflow-auto', noPadding ? '' : 'bg-muted/30']">
                        <div :class="['max-w-full min-w-0', noPadding ? 'h-full' : 'p-3 sm:p-4 lg:p-6', fullHeight ? 'h-full' : '']">
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
                        <label for="agent-name-confirmation" class="text-foreground mb-2 block text-sm font-medium">
                            Please type <strong>{{ agent.name }}</strong> to confirm
                        </label>
                        <input
                            id="agent-name-confirmation"
                            v-model="agentNameConfirmation"
                            type="text"
                            class="border-input bg-background text-foreground placeholder:text-muted-foreground focus:ring-ring w-full rounded-md border px-3 py-2 focus:border-transparent focus:ring-2 focus:outline-none"
                            placeholder="Enter agent name to confirm"
                            :class="{
                                'border-red-500 focus:ring-red-500': agentNameConfirmation && !canDelete,
                                'border-green-500 focus:ring-green-500': canDelete,
                            }"
                        />
                        <p v-if="agentNameConfirmation && !canDelete" class="mt-1 text-sm text-red-500">Agent name does not match</p>
                    </div>
                </div>

                <DialogFooter>
                    <Button variant="outline" @click="showDeleteDialog = false"> Cancel </Button>
                    <Button variant="destructive" @click="deleteAgent" :disabled="isDeleting || !canDelete">
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
                        Enter a new name for your agent <strong>{{ agent.name }}</strong
                        >.
                    </DialogDescription>
                </DialogHeader>

                <div class="space-y-4">
                    <div>
                        <label for="new-agent-name" class="text-foreground mb-2 block text-sm font-medium"> Agent name </label>
                        <input
                            id="new-agent-name"
                            v-model="renameForm.name"
                            type="text"
                            class="border-input bg-background text-foreground placeholder:text-muted-foreground focus:ring-ring w-full rounded-md border px-3 py-2 focus:border-transparent focus:ring-2 focus:outline-none"
                            :class="{ 'border-red-500': renameForm.errors.name }"
                            placeholder="Enter new agent name"
                            @keyup.enter="renameAgent"
                        />
                        <p v-if="renameForm.errors.name" class="mt-1 text-xs text-red-500">
                            {{ renameForm.errors.name }}
                        </p>
                    </div>

                    <!-- Subdomain field for knowledge_base only -->
                    <div v-if="agent.type === 'knowledge_base'">
                        <label for="subdomain" class="text-foreground mb-2 block text-sm font-medium"> Subdomain </label>
                        <div class="flex">
                            <span
                                class="bg-muted border-input text-muted-foreground inline-flex items-center rounded-l-md border border-r-0 px-3 py-2 text-sm"
                            >
                                https://
                            </span>
                            <input
                                id="subdomain"
                                v-model="renameForm.sub_domain"
                                type="text"
                                class="border-input bg-background text-foreground placeholder:text-muted-foreground focus:ring-ring flex-1 border-y px-3 py-2 text-sm focus:z-10 focus:ring-2 focus:outline-none"
                                :class="{ 'border-red-500': renameForm.errors.sub_domain }"
                                placeholder="your-subdomain"
                                @keyup.enter="renameAgent"
                            />
                            <span
                                class="bg-muted border-input text-muted-foreground inline-flex items-center rounded-r-md border border-l-0 px-3 py-2 text-sm"
                            >
                                .chatvia.ai
                            </span>
                        </div>
                        <p v-if="renameForm.errors.sub_domain" class="mt-1 text-xs text-red-500">
                            {{ renameForm.errors.sub_domain }}
                        </p>
                        <p v-else class="text-muted-foreground mt-1 text-xs">Only lowercase letters, numbers, and hyphens allowed.</p>
                    </div>
                </div>

                <DialogFooter>
                    <Button variant="outline" @click="showRenameDialog = false"> Cancel </Button>
                    <Button
                        @click="renameAgent"
                        :disabled="
                            renameForm.processing || !renameForm.name.trim() || (agent.type === 'knowledge_base' && !renameForm.sub_domain.trim())
                        "
                    >
                        <LoaderCircle v-if="renameForm.processing" class="mr-2 h-4 w-4 animate-spin" />
                        Save
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
