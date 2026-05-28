<script setup lang="ts">
import CreateAgentModal from '@/components/CreateAgentModal.vue';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/AppLayout.vue';
import type { SharedData } from '@/types';
import { Head, router, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import { Bot, Code, Loader2, MessageSquare, Plus, Search } from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';

const page = usePage<SharedData>();

type Agent = Domain.Agents.Data.Resources.AgentResourceData;

const agents = ref<Agent[]>([]);
const isLoading = ref(true);
const searchQuery = ref('');

const showCreateAgentModal = ref(false);

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

const getAgentTypeStyle = (type: string) => {
    const lowerType = type.toLowerCase();
    if (lowerType === 'knowledge_base') {
        return 'bg-green-100 text-green-700 dark:bg-green-900/20 dark:text-green-400';
    } else if (lowerType === 'widget') {
        return 'bg-blue-100 text-blue-700 dark:bg-blue-900/20 dark:text-blue-400';
    }
    return 'bg-primary/10 text-primary';
};

const getAgentTypeIcon = (type: string) => {
    const lowerType = type.toLowerCase();
    if (lowerType === 'knowledge_base') {
        return MessageSquare;
    } else if (lowerType === 'widget') {
        return Code;
    }
    return Bot;
};

const getAgentTypeLabel = (type: string) => {
    const lowerType = type.toLowerCase();
    if (lowerType === 'knowledge_base') return 'Knowledge base agent';
    if (lowerType === 'widget') return 'Embeddable widget';
    return 'Agent';
};

const openAgent = (agent: Agent) => {
    router.visit(route('agents.manager', { agent: agent.external_id }));
};
</script>

<template>
    <AppLayout>
        <Head title="My Agents" />

        <div class="mx-auto w-full max-w-6xl space-y-6 py-8">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <h1 class="text-xl font-semibold">My Agents</h1>
                    <p class="text-muted-foreground text-sm">Create and manage your AI agents.</p>
                </div>
                <Button v-if="canCreateAgent" @click="showCreateAgentModal = true">
                    <Plus class="mr-1 h-4 w-4" />
                    Create agent
                </Button>
            </div>

            <div class="relative max-w-sm">
                <Search class="text-muted-foreground absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2" />
                <Input v-model="searchQuery" placeholder="Search agents" class="pl-9" />
            </div>

            <div v-if="isLoading" class="text-muted-foreground flex items-center gap-2 py-12 text-sm">
                <Loader2 class="h-4 w-4 animate-spin" />
                Loading agents
            </div>

            <Card v-else-if="agents.length === 0" class="flex h-64 flex-col items-center justify-center">
                <Bot class="text-muted-foreground h-12 w-12 opacity-50" />
                <h3 class="mt-4 text-xl font-medium">No agents yet</h3>
                <p v-if="canCreateAgent" class="text-muted-foreground mt-2 text-center">
                    Create your first AI agent to start building conversational experiences
                </p>
                <p v-else class="text-muted-foreground mt-2 text-center">Only team owners can create agents. Contact your team owner.</p>
                <Button v-if="canCreateAgent" size="lg" @click="showCreateAgentModal = true" class="mt-6 flex items-center gap-2">
                    <Plus class="h-4 w-4" />
                    Create agent
                </Button>
            </Card>

            <div v-else-if="filteredAgents.length === 0" class="text-muted-foreground py-12 text-center text-sm">
                No agents found matching "{{ searchQuery }}".
                <Button variant="link" class="ml-1 h-auto p-0" @click="searchQuery = ''">Clear search</Button>
            </div>

            <div v-else class="rounded-lg border">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead class="h-11 pl-5">Agent name</TableHead>
                            <TableHead class="h-11 w-32">Messages</TableHead>
                            <TableHead class="h-11 w-32">Members</TableHead>
                            <TableHead class="h-11 w-40 pr-5">Created</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow
                            v-for="agent in filteredAgents"
                            :key="agent.external_id"
                            class="hover:bg-muted/40 cursor-pointer"
                            @click="openAgent(agent)"
                        >
                            <TableCell class="py-4 pl-5">
                                <div class="flex items-center gap-4">
                                    <div
                                        class="flex h-10 w-10 shrink-0 items-center justify-center rounded-md"
                                        :class="getAgentTypeStyle(agent.type)"
                                    >
                                        <component :is="getAgentTypeIcon(agent.type)" class="h-5 w-5" />
                                    </div>
                                    <div class="min-w-0">
                                        <p class="leading-tight font-medium">{{ agent.name }}</p>
                                        <p class="text-muted-foreground mt-1 line-clamp-1 text-xs">{{ getAgentTypeLabel(agent.type) }}</p>
                                    </div>
                                </div>
                            </TableCell>
                            <TableCell class="py-4 text-sm">{{ agent.total_messages || 0 }}</TableCell>
                            <TableCell class="py-4 text-sm">
                                <span v-if="agent.type.toLowerCase() === 'widget'" class="text-muted-foreground">—</span>
                                <span v-else>{{ agent.total_members || 0 }}</span>
                            </TableCell>
                            <TableCell class="text-muted-foreground py-4 pr-5 text-sm">{{ formatDate(agent.created_at) }}</TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </div>
        </div>

        <CreateAgentModal v-model:open="showCreateAgentModal" @agent-created="refreshAgents" />
    </AppLayout>
</template>
