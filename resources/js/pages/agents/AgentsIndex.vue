<script setup lang="ts">
import { ref, onMounted, computed } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import CreateAgentModal from '@/components/CreateAgentModal.vue';
import {
    Card,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle
} from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Bot,
    Plus,
    Search,
    ExternalLink,
    Loader2
} from 'lucide-vue-next';
import axios from 'axios';

const page = usePage();

// Define agent type
interface Agent {
    id: number;
    name: string;
    type: string;
    sub_domain: string;
    is_public: boolean;
    created_at: string;
    total_messages?: number;
    unique_users?: number;
}

// Fetch agents from the server
const agents = ref<Agent[]>([]);
const isLoading = ref(true);
const searchQuery = ref('');

// Modal state
const showCreateAgentModal = ref(false);

onMounted(async () => {
    try {
        const response = await axios.get(route('agents.list'));
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
        const response = await axios.get(route('agents.list'));
        agents.value = response.data;
    } catch (error) {
        console.error('Failed to fetch agents:', error);
    }
};

const filteredAgents = computed(() => {
    if (!searchQuery.value) return agents.value;

    const query = searchQuery.value.toLowerCase();
    return agents.value.filter(agent =>
        agent.name.toLowerCase().includes(query) ||
        agent.type.toLowerCase().includes(query) ||
        agent.sub_domain.toLowerCase().includes(query)
    );
});

const formatDate = (dateString: string) => {
    return new Date(dateString).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric'
    });
};

const getAgentTypeBadgeClass = (type: string) => {
    const lowerType = type.toLowerCase();
    if (lowerType === 'knowledge_base') {
        return 'rounded-full bg-blue-100 px-2 py-1 text-xs font-medium text-blue-700 dark:bg-blue-900/20 dark:text-blue-400';
    } else if (lowerType === 'widget') {
        return 'rounded-full bg-green-100 px-2 py-1 text-xs font-medium text-green-700 dark:bg-green-900/20 dark:text-green-400';
    } else {
        return 'rounded-full bg-primary/10 px-2 py-1 text-xs font-medium text-primary';
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
                    <p class="mt-1 text-muted-foreground">
                        Create and manage your AI agents
                    </p>
                </div>
                <Button size="lg" @click="showCreateAgentModal = true" class="flex items-center gap-2">
                    <Plus class="h-4 w-4" />
                    Create Agent
                </Button>
            </div>

            <!-- Search and filters -->
            <div class="mb-6 flex items-center">
                <div class="relative flex-1">
                    <Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                    <Input
                        v-model="searchQuery"
                        class="pl-10"
                        placeholder="Search agents..."
                    />
                </div>
            </div>

            <!-- Loading state -->
            <div v-if="isLoading" class="flex h-64 items-center justify-center">
                <Loader2 class="h-8 w-8 animate-spin text-primary" />
                <span class="ml-2 text-lg">Loading agents...</span>
            </div>

            <!-- Empty state -->
            <Card v-else-if="agents.length === 0" class="flex h-64 flex-col items-center justify-center">
                <Bot class="h-12 w-12 text-muted-foreground opacity-50" />
                <h3 class="mt-4 text-xl font-medium">No agents yet</h3>
                <p class="mt-2 text-center text-muted-foreground">
                    Create your first AI agent to start building conversational experiences
                </p>
                <Button size="lg" @click="showCreateAgentModal = true" class="flex items-center gap-2">
                    <Plus class="h-4 w-4" />
                    Create Agent
                </Button>
            </Card>

            <!-- No search results -->
            <Card v-else-if="filteredAgents.length === 0" class="flex h-64 flex-col items-center justify-center">
                <Search class="h-12 w-12 text-muted-foreground opacity-50" />
                <h3 class="mt-4 text-xl font-medium">No matching agents</h3>
                <p class="mt-2 text-center text-muted-foreground">
                    No agents found matching "{{ searchQuery }}"
                </p>
                <Button variant="outline" class="mt-6" @click="searchQuery = ''">
                    Clear Search
                </Button>
            </Card>

            <!-- Agents grid -->
            <div v-else class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                <Card v-for="agent in filteredAgents" :key="agent.id" class="overflow-hidden">
                    <CardHeader class="pb-3">
                        <div class="flex items-center justify-between">
                            <CardTitle class="flex items-center gap-2 text-xl">
                                <Bot class="h-5 w-5 flex-shrink-0" />
                                <span class="truncate">{{ agent.name }}</span>
                            </CardTitle>
                            <span :class="getAgentTypeBadgeClass(agent.type)">
                                {{ agent.type === 'knowledge_base' ? 'ChatGPT-style' : agent.type === 'widget' ? 'Widget' : agent.type }}
                            </span>
                        </div>

                    </CardHeader>

                    <CardContent>
                        <div class="grid grid-cols-2 gap-4 text-sm">
                            <div class="flex flex-col">
                                <span class="text-muted-foreground">Messages</span>
                                <span class="text-lg font-medium">{{ agent.total_messages || 0 }}</span>
                            </div>
                            <div class="flex flex-col">
                                <span class="text-muted-foreground">Members</span>
                                <span class="text-lg font-medium">{{ agent.total_members || 0 }}</span>
                            </div>
                            <div class="flex flex-col col-span-2">
                                <span class="text-muted-foreground">Created</span>
                                <span>{{ formatDate(agent.created_at) }}</span>
                            </div>
                        </div>
                    </CardContent>

                    <CardFooter class="flex justify-between border-t p-4">
                        <Button variant="outline" size="sm" :href="`${page.props.schema}://${agent.sub_domain}.${page.props.domain}`" as="a" target="_blank" class="gap-1">
                            <ExternalLink class="h-3.5 w-3.5" />
                            View
                        </Button>
                        <Button as-child size="sm">
                            <Link :href="route('agents.preview', {agent: agent.id})" class="gap-1">
                                Manage
                            </Link>
                        </Button>
                    </CardFooter>
                </Card>
            </div>
        </div>

        <!-- Create Agent Modal -->
        <CreateAgentModal
            v-model:open="showCreateAgentModal"
            :can_register_custom_sub_domain="(page.props.can_register_custom_sub_domain as boolean)"
            :knowledge_based_system_instruction="(page.props.knowledge_based_system_instruction as string)"
            @agent-created="refreshAgents"
        />
    </AppLayout>
</template>

<style scoped>
.card-hover-effect {
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.card-hover-effect:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.1);
}
</style>
