<script setup lang="ts">
import { fetchAgents, type AgentListItem } from '@/api/agents';
import CreateAgentModal from '@/components/CreateAgentModal.vue';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { useDateFormat } from '@/composables/useDateFormat';
import AppLayout from '@/layouts/AppLayout.vue';
import type { SharedData } from '@/types';
import { Head, router, usePage } from '@inertiajs/vue3';
import { Bot, Loader2, Plus, Search } from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();
const { formatDate } = useDateFormat();

const page = usePage<SharedData>();

type Agent = AgentListItem;

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
        agents.value = await fetchAgents();
    } catch (error) {
        console.error('Failed to fetch agents:', error);
    } finally {
        isLoading.value = false;
    }
});

const refreshAgents = async () => {
    try {
        agents.value = await fetchAgents();
    } catch (error) {
        console.error('Failed to fetch agents:', error);
    }
};

const filteredAgents = computed(() => {
    if (!searchQuery.value) return agents.value;

    const query = searchQuery.value.toLowerCase();
    return agents.value.filter(
        (agent) => agent.name.toLowerCase().includes(query) || (agent.sub_domain && agent.sub_domain.toLowerCase().includes(query)),
    );
});

const openAgent = (agent: Agent) => {
    router.visit(route('agents.manager', { agent: agent.external_id }));
};
</script>

<template>
    <AppLayout>
        <Head :title="t('agents.list.title')" />

        <div class="mx-auto w-full max-w-6xl space-y-6 px-4 py-8">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-bold tracking-tight">{{ t('agents.list.heading') }}</h1>
                    <p class="text-muted-foreground text-sm">{{ t('agents.list.subtitle') }}</p>
                </div>
                <Button v-if="canCreateAgent" @click="showCreateAgentModal = true">
                    <Plus class="me-1 h-4 w-4" />
                    {{ t('agents.list.create_agent') }}
                </Button>
            </div>

            <div class="relative max-w-sm">
                <Search class="text-muted-foreground absolute start-3 top-1/2 h-4 w-4 -translate-y-1/2" />
                <Input v-model="searchQuery" :placeholder="t('agents.list.search_placeholder')" class="ps-9" />
            </div>

            <div v-if="isLoading" class="text-muted-foreground flex items-center gap-2 py-12 text-sm">
                <Loader2 class="h-4 w-4 animate-spin" />
                {{ t('agents.list.loading') }}
            </div>

            <Card v-else-if="agents.length === 0" class="flex h-64 flex-col items-center justify-center">
                <Bot class="text-muted-foreground h-12 w-12 opacity-50" />
                <h3 class="mt-4 text-xl font-medium">{{ t('agents.list.empty_title') }}</h3>
                <p v-if="canCreateAgent" class="text-muted-foreground mt-2 text-center">
                    {{ t('agents.list.empty_description') }}
                </p>
                <p v-else class="text-muted-foreground mt-2 text-center">{{ t('agents.list.empty_no_permission') }}</p>
                <Button v-if="canCreateAgent" size="lg" @click="showCreateAgentModal = true" class="mt-6 flex items-center gap-2">
                    <Plus class="h-4 w-4" />
                    {{ t('agents.list.create_agent') }}
                </Button>
            </Card>

            <div v-else-if="filteredAgents.length === 0" class="text-muted-foreground py-12 text-center text-sm">
                {{ t('agents.list.no_results', { query: searchQuery }) }}
                <Button variant="link" class="ms-1 h-auto p-0" @click="searchQuery = ''">{{ t('agents.list.clear_search') }}</Button>
            </div>

            <div v-else class="rounded-lg border">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead class="h-11 ps-5">{{ t('agents.list.table.name') }}</TableHead>
                            <TableHead class="h-11 w-32">{{ t('agents.list.table.messages') }}</TableHead>
                            <TableHead class="h-11 w-32">{{ t('agents.list.table.members') }}</TableHead>
                            <TableHead class="h-11 w-40 pe-5">{{ t('agents.list.table.created') }}</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow
                            v-for="agent in filteredAgents"
                            :key="agent.external_id"
                            class="hover:bg-muted/40 cursor-pointer"
                            @click="openAgent(agent)"
                        >
                            <TableCell class="py-4 ps-5">
                                <div class="flex items-center gap-4">
                                    <div class="bg-primary/10 text-primary flex h-10 w-10 shrink-0 items-center justify-center rounded-md">
                                        <Bot class="h-5 w-5" />
                                    </div>
                                    <div class="min-w-0">
                                        <p class="leading-tight font-medium">{{ agent.name }}</p>
                                    </div>
                                </div>
                            </TableCell>
                            <TableCell class="py-4 text-sm">{{ agent.total_messages || 0 }}</TableCell>
                            <TableCell class="py-4 text-sm">{{ agent.total_members || 0 }}</TableCell>
                            <TableCell class="text-muted-foreground py-4 pe-5 text-sm">{{ formatDate(agent.created_at) }}</TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </div>
        </div>

        <CreateAgentModal v-model:open="showCreateAgentModal" @agent-created="refreshAgents" />
    </AppLayout>
</template>
