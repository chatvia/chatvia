/**
 * State for the workspace People → Chatters page: the chatter list, the search
 * filter and small helpers that patch one row after the detail sheet or a
 * confirm dialog changed it, so the table updates without a refetch.
 */
import { listWorkspaceChatters, type WorkspaceChatterRow } from '@/dashboard/api/chatters';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { toast } from 'vue-sonner';

export function useWorkspaceChatters() {
    const { t } = useI18n();

    const members = ref<WorkspaceChatterRow[]>([]);
    const isLoading = ref(true);

    const fetchMembers = async () => {
        isLoading.value = true;
        try {
            members.value = await listWorkspaceChatters();
        } catch (error) {
            console.error('Failed to fetch members:', error);
            toast.error(t('workspace.members.toast.load_failed'));
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

    /** Replace the row with the same id by `updated`. */
    const replaceMember = (updated: WorkspaceChatterRow) => {
        members.value = members.value.map((m) => (m.external_id === updated.external_id ? updated : m));
    };

    /** Set the number of agents a chatter can use. */
    const setAgentsCount = (externalId: string, agentsCount: number) => {
        members.value = members.value.map((m) => (m.external_id === externalId ? { ...m, agents_count: agentsCount } : m));
    };

    /** Drop a deleted chatter from the list. */
    const removeMember = (externalId: string) => {
        members.value = members.value.filter((m) => m.external_id !== externalId);
    };

    return { members, isLoading, fetchMembers, searchQuery, filteredMembers, replaceMember, setAgentsCount, removeMember };
}
