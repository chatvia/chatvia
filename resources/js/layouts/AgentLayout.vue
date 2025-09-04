<script lang="ts" setup>
import { computed, onMounted, watch } from 'vue';
import { Head, Link, usePage, router } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Separator } from '@/components/ui/separator';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle
} from '@/components/ui/dialog';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger
} from '@/components/ui/tooltip';
import { toast } from 'vue-sonner';
import type { Features } from '@/types';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import UserMenu from '@/components/UserMenu.vue';
import ThemeSwitcher from '@/components/ThemeSwitcher.vue';
import UpgradeButton from '@/components/UpgradeButton.vue';

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
    ChevronDown,
    ChevronUp,
    Code,
    PanelLeftClose,
    PanelLeftOpen,
    FileText,
    Settings,
    Menu,
} from 'lucide-vue-next';
import { cn } from '@/lib/utils';
import { ref } from 'vue';

const props = defineProps({
    agent: {
        type: Object,
        required: true,
    },
    title: {
        type: String,
        default: 'Agent Settings',
    }
})

const page = usePage();
const currentRoute = computed(() => page.component);

// Get features from page props
const features = page.props.features as Features;

// Collapse/expand state for sections - with localStorage persistence
const isUiSectionExpanded = ref(true);
const isControlPanelExpanded = ref(true);
const isNavigationCollapsed = ref(true); // Default to collapsed on mobile
const isKnowledgeBaseExpanded = ref(false);

// Load states from localStorage on component mount
onMounted(() => {
    const savedUiExpanded = localStorage.getItem('agent-layout-ui-expanded');
    const savedControlPanelExpanded = localStorage.getItem('agent-layout-control-panel-expanded');
    const savedNavigationCollapsed = localStorage.getItem('agent-layout-navigation-collapsed');
    const savedKnowledgeBaseExpanded = localStorage.getItem('agent-layout-knowledge-base-expanded');

    if (savedUiExpanded !== null) {
        isUiSectionExpanded.value = savedUiExpanded === 'true';
    }
    if (savedControlPanelExpanded !== null) {
        isControlPanelExpanded.value = savedControlPanelExpanded === 'true';
    }

    // Mobile-first approach: collapsed by default, but respect desktop preference
    if (savedNavigationCollapsed !== null) {
        // On desktop, use saved preference. On mobile, always start collapsed
        const isDesktop = window.innerWidth >= 1024;
        if (isDesktop) {
            isNavigationCollapsed.value = savedNavigationCollapsed === 'true';
        } else {
            isNavigationCollapsed.value = true; // Always collapsed on mobile
        }
    }

    if (savedKnowledgeBaseExpanded !== null) {
        isKnowledgeBaseExpanded.value = savedKnowledgeBaseExpanded === 'true';
    }

    // Auto-expand knowledge base if user is on a knowledge base page
    const isOnKnowledgePage = currentRoute.value?.includes('KnowledgeBase');
    if (isOnKnowledgePage) {
        isKnowledgeBaseExpanded.value = true;
    }
});

// Watch for changes and save to localStorage
watch(isUiSectionExpanded, (newValue) => {
    localStorage.setItem('agent-layout-ui-expanded', newValue.toString());
});

watch(isControlPanelExpanded, (newValue) => {
    localStorage.setItem('agent-layout-control-panel-expanded', newValue.toString());
});

watch(isNavigationCollapsed, (newValue) => {
    localStorage.setItem('agent-layout-navigation-collapsed', newValue.toString());
});

watch(isKnowledgeBaseExpanded, (newValue) => {
    localStorage.setItem('agent-layout-knowledge-base-expanded', newValue.toString());
});

// Remove breadcrumbs as we're creating a standalone layout

const uiNavigationItems = computed(() => {
    const baseItems = [];

    // Only show Chat for non-widget agents
    if (props.agent.type !== 'widget') {
        baseItems.push({
            id: 'preview',
            label: 'Chat',
            icon: BotIcon,
            route: 'agents.preview',
            tooltip: 'Preview your agent chat interface'
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

    if (page.props.permissions.persona.view === true) {
        baseItems.push(
            { id: 'persona', label: 'Persona', icon: Bot, route: 'agents.persona', tooltip: 'Configure agent personality and behavior' },
        )
    }

    if (page.props.permissions.models.view === true) {
        baseItems.push(
            { id: 'models', label: 'AI Models', icon: Brain, route: 'agents.models', tooltip: 'Manage AI models and settings' },
        )
    }

    baseItems.push(
        ...[
            { id: 'starters', label: 'Starter Messages', icon: MessageSquare, route: 'agents.starters', tooltip: 'Create conversation starter messages' },

            { id: 'history', label: 'History', icon: Clock, route: 'agents.history', tooltip: 'View all agent conversations' },
            { id: 'logs', label: 'Chat Log', icon: MessageSquare, route: 'agents.logs', tooltip: 'Inspect raw chat messages by session' },
            { id: 'leads', label: 'Leads', icon: Users, route: 'agents.leads', tooltip: 'View and manage leads generated by this agent' },
        ]
    )

    // Only add security if the agent is not a widget
    if (props.agent.type !== 'widget') {
        baseItems.push({ id: 'security', label: 'Security', icon: Lock, route: 'agents.security', tooltip: 'Configure access control and embedding settings' });
    }

    // Only add members if the agent is not a widget
    if (props.agent.type !== 'widget') {
        // Insert members after history (at index 5)
        baseItems.splice(5, 0, { id: 'team', label: 'Members', icon: Users, route: 'agents.team', tooltip: 'Manage team members and permissions' });
    }

    // Only add appearance if the agent is not a widget
    if (props.agent.type !== 'widget') {
        // Insert appearance after starters (at index 4)
        baseItems.splice(4, 0, { id: 'appearance', label: 'Appearance', icon: Palette, route: 'agents.appearance', tooltip: 'Customize colors, themes, and branding' });
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
        baseItems.splice(insertIndex, 0, { id: 'actions', label: 'Actions', icon: Zap, route: 'agents.actions', tooltip: 'Set up automated actions based on user inputs' });
    }

    return baseItems;
});

// Knowledge Base submenu items
const knowledgeBaseItems = computed(() => [
    { id: 'knowledge-files', label: 'Files', icon: FileText, route: 'agents.knowledge', tooltip: 'Upload and manage knowledge files' },
    { id: 'knowledge-web', label: 'Website', icon: Globe, route: 'agents.knowledge.web', tooltip: 'Crawl websites for knowledge' },
]);

// Knowledge Base parent should not be highlighted - only submenu items should be
const isKnowledgeBaseActive = computed(() => {
    return false; // Parent item is never highlighted, only submenu items
});

const showDeleteDialog = ref(false);
const isDeleting = ref(false);
const agentNameConfirmation = ref('');

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

const canDelete = computed(() => {
    return agentNameConfirmation.value === props.agent.name;
});

const isActiveRoute = (routeName: string) => {
    const routeParts = routeName.split('.'); // Extract parts like ['agents', 'knowledge', 'web']

    // Handle special cases for component naming
    let componentName;
    if (routeName === 'agents.embed') {
        componentName = 'Embed';
    } else if (routeName === 'agents.preview') {
        componentName = 'Preview';
    } else if (routeName === 'agents.settings') {
        componentName = 'Persona';  // Now maps to Persona component file
    } else if (routeName === 'agents.knowledge') {
        componentName = 'KnowledgeBase';  // Maps to KnowledgeBase component file
    } else if (routeName === 'agents.knowledge.web') {
        componentName = 'KnowledgeBase/Web';  // Maps to KnowledgeBase/Web component file
    } else if (routeName === 'agents.workflow') {
        componentName = 'Workflow';  // Maps to Workflow component file
    } else if (routeName === 'agents.actions') {
        componentName = 'Actions';  // Maps to Actions component file
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




</script>

<template>
    <div class="min-h-screen bg-background">
        <Head :title="title" />

        <!-- Main Navigation Bar -->
        <nav class="bg-background border-b border-border sticky top-0 z-50">
            <div class="px-4 lg:px-6">
                <div class="flex items-center justify-between h-16">
                    <!-- Left side - Logo/Brand and breadcrumbs -->
                    <div class="flex items-center gap-4">
                        <div class="flex items-center gap-2">
                            <!-- Logo/Brand -->
                            <Link :href="route('dashboard')" class="flex items-center gap-2 text-foreground hover:text-primary transition-colors">
                                <div class="h-8 w-8 flex items-center justify-center">
                                    <AppLogoIcon class="h-8 w-8" />
                                </div>
                            </Link>
                        </div>
                        <!-- Mobile sidebar toggle (moved to left) -->
                        <Button
                            variant="ghost"
                            size="sm"
                            @click="isNavigationCollapsed = !isNavigationCollapsed"
                            class="lg:hidden p-2"
                        >
                            <Menu class="h-4 w-4" />
                        </Button>

                        <!-- Breadcrumbs -->
                        <div class="hidden md:flex items-center gap-2 text-sm">
                            <ChevronRight class="h-4 w-4 text-muted-foreground" />
                            <Link :href="route('agents.index')" class="text-muted-foreground hover:text-foreground transition-colors">
                                Agents
                            </Link>
                            <ChevronRight class="h-4 w-4 text-muted-foreground" />
                            <span class="text-foreground font-medium">{{ agent.name }}</span>
                        </div>
                    </div>

                    <!-- Right side - Actions and user menu -->
                    <div class="flex items-center gap-3">
                        <!-- Removed View Agent buttons (desktop & mobile) per request -->

                        <!-- Utilities: Upgrade, Theme, User -->
                        <div class="hidden lg:flex items-center gap-2">
                            <UpgradeButton />
                            <ThemeSwitcher />
                            <UserMenu />
                        </div>
                    </div>
                </div>
            </div>
        </nav>

        <div class="lg:flex h-[calc(100vh-4rem)] lg:overflow-hidden overflow-visible">
            <!-- Mobile Sidebar Backdrop -->
            <div
                v-if="!isNavigationCollapsed"
                @click="isNavigationCollapsed = true"
                class="fixed inset-0 bg-black/50 z-30 lg:hidden"
                style="top: 4rem;"
            ></div>

            <!-- Left Sidebar -->
            <aside
                :class="[
                    'bg-card border-r border-border transition-all duration-300 flex flex-col z-40',
                    // Desktop: normal sidebar behavior
                    'lg:relative lg:flex-shrink-0 lg:h-full',
                    isNavigationCollapsed ? 'lg:w-16' : 'lg:w-64',
                    // Mobile: fixed overlay that doesn't affect layout
                    'fixed lg:relative top-16 lg:top-0 bottom-0 left-0 w-64 h-[calc(100vh-4rem)] lg:h-full',
                    // Mobile show/hide with transforms
                    isNavigationCollapsed
                        ? '-translate-x-full lg:translate-x-0'
                        : 'translate-x-0',
                ]"
            >
                <!-- Navigation Menu -->
                <nav class="flex-1 overflow-y-auto py-4" :class="isNavigationCollapsed ? 'pb-32 lg:pb-32' : 'pb-36 lg:pb-36'">
                    <div class="px-3 lg:px-4 space-y-6">
                        <!-- Interface Section -->
                        <div class="space-y-2">
                            <div v-if="!isNavigationCollapsed" class="px-2 mb-3">
                                <h3 class="text-xs font-semibold text-muted-foreground uppercase tracking-wider">Interface</h3>
                            </div>

                            <template v-if="!isNavigationCollapsed">
                                <Link
                                    v-for="item in uiNavigationItems"
                                    :key="item.id"
                                    :href="route(item.route, { agent: agent.id })"
                                    :class="cn(
                                        'flex items-center gap-3 px-3 py-3 lg:py-2.5 text-sm font-medium rounded-lg transition-all duration-200 group touch-manipulation',
                                        isActiveRoute(item.route)
                                            ? 'bg-primary text-primary-foreground shadow-sm'
                                            : 'text-muted-foreground hover:bg-muted hover:text-foreground active:bg-muted'
                                    )"
                                >
                                    <component :is="item.icon" class="h-4 w-4 flex-shrink-0" />
                                    <span>{{ item.label }}</span>
                                </Link>
                            </template>

                            <!-- Collapsed Interface Icons -->
                            <template v-else>
                                <TooltipProvider>
                                    <div class="space-y-1">
                                        <Tooltip v-for="item in uiNavigationItems" :key="item.id">
                                            <TooltipTrigger as-child>
                                                <Link
                                                    :href="route(item.route, { agent: agent.id })"
                                                    :class="cn(
                                                        'flex items-center justify-center h-10 w-10 rounded-lg transition-all duration-200',
                                                        isActiveRoute(item.route)
                                                            ? 'bg-primary text-primary-foreground shadow-sm'
                                                            : 'text-muted-foreground hover:bg-muted hover:text-foreground'
                                                    )"
                                                >
                                                    <component :is="item.icon" class="h-4 w-4" />
                                                </Link>
                                            </TooltipTrigger>
                                            <TooltipContent side="right">
                                                <p>{{ item.tooltip }}</p>
                                            </TooltipContent>
                                        </Tooltip>
                                    </div>
                                </TooltipProvider>
                            </template>
                        </div>

                        <Separator v-if="!isNavigationCollapsed" />

                        <!-- Management Section -->
                        <div class="space-y-2">
                            <div v-if="!isNavigationCollapsed" class="px-2 mb-3">
                                <h3 class="text-xs font-semibold text-muted-foreground uppercase tracking-wider">Management</h3>
                            </div>

                            <template v-if="!isNavigationCollapsed">
                                <!-- Regular navigation items -->
                                <template v-for="item in controlPanelNavigationItems" :key="item.id">
                                    <!-- Insert Knowledge Base submenu after AI Models -->
                                    <template v-if="item.id === 'models'">
                                        <!-- AI Models item -->
                                        <Link
                                            :href="route(item.route, { agent: agent.id })"
                                            :class="cn(
                                                'flex items-center gap-3 px-3 py-3 lg:py-2.5 text-sm font-medium rounded-lg transition-all duration-200 group touch-manipulation',
                                                isActiveRoute(item.route)
                                                    ? 'bg-primary text-primary-foreground shadow-sm'
                                                    : 'text-muted-foreground hover:bg-muted hover:text-foreground active:bg-muted'
                                            )"
                                        >
                                            <component :is="item.icon" class="h-4 w-4 flex-shrink-0" />
                                            <span>{{ item.label }}</span>
                                        </Link>

                                        <!-- Knowledge Base expandable item -->
                                        <div class="space-y-1">
                                            <button
                                                @click="isKnowledgeBaseExpanded = !isKnowledgeBaseExpanded"
                                                :class="cn(
                                                    'w-full flex items-center gap-3 px-3 py-3 lg:py-2.5 text-sm font-medium rounded-lg transition-all duration-200 group touch-manipulation',
                                                    'text-muted-foreground hover:bg-muted hover:text-foreground active:bg-muted'
                                                )"
                                            >
                                                <Database class="h-4 w-4 flex-shrink-0" />
                                                <span class="flex-1 text-left">Knowledge Base</span>
                                                <component
                                                    :is="isKnowledgeBaseExpanded ? ChevronUp : ChevronDown"
                                                    class="h-4 w-4 flex-shrink-0"
                                                />
                                            </button>

                                            <!-- Knowledge Base submenu -->
                                            <div v-show="isKnowledgeBaseExpanded" class="ml-6 space-y-1">
                                                <Link
                                                    v-for="subItem in knowledgeBaseItems"
                                                    :key="subItem.id"
                                                    :href="route(subItem.route, { agent: agent.id })"
                                                    :class="cn(
                                                        'flex items-center gap-3 px-3 py-2 text-sm font-medium rounded-lg transition-all duration-200 group',
                                                        isActiveRoute(subItem.route)
                                                            ? 'bg-primary text-primary-foreground shadow-sm'
                                                            : 'text-muted-foreground hover:bg-muted hover:text-foreground'
                                                    )"
                                                >
                                                    <component :is="subItem.icon" class="h-4 w-4 flex-shrink-0" />
                                                    <span>{{ subItem.label }}</span>
                                                </Link>
                                            </div>
                                        </div>
                                    </template>
                                    <template v-else>
                                        <!-- Regular navigation item -->
                                        <Link
                                            :href="route(item.route, { agent: agent.id })"
                                            :class="cn(
                                                'flex items-center gap-3 px-3 py-3 lg:py-2.5 text-sm font-medium rounded-lg transition-all duration-200 group touch-manipulation',
                                                isActiveRoute(item.route)
                                                    ? 'bg-primary text-primary-foreground shadow-sm'
                                                    : 'text-muted-foreground hover:bg-muted hover:text-foreground active:bg-muted'
                                            )"
                                        >
                                            <component :is="item.icon" class="h-4 w-4 flex-shrink-0" />
                                            <span>{{ item.label }}</span>
                                        </Link>
                                    </template>
                                </template>
                            </template>

                            <!-- Collapsed Management Icons -->
                            <template v-else>
                                <TooltipProvider>
                                    <div class="space-y-1">
                                        <Tooltip v-for="item in controlPanelNavigationItems" :key="item.id">
                                            <TooltipTrigger as-child>
                                                <Link
                                                    :href="route(item.route, { agent: agent.id })"
                                                    :class="cn(
                                                        'flex items-center justify-center h-10 w-10 rounded-lg transition-all duration-200',
                                                        isActiveRoute(item.route)
                                                            ? 'bg-primary text-primary-foreground shadow-sm'
                                                            : 'text-muted-foreground hover:bg-muted hover:text-foreground'
                                                    )"
                                                >
                                                    <component :is="item.icon" class="h-4 w-4" />
                                                </Link>
                                            </TooltipTrigger>
                                            <TooltipContent side="right">
                                                <p>{{ item.tooltip }}</p>
                                            </TooltipContent>
                                        </Tooltip>
                                    </div>
                                </TooltipProvider>
                            </template>
                        </div>
                    </div>
                </nav>

                <!-- Sidebar Footer - Absolutely positioned at bottom -->
                <div class="absolute bottom-0 left-0 right-0 bg-card border-t border-border">
                    <div class="p-4">
                        <template v-if="!isNavigationCollapsed">
                            <button
                                @click="showDeleteDialog = true"
                                class="flex items-center gap-3 px-3 py-3 lg:py-2.5 text-sm font-medium rounded-lg transition-all duration-200 group text-destructive hover:bg-destructive/10 active:bg-destructive/20 w-full mb-3 touch-manipulation"
                            >
                                <Trash2 class="h-4 w-4 flex-shrink-0" />
                                <span>Delete Agent</span>
                            </button>
                        </template>

                        <!-- Collapsed Delete Button -->
                        <template v-else>
                            <div class="flex justify-center mb-3">
                                <TooltipProvider>
                                    <Tooltip>
                                        <TooltipTrigger as-child>
                                            <button
                                                @click="showDeleteDialog = true"
                                                class="flex items-center justify-center h-10 w-10 rounded-lg transition-all duration-200 text-destructive hover:bg-destructive/10"
                                            >
                                                <Trash2 class="h-4 w-4" />
                                            </button>
                                        </TooltipTrigger>
                                        <TooltipContent side="right">
                                            <p>Delete agent permanently</p>
                                        </TooltipContent>
                                    </Tooltip>
                                </TooltipProvider>
                            </div>
                        </template>

                        <!-- Collapse Button -->
                        <Button
                            @click="isNavigationCollapsed = !isNavigationCollapsed"
                            variant="ghost"
                            size="sm"
                            :class="[
                                'transition-all duration-200 text-muted-foreground hover:text-foreground',
                                isNavigationCollapsed ? 'w-10 h-10 p-0' : 'w-full gap-2'
                            ]"
                        >
                            <component :is="isNavigationCollapsed ? PanelLeftOpen : PanelLeftClose" class="h-4 w-4" />
                            <span v-if="!isNavigationCollapsed">
                                Collapse
                            </span>
                        </Button>
                    </div>
                </div>
            </aside>

            <!-- Main Content Area -->
            <main class="flex-1 flex flex-col lg:overflow-hidden overflow-visible w-full lg:w-auto">
                <!-- Page Header -->
                <div class="bg-background border-b border-border px-4 lg:px-6 py-4">
                    <div class="flex items-center justify-between">
                        <div class="min-w-0 flex-1">
                            <h1 class="text-lg lg:text-xl font-semibold text-foreground truncate">{{ title }}</h1>
                            <p class="text-xs lg:text-sm text-muted-foreground mt-1 hidden sm:block">
                                Manage your {{ agent.name }} {{ agent.type }} agent
                            </p>
                        </div>


                        <div class="flex gap-2 lg:gap-4 flex-shrink-0">

                            <div class="flex items-center gap-2">
                                <slot name="header-actions" />
                            </div>

                            <Badge
                                v-if="agent.type === 'standalone'"
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
                <div class="flex-1 lg:overflow-auto overflow-visible bg-muted/30">
                    <div class="p-3 sm:p-4 lg:p-6">
                        <slot />
                    </div>
                </div>
            </main>
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
    </div>
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


