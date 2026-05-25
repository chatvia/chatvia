<script setup lang="ts">
import CreateAgentModal from '@/components/CreateAgentModal.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import type { SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import { Bot, Code, Loader2, MessageSquare, Plus, Search } from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';

const page = usePage<SharedData>();

type Agent = Domain.Agents.Data.Resources.AgentResourceData;

// Fetch agents from the server
const agents = ref<Agent[]>([]);
const isLoading = ref(true);
const searchQuery = ref('');

// Modal state
const showCreateAgentModal = ref(false);

// Permission checking - only team owners can create agents
const canCreateAgent = computed(() => {
    const currentTeam = page.props.currentTeam;
    if (!currentTeam) return false;

    return currentTeam.is_owner;
});

onMounted(async () => {
    try {
        const response = await axios.get(route('agents.index'));
        agents.value = response.data;
    } catch (error) {
        console.error('Failed to fetch agents:', error);
    } finally {
        isLoading.value = false;
    }
});

// Function to refresh agents list
const refreshAgents = async () => {
    try {
        const response = await axios.get(route('agents.index'));
        agents.value = response.data;
    } catch (error) {
        console.error('Failed to fetch agents:', error);
    }
};

const filteredAgents = computed(() => {
    if (!searchQuery.value) return agents.value;

    const query = searchQuery.value.toLowerCase();
    return agents.value.filter(
        (agent) =>
            agent.name.toLowerCase().includes(query) ||
            agent.type.toLowerCase().includes(query) ||
            (agent.sub_domain && agent.sub_domain.toLowerCase().includes(query)),
    );
});

const formatDate = (dateString: string) => {
    return new Date(dateString).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
};

const getAgentTypeBadgeClass = (type: string) => {
    const lowerType = type.toLowerCase();
    if (lowerType === 'knowledge_base') {
        return 'rounded-full bg-green-100 p-2 text-green-700 dark:bg-green-900/20 dark:text-green-400';
    } else if (lowerType === 'widget') {
        return 'rounded-full bg-blue-100 p-2 text-blue-700 dark:bg-blue-900/20 dark:text-blue-400';
    } else {
        return 'rounded-full bg-primary/10 p-2 text-primary';
    }
};

const getAgentTypeIcon = (type: string) => {
    const lowerType = type.toLowerCase();
    if (lowerType === 'knowledge_base') {
        return MessageSquare;
    } else if (lowerType === 'widget') {
        return Code;
    } else {
        return Bot;
    }
};
</script>

<template>
    <AppLayout>
        <Head title="My Agents" />

        <div class="container mx-auto max-w-5xl py-8">
            <div class="mb-8 flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold tracking-tight">My Agents</h1>
                    <p class="text-muted-foreground mt-1">Create and manage your AI agents</p>
                </div>
                <Button v-if="canCreateAgent" size="lg" @click="showCreateAgentModal = true" class="flex items-center gap-2">
                    <Plus class="h-4 w-4" />
                    Create Agent
                </Button>
            </div>

            <!-- Search and filters -->
            <div class="mb-6 flex items-center">
                <div class="relative flex-1">
                    <Search class="text-muted-foreground absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2" />
                    <Input v-model="searchQuery" class="pl-10" placeholder="Search agents..." />
                </div>
            </div>

            <!-- Loading state -->
            <div v-if="isLoading" class="flex h-64 items-center justify-center">
                <Loader2 class="text-primary h-8 w-8 animate-spin" />
                <span class="ml-2 text-lg">Loading agents...</span>
            </div>

            <!-- Empty state -->
            <Card v-else-if="agents.length === 0" class="flex h-64 flex-col items-center justify-center">
                <Bot class="text-muted-foreground h-12 w-12 opacity-50" />
                <h3 class="mt-4 text-xl font-medium">No agents yet</h3>
                <p v-if="canCreateAgent" class="text-muted-foreground mt-2 text-center">
                    Create your first AI agent to start building conversational experiences
                </p>
                <p v-else class="text-muted-foreground mt-2 text-center">Only team owners can create agents. Contact your team owner.</p>
                <Button v-if="canCreateAgent" size="lg" @click="showCreateAgentModal = true" class="flex items-center gap-2">
                    <Plus class="h-4 w-4" />
                    Create Agent
                </Button>
            </Card>

            <!-- No search results -->
            <Card v-else-if="filteredAgents.length === 0" class="flex h-64 flex-col items-center justify-center">
                <Search class="text-muted-foreground h-12 w-12 opacity-50" />
                <h3 class="mt-4 text-xl font-medium">No matching agents</h3>
                <p class="text-muted-foreground mt-2 text-center">No agents found matching "{{ searchQuery }}"</p>
                <Button variant="outline" class="mt-6" @click="searchQuery = ''"> Clear Search </Button>
            </Card>

            <!-- Agents grid -->
            <div v-else class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                <Card v-for="agent in filteredAgents" :key="agent.external_id" class="overflow-hidden">
                    <CardHeader class="pb-3">
                        <div class="flex items-center justify-between">
                            <CardTitle class="flex items-center gap-2 text-xl">
                                <Bot class="h-5 w-5 flex-shrink-0" />
                                <span class="truncate">
                                    <Link :href="route('agents.manager', { agent: agent.external_id })">{{ agent.name }}</Link>
                                </span>
                            </CardTitle>
                            <div :class="getAgentTypeBadgeClass(agent.type)">
                                <component :is="getAgentTypeIcon(agent.type)" class="h-4 w-4" />
                            </div>
                        </div>
                    </CardHeader>

                    <CardContent>
                        <div class="grid gap-4 text-sm" :class="agent.type.toLowerCase() === 'widget' ? 'grid-cols-1' : 'grid-cols-2'">
                            <div class="flex flex-col">
                                <span class="text-muted-foreground">Messages</span>
                                <span class="text-lg font-medium">{{ agent.total_messages || 0 }}</span>
                            </div>
                            <div v-if="agent.type.toLowerCase() !== 'widget'" class="flex flex-col">
                                <span class="text-muted-foreground">Members</span>
                                <span class="text-lg font-medium">{{ agent.total_members || 0 }}</span>
                            </div>
                            <div class="flex flex-col" :class="agent.type.toLowerCase() === 'widget' ? '' : 'col-span-2'">
                                <span class="text-muted-foreground">Created</span>
                                <span>{{ formatDate(agent.created_at) }}</span>
                            </div>
                        </div>
                    </CardContent>

                    <CardFooter class="flex justify-end border-t p-4">
                        <Button as-child size="sm">
                            <Link :href="route('agents.manager', { agent: agent.external_id })" class="gap-1"> Manage </Link>
                        </Button>
                    </CardFooter>
                </Card>
            </div>
        </div>

        <!-- Create Agent Modal -->
        <CreateAgentModal v-model:open="showCreateAgentModal" @agent-created="refreshAgents" />
    </AppLayout>
</template>

<style scoped>
.card-hover-effect {
    transition:
        transform 0.2s ease,
        box-shadow 0.2s ease;
}

.card-hover-effect:hover {
    transform: translateY(-2px);
    box-shadow:
        0 10px 25px -5px rgba(0, 0, 0, 0.1),
        0 8px 10px -6px rgba(0, 0, 0, 0.1);
}
</style>
