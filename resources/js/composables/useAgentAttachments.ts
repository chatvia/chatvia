/**
 * State for the Agents tab in the Functions, Connectors and Chatters sheets: a
 * single list of every accessible agent with one toggle (on = attached). The
 * HTTP calls live in `@/api/agentAttachments`; this keeps the lists, the
 * loading flag and the id of the agent whose toggle is in flight.
 */
import { attachAgent, detachAgent, fetchAgentAttachments, type AgentAttachmentKind, type AgentAttachments } from '@/api/agentAttachments';
import { computed, ref } from 'vue';

export interface Agent {
    id: string;
    name: string;
}

export function useAgentAttachments(kind: AgentAttachmentKind) {
    const attached = ref<Agent[]>([]);
    const available = ref<Agent[]>([]);
    const loading = ref(false);
    const busyId = ref<string | null>(null);

    function apply(payload: AgentAttachments): void {
        attached.value = payload.attached;
        available.value = payload.available;
    }

    async function load(resourceId: string): Promise<void> {
        loading.value = true;
        try {
            apply(await fetchAgentAttachments(kind, resourceId));
        } finally {
            loading.value = false;
        }
    }

    // Every accessible agent with an `attached` flag, sorted by name — drives
    // a single toggle list (toggle on = attached, off = detached).
    const all = computed(() =>
        [
            ...attached.value.map((a) => ({ id: a.id, name: a.name, attached: true })),
            ...available.value.map((a) => ({ id: a.id, name: a.name, attached: false })),
        ].sort((a, b) => a.name.localeCompare(b.name)),
    );

    async function toggleAttachment(resourceId: string, agentId: string): Promise<void> {
        const isAttached = attached.value.some((a) => a.id === agentId);
        busyId.value = agentId;
        try {
            apply(isAttached ? await detachAgent(kind, resourceId, agentId) : await attachAgent(kind, resourceId, agentId));
        } finally {
            busyId.value = null;
        }
    }

    return {
        attached,
        available,
        all,
        loading,
        busyId,
        load,
        toggleAttachment,
        reset: () => {
            attached.value = [];
            available.value = [];
        },
    };
}
