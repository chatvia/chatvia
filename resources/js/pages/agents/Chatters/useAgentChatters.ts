/**
 * State for the agent → Chatters tab: every workspace chatter with a
 * `has_access` flag for this agent, the search filter, and the grant call.
 * Access changes patch the local list (and re-sort it like the server does)
 * instead of refetching, so the table updates at once.
 */
import { listAgentChatters, type AgentChatterRow } from '@/api/chatters';
import { router } from '@inertiajs/vue3';
import { computed, ref, toValue, type MaybeRefOrGetter } from 'vue';
import { useI18n } from 'vue-i18n';
import { toast } from 'vue-sonner';

type Agent = Domain.Agent.Data.Resources.Account.AgentResourceData;

export function useAgentChatters(agentSource: MaybeRefOrGetter<Agent>) {
    const { t } = useI18n();

    const members = ref<AgentChatterRow[]>([]);
    const isLoading = ref(true);

    const fetchMembers = async () => {
        const agent = toValue(agentSource);
        if (agent.is_public) {
            // Nothing to show for a public agent — the notice block in the
            // template stands in for the table.
            isLoading.value = false;
            members.value = [];
            return;
        }
        isLoading.value = true;
        try {
            members.value = await listAgentChatters(agent.external_id);
        } catch (error) {
            console.error('Failed to fetch members:', error);
            toast.error(t('agents.chatters.load_error'));
        } finally {
            isLoading.value = false;
        }
    };

    // Search
    const searchQuery = ref('');
    const filteredMembers = computed(() => {
        const q = searchQuery.value.trim().toLowerCase();
        if (!q) return members.value;
        return members.value.filter((m) => m.name.toLowerCase().includes(q) || (m.email ?? '').toLowerCase().includes(q));
    });

    // Mirror the server-side sort: granted first, then alphabetical within each group.
    const resort = () => {
        members.value = [...members.value].sort((a, b) => {
            if (a.has_access !== b.has_access) return a.has_access ? -1 : 1;
            return a.name.toLowerCase().localeCompare(b.name.toLowerCase());
        });
    };

    // Flip one row's access flag so it moves into the right group.
    const setAccess = (externalId: string, hasAccess: boolean) => {
        members.value = members.value.map((m) => (m.external_id === externalId ? { ...m, has_access: hasAccess } : m));
        resort();
    };

    // Grant access to an existing chatter (idempotent server-side).
    const grantingId = ref<string | null>(null);

    const grantAccess = (member: AgentChatterRow) => {
        grantingId.value = member.external_id;
        router.post(
            route('agents.chatters.grant', {
                agent: toValue(agentSource).external_id,
                chatter: member.external_id,
            }),
            {},
            {
                preserveScroll: true,
                onSuccess: () => {
                    setAccess(member.external_id, true);
                    toast.success(t('agents.chatters.access_granted', { name: member.name }));
                },
                onError: () => toast.error(t('agents.chatters.grant_error')),
                onFinish: () => {
                    grantingId.value = null;
                },
            },
        );
    };

    return { members, isLoading, fetchMembers, searchQuery, filteredMembers, setAccess, grantingId, grantAccess };
}
