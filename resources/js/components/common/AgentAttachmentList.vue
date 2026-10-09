<script setup lang="ts">
/**
 * The "which agents can use this" list shown in the Agents tab of the
 * Functions and Connectors sheets: an intro line, then every accessible agent
 * with an on/off switch (on = attached). State and requests live in
 * `useAgentAttachments`; this component only renders and reports toggles.
 */
import Switch from '@/components/ui/switch/Switch.vue';
import { LoaderCircle } from 'lucide-vue-next';

export interface AgentAttachmentItem {
    id: string;
    name: string;
    attached: boolean;
}

defineProps<{
    intro: string;
    loadingText: string;
    emptyText: string;
    agents: AgentAttachmentItem[];
    loading: boolean;
    /** The agent whose switch is waiting for the server. */
    busyId: string | null;
}>();
const emit = defineEmits<{ toggle: [agentId: string] }>();
</script>

<template>
    <p class="text-muted-foreground mb-3 text-sm">{{ intro }}</p>

    <div v-if="loading" class="text-muted-foreground flex items-center gap-2 py-6 text-sm">
        <LoaderCircle class="size-4 animate-spin" />
        {{ loadingText }}
    </div>

    <div v-else-if="agents.length === 0" class="text-muted-foreground py-6 text-center text-sm">
        {{ emptyText }}
    </div>

    <ul v-else class="divide-border divide-y">
        <li v-for="agent in agents" :key="agent.id" class="flex items-center justify-between gap-3 py-3 first:pt-0 last:pb-0">
            <span class="truncate text-sm font-medium">{{ agent.name }}</span>
            <Switch :model-value="agent.attached" :disabled="busyId === agent.id" @update:model-value="() => emit('toggle', agent.id)" />
        </li>
    </ul>
</template>
