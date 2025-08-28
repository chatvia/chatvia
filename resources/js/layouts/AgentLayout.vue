<script lang="ts" setup>
import { computed, onMounted, watch } from 'vue';
import { Head, Link, usePage, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
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
import type { BreadcrumbItem, Features } from '@/types';

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
const isNavigationCollapsed = ref(false);

// Load states from localStorage on component mount
onMounted(() => {
    const savedUiExpanded = localStorage.getItem('agent-layout-ui-expanded');
    const savedControlPanelExpanded = localStorage.getItem('agent-layout-control-panel-expanded');
    const savedNavigationCollapsed = localStorage.getItem('agent-layout-navigation-collapsed');

    if (savedUiExpanded !== null) {
        isUiSectionExpanded.value = savedUiExpanded === 'true';
    }
    if (savedControlPanelExpanded !== null) {
        isControlPanelExpanded.value = savedControlPanelExpanded === 'true';
    }
    if (savedNavigationCollapsed !== null) {
        isNavigationCollapsed.value = savedNavigationCollapsed === 'true';
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

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Agents',
        href: route('agents.index'),
    },
    {
        title: props.agent.name,
        href: route('agents.stats', { agent: props.agent.id }),
    },
];

const uiNavigationItems = computed(() => {
    const baseItems = [
        { id: 'preview', label: 'Chat', icon: BotIcon, route: 'agents.preview', tooltip: 'Preview your agent chat interface' },
        { id: 'embed', label: 'Embed', icon: Code, route: 'agents.embed', tooltip: 'Generate embed code for your website' },
    ];

    // Only add widget if the feature is enabled
    if (features.widget) {
        baseItems.push({ id: 'widget', label: 'Widget', icon: Globe, route: 'agents.widget', tooltip: 'Configure and preview chat widget' });
    }

    return baseItems;
});

const controlPanelNavigationItems = computed(() => {
    const baseItems = [
        { id: 'stats', label: 'Statistics', icon: BarChart3, route: 'agents.stats', tooltip: 'View usage statistics and analytics' },
        { id: 'persona', label: 'Persona', icon: Bot, route: 'agents.settings', tooltip: 'Configure agent personality and behavior' },
        { id: 'models', label: 'AI Models', icon: Brain, route: 'agents.models', tooltip: 'Manage AI models and settings' },

        { id: 'knowledge', label: 'Knowledge Base', icon: Database, route: 'agents.knowledge', tooltip: 'Upload and manage knowledge files' },
        { id: 'starters', label: 'Starter Messages', icon: MessageSquare, route: 'agents.starters', tooltip: 'Create conversation starter messages' },

        { id: 'appearance', label: 'Appearance', icon: Palette, route: 'agents.appearance', tooltip: 'Customize colors, themes, and branding' },

        { id: 'history', label: 'Message History', icon: Clock, route: 'agents.history', tooltip: 'View all agent conversations' },
        { id: 'team', label: 'Members', icon: Users, route: 'agents.team', tooltip: 'Manage team members and permissions' },
        { id: 'security', label: 'Security', icon: Lock, route: 'agents.security', tooltip: 'Configure access control and embedding settings' },
    ];

    // Only add workflow if the feature is enabled
    if (features.workflow) {
        // Insert workflow after knowledge base (at index 4)
        baseItems.splice(4, 0, { id: 'workflow', label: 'Workflow', icon: Workflow, route: 'agents.workflow', tooltip: 'Create and manage agent workflows' });
    }

    // Only add actions if the feature is enabled
    if (features.actions) {
        // Insert actions after workflow (or after knowledge base if workflow is disabled)
        const insertIndex = features.workflow ? 5 : 4;
        baseItems.splice(insertIndex, 0, { id: 'actions', label: 'Actions', icon: Zap, route: 'agents.actions', tooltip: 'Set up automated actions based on user inputs' });
    }

    return baseItems;
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
    const routeSuffix = routeName.split('.')[1]; // Extract 'stats', 'settings', etc.

    // Handle special cases for component naming
    let componentName;
    if (routeSuffix === 'embed') {
        componentName = 'Embed';
    } else if (routeSuffix === 'preview') {
        componentName = 'Preview';
    } else if (routeSuffix === 'settings') {
        componentName = 'Persona';  // Now maps to Persona component file
    } else if (routeSuffix === 'knowledge') {
        componentName = 'KnowledgeBase';  // Maps to KnowledgeBase component file
    } else if (routeSuffix === 'workflow') {
        componentName = 'Workflow';  // Maps to Workflow component file
    } else if (routeSuffix === 'actions') {
        componentName = 'Actions';  // Maps to Actions component file
    } else {
        componentName = routeSuffix.charAt(0).toUpperCase() + routeSuffix.slice(1);
    }

    return currentRoute.value === `agents/${componentName}`;
};




</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="title" />

        <div class="min-h-screen bg-background">
            <!-- Header -->
            <div class="bg-background">
                <div class="container mx-auto px-4 sm:px-6 lg:px-8 py-3 sm:py-4">
                    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                        <!-- Agent Info Section -->
                        <div class="flex items-center gap-3 min-w-0">
                            <h1 class="text-xl font-semibold truncate">{{ agent.name }}</h1>

                            <!-- Separator -->
                            <div class="w-px h-4 bg-border flex-shrink-0"></div>

                            <!-- Agent Type -->
                            <div class="flex items-center gap-1.5 flex-shrink-0">
                                <Bot class="h-4 w-4 text-blue-500" />
                                <span class="text-sm font-medium text-blue-500 capitalize">
                                    {{ agent.type }}
                                </span>
                            </div>

                            <!-- Separator -->
                            <div class="w-px h-4 bg-border flex-shrink-0"></div>

                            <!-- Public/Protected Status -->
                            <div class="flex items-center gap-1.5 flex-shrink-0">
                                <component
                                    :is="agent.is_public ? Eye : Lock"
                                    class="h-4 w-4"
                                    :class="[
                                        agent.is_public
                                            ? 'text-red-500'
                                            : 'text-green-500'
                                    ]"
                                />
                                <span
                                    class="text-sm font-medium"
                                    :class="[
                                        agent.is_public
                                            ? 'text-red-500'
                                            : 'text-green-500'
                                    ]"
                                >
                                    {{ agent.is_public ? 'Public' : 'Protected' }}
                                </span>

                                <!-- Public Notice -->
                                <template v-if="agent.is_public">
                                    <span class="text-xs text-muted-foreground">(accessible by everyone)</span>
                                    <Link
                                        :href="route('agents.security', { agent: agent.id })"
                                        class="text-xs text-blue-500 hover:text-blue-600 underline ml-1"
                                    >
                                        Security
                                    </Link>
                                </template>
                            </div>
                        </div>

                        <!-- Actions Section -->
                        <div class="flex items-center md:flex-shrink-0">
                            <Button
                                :href="`${agent.url}/chat`"
                                as="a"
                                class="gap-2"
                                target="_blank"
                                variant="outline"
                                size="sm"
                            >
                                <Bot class="h-4 w-4" />
                                <span class="hidden sm:inline">View Agent</span>
                                <span class="sm:hidden">View</span>
                                <ChevronRight class="h-4 w-4" />
                            </Button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sidebar Toggle -->
            <div class="container mx-auto max-w-7xl px-4">
                <button
                    @click="isNavigationCollapsed = !isNavigationCollapsed"
                    class="flex items-center gap-2 text-sm text-muted-foreground hover:text-foreground transition-colors mb-4"
                >
                    <component :is="isNavigationCollapsed ? PanelLeftOpen : PanelLeftClose" class="h-4 w-4" />
                    {{ isNavigationCollapsed ? 'Expand Sidebar' : 'Collapse Sidebar' }}
                </button>
            </div>

            <!-- Main Content -->
            <div class="container mx-auto max-w-7xl px-4 py-4">
                <div class="flex flex-col lg:flex-row gap-8">
                    <!-- Vertical Navigation -->
                    <nav
                        :class="[
                            'flex-shrink-0 space-y-4 transition-all duration-300',
                            isNavigationCollapsed
                                ? 'w-16'
                                : 'w-full lg:w-64'
                        ]"
                    >
                        <!-- UI Section Container -->
                        <div class="bg-card rounded-lg shadow-sm border">
                            <template v-if="!isNavigationCollapsed">
                                <button
                                    @click="isUiSectionExpanded = !isUiSectionExpanded"
                                    class="w-full p-4 border-b flex items-center justify-between hover:bg-muted/50 transition-colors"
                                >
                                    <h2 class="font-semibold text-foreground">UI</h2>
                                    <component
                                        :is="isUiSectionExpanded ? ChevronUp : ChevronDown"
                                        class="h-4 w-4 text-muted-foreground"
                                    />
                                </button>
                                <div v-show="isUiSectionExpanded" class="p-2">
                                    <Link
                                        v-for="item in uiNavigationItems"
                                        :key="item.id"
                                        :href="route(item.route, { agent: agent.id })"
                                        :class="cn(
                                            'w-full flex items-center gap-3 px-3 py-2 text-sm font-medium rounded-md transition-colors',
                                            isActiveRoute(item.route)
                                                ? 'bg-primary text-primary-foreground'
                                                : 'text-muted-foreground hover:bg-muted'
                                        )"
                                    >
                                        <component :is="item.icon" class="h-4 w-4 flex-shrink-0" />
                                        <span>{{ item.label }}</span>
                                    </Link>
                                </div>
                            </template>

                            <!-- Collapsed UI Icons -->
                            <template v-else>
                                <TooltipProvider>
                                    <div class="p-2 space-y-2">
                                        <Tooltip v-for="item in uiNavigationItems" :key="item.id">
                                            <TooltipTrigger as-child>
                                                <Link
                                                    :href="route(item.route, { agent: agent.id })"
                                                    :class="cn(
                                                        'w-full flex items-center justify-center p-2 rounded-md transition-colors',
                                                        isActiveRoute(item.route)
                                                            ? 'bg-primary text-primary-foreground'
                                                            : 'text-muted-foreground hover:bg-muted'
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

                        <!-- Control Panel Section Container -->
                        <div class="bg-card rounded-lg shadow-sm border">
                            <template v-if="!isNavigationCollapsed">
                                <button
                                    @click="isControlPanelExpanded = !isControlPanelExpanded"
                                    class="w-full p-4 border-b flex items-center justify-between hover:bg-muted/50 transition-colors"
                                >
                                    <h2 class="font-semibold text-foreground">Control Panel</h2>
                                    <component
                                        :is="isControlPanelExpanded ? ChevronUp : ChevronDown"
                                        class="h-4 w-4 text-muted-foreground"
                                    />
                                </button>
                                <div v-show="isControlPanelExpanded">
                                    <div class="p-2">
                                        <Link
                                            v-for="item in controlPanelNavigationItems"
                                            :key="item.id"
                                            :href="route(item.route, { agent: agent.id })"
                                            :class="cn(
                                                'w-full flex items-center gap-3 px-3 py-2 text-sm font-medium rounded-md transition-colors',
                                                isActiveRoute(item.route)
                                                    ? 'bg-primary text-primary-foreground'
                                                    : 'text-muted-foreground hover:bg-muted'
                                            )"
                                        >
                                            <component :is="item.icon" class="h-4 w-4 flex-shrink-0" />
                                            <span>{{ item.label }}</span>
                                        </Link>
                                    </div>

                                    <!-- Danger Zone in Navigation -->
                                    <div class="p-2 border-t mt-2">
                                        <button
                                            @click="showDeleteDialog = true"
                                            class="w-full flex items-center gap-3 px-3 py-2 text-sm font-medium rounded-md text-destructive hover:bg-destructive/10 transition-colors"
                                        >
                                            <Trash2 class="h-4 w-4 flex-shrink-0" />
                                            <span>Delete Agent</span>
                                        </button>
                                    </div>
                                </div>
                            </template>

                            <!-- Collapsed Control Panel Icons -->
                            <template v-else>
                                <TooltipProvider>
                                    <div class="p-2 space-y-2">
                                        <Tooltip v-for="item in controlPanelNavigationItems" :key="item.id">
                                            <TooltipTrigger as-child>
                                                <Link
                                                    :href="route(item.route, { agent: agent.id })"
                                                    :class="cn(
                                                        'w-full flex items-center justify-center p-2 rounded-md transition-colors',
                                                        isActiveRoute(item.route)
                                                            ? 'bg-primary text-primary-foreground'
                                                            : 'text-muted-foreground hover:bg-muted'
                                                    )"
                                                >
                                                    <component :is="item.icon" class="h-4 w-4" />
                                                </Link>
                                            </TooltipTrigger>
                                            <TooltipContent side="right">
                                                <p>{{ item.tooltip }}</p>
                                            </TooltipContent>
                                        </Tooltip>

                                        <!-- Collapsed Delete Button -->
                                        <Tooltip>
                                            <TooltipTrigger as-child>
                                                <button
                                                    @click="showDeleteDialog = true"
                                                    class="w-full flex items-center justify-center p-2 rounded-md text-destructive hover:bg-destructive/10 transition-colors"
                                                >
                                                    <Trash2 class="h-4 w-4" />
                                                </button>
                                            </TooltipTrigger>
                                            <TooltipContent side="right">
                                                <p>Delete agent permanently</p>
                                            </TooltipContent>
                                        </Tooltip>
                                    </div>
                                </TooltipProvider>
                            </template>
                        </div>
                    </nav>

                    <!-- Content Area -->
                    <div class="flex-1 min-w-0">
                        <slot />
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
    </AppLayout>
</template><style scoped>
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


