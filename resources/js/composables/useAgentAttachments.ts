import axios from 'axios';
import { computed, ref } from 'vue';

export interface Agent {
    id: string;
    name: string;
}

interface AgentsPayload {
    attached: Agent[];
    available: Agent[];
}

type Kind = 'function' | 'connector' | 'member';

/**
 * Drives the Agents tab in the Functions, Connectors, and Members sheets —
 * a single list of every accessible agent with one toggle (on = attached).
 * All three resources expose identical endpoints; only the route names and
 * the id param key differ.
 */
export function useAgentAttachments(kind: Kind) {
    const attached = ref<Agent[]>([]);
    const available = ref<Agent[]>([]);
    const loading = ref(false);
    const busyId = ref<string | null>(null);

    function url(action: 'index' | 'attach' | 'detach', resourceId: string, agentId?: string): string {
        const segment = kind === 'function' ? 'functions' : kind === 'connector' ? 'connectors' : 'members';
        const idKey = kind === 'function' ? 'external_id' : kind === 'connector' ? 'slug' : 'chatter';
        const params: Record<string, string | undefined> = { [idKey]: resourceId, agent: agentId };
        return route(`settings.${segment}.agents.${action}`, params);
    }

    function apply(payload: AgentsPayload): void {
        attached.value = payload.attached;
        available.value = payload.available;
    }

    async function load(resourceId: string): Promise<void> {
        loading.value = true;
        try {
            const { data } = await axios.get<AgentsPayload>(url('index', resourceId));
            apply(data);
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
            const { data } = await axios.request<AgentsPayload>({
                method: isAttached ? 'delete' : 'post',
                url: url(isAttached ? 'detach' : 'attach', resourceId, agentId),
            });
            apply(data);
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
