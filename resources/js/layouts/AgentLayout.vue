<script lang="ts" setup>
import { computed } from 'vue';
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
import { toast } from 'vue-sonner';
import type { BreadcrumbItem } from '@/types';

import {
    ArrowLeft,
    Bot,
    ChevronRight,
    Clock,
    Database,
    LoaderCircle,
    MessageSquare,
    Palette,
    Settings,
    TestTube,
    Trash2,
    Users,
    BarChart3,
    Globe,
    Lock,
    Eye,
    EyeOff,
    Power,
    PowerOff,
    Brain,
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
const isToggling = ref(false);

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

const navigationItems = [
    { id: 'stats', label: 'Statistics', icon: BarChart3, route: 'agents.stats' },
    { id: 'playground', label: 'Playground', icon: TestTube, route: 'agents.playground' },
    { id: 'settings', label: 'Settings', icon: Settings, route: 'agents.settings' },
    { id: 'models', label: 'AI Models', icon: Brain, route: 'agents.models' },
    { id: 'knowledge', label: 'Knowledge Base', icon: Database, route: 'agents.knowledge' },
    { id: 'starters', label: 'Starter Messages', icon: MessageSquare, route: 'agents.starters' },
    { id: 'history', label: 'Message History', icon: Clock, route: 'agents.history' },
    { id: 'appearance', label: 'Appearance', icon: Palette, route: 'agents.appearance' },
    { id: 'team', label: 'Members', icon: Users, route: 'agents.team' },
];

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

const toggleAgentStatus = () => {
    // Optimistically update the UI
    const newStatus = !props.agent.is_active;
    props.agent.is_active = newStatus;
    isToggling.value = true;

    router.patch(route('agents.activity', {
        agent: props.agent.id,
        is_active: newStatus
    }), {}, {
        onSuccess: () => {
            isToggling.value = false;
        },
        onError: () => {
            // Revert the optimistic update on error
            props.agent.is_active = !newStatus;
            isToggling.value = false;
        }
    });
};

const isActiveRoute = (routeName: string) => {
    const routeSuffix = routeName.split('.')[1]; // Extract 'stats', 'settings', etc.
    return currentRoute.value === `agents/${routeSuffix.charAt(0).toUpperCase() + routeSuffix.slice(1)}`;
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="title" />

        <div class="min-h-screen bg-background">
            <!-- Header -->
            <div class="bg-background">
                <div class="container mx-auto px-4 sm:px-6 lg:px-8 py-3 sm:py-4">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                        <div class="flex items-center gap-4">
                            <div class="flex items-center gap-3">
                                <h1 class="text-xl font-semibold">{{ agent.name }}</h1>

                                <!-- Agent Type -->
                                <div class="flex items-center gap-1.5">
                                    <Bot class="h-4 w-4 text-blue-500" />
                                    <span class="text-sm font-medium text-blue-500 capitalize">
                                        {{ agent.type }}
                                    </span>
                                </div>

                                <!-- Public/Private Status -->
                                <div class="flex items-center gap-1.5">
                                    <component
                                        :is="agent.is_public ? Eye : EyeOff"
                                        class="h-4 w-4"
                                        :class="[
                                            agent.is_public
                                                ? 'text-green-500'
                                                : 'text-red-500'
                                        ]"
                                    />
                                    <span
                                        class="text-sm font-medium"
                                        :class="[
                                            agent.is_public
                                                ? 'text-green-500'
                                                : 'text-red-500'
                                        ]"
                                    >
                                        {{ agent.is_public ? 'Public' : 'Private' }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div class="flex flex-wrap items-center gap-3">
                            <!-- Active/Inactive Toggle -->
                            <div class="flex items-center gap-2">
                                <span class="text-xs text-muted-foreground whitespace-nowrap font-bold">
                                    {{ agent.is_active ? 'Accessible' : 'Inaccessible' }}
                                </span>
                                <Button
                                    @click="toggleAgentStatus"
                                    :disabled="isToggling"
                                    variant="outline"
                                    size="sm"
                                    class="gap-1.5 h-8 px-3 border"
                                    :class="[
                                        agent.is_active
                                            ? 'border-red-500 text-red-500 hover:bg-red-50 bg-transparent'
                                            : 'border-green-500 bg-green-500 text-white hover:bg-green-600'
                                    ]"
                                >
                                    <component
                                        :is="agent.is_active ? PowerOff : Power"
                                        class="h-3.5 w-3.5"
                                        :class="[
                                            agent.is_active
                                                ? 'animate-pulse'
                                                : ''
                                        ]"
                                    />
                                    {{ agent.is_active ? 'Disable' : 'Enable' }}
                                    <LoaderCircle v-if="isToggling" class="h-3.5 w-3.5 animate-spin" />
                                </Button>
                            </div>

                            <Button
                                :href="`${agent.url}/chat`"
                                as="a"
                                class="gap-2"
                                target="_blank"
                                variant="outline"
                            >
                                <Bot class="h-4 w-4" />
                                View Agent
                                <ChevronRight class="h-4 w-4" />
                            </Button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Main Content -->
            <div class="container mx-auto max-w-7xl px-4 py-8">
                <div class="flex flex-col lg:flex-row gap-8">
                    <!-- Vertical Navigation -->
                    <nav class="w-full lg:w-64 flex-shrink-0">
                        <div class="bg-card rounded-lg shadow-sm border">
                            <div class="p-4 border-b">
                                <h2 class="font-semibold text-foreground">Settings</h2>
                            </div>
                            <div class="p-2">
                                <Link
                                    v-for="item in navigationItems"
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
