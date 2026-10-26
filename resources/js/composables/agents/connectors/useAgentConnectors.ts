/**
 * State for the agent's Connectors page: loads the catalog and custom rows,
 * filters them by the search box, and attaches / detaches connectors or flips
 * their "always on" flag, updating the row in place when the server agrees.
 */
import {
    attachConnector,
    type CustomConnectorRow,
    detachConnector,
    type FeaturedConnectorRow,
    fetchAgentConnectors,
    setConnectorAlwaysAvailable,
} from '@/api/agentConnectors';
import { useConnectorText } from '@/composables/useConnectorText';
import { computed, type MaybeRefOrGetter, onMounted, ref, toValue } from 'vue';
import { useI18n } from 'vue-i18n';
import { toast } from 'vue-sonner';

export function useAgentConnectors(agentId: MaybeRefOrGetter<string>) {
    const { t } = useI18n();
    const { connectorName, connectorDescription } = useConnectorText();

    const loading = ref(true);
    const featured = ref<FeaturedConnectorRow[]>([]);
    const customConnectors = ref<CustomConnectorRow[]>([]);
    const search = ref('');

    function matches(row: { name: string; description?: string | null; url?: string }): boolean {
        const q = search.value.trim().toLowerCase();
        if (!q) return true;
        return row.name.toLowerCase().includes(q) || (row.description ?? '').toLowerCase().includes(q) || (row.url ?? '').toLowerCase().includes(q);
    }

    // The server sends rows sorted by catalog priority. Re-sort only to float
    // attached ("enabled") rows above the rest; `sort` is stable, so the
    // priority order inside each group is kept.
    const filteredFeatured = computed(() =>
        featured.value
            .filter((f) => matches({ name: connectorName(f.connector), description: connectorDescription(f.connector) }))
            .slice()
            .sort((a, b) => Number(b.state === 'attached') - Number(a.state === 'attached')),
    );
    const filteredCustom = computed(() => customConnectors.value.filter(matches));

    async function load(): Promise<void> {
        loading.value = true;
        try {
            const response = await fetchAgentConnectors(toValue(agentId));
            featured.value = response.featured;
            customConnectors.value = response.custom;
        } catch {
            toast.error(t('agents.sources.connectors.load_failed'));
        } finally {
            loading.value = false;
        }
    }

    function updateFeatured(row: FeaturedConnectorRow, changes: Partial<FeaturedConnectorRow>): void {
        const i = featured.value.findIndex((f) => f.connector.slug === row.connector.slug);
        if (i !== -1) featured.value[i] = { ...featured.value[i], ...changes };
    }

    function updateCustom(row: CustomConnectorRow, changes: Partial<CustomConnectorRow>): void {
        const i = customConnectors.value.findIndex((c) => c.external_id === row.external_id);
        if (i !== -1) customConnectors.value[i] = { ...customConnectors.value[i], ...changes };
    }

    async function attach(slug: string, onSuccess: () => void): Promise<void> {
        try {
            await attachConnector(toValue(agentId), slug);
            onSuccess();
        } catch {
            toast.error(t('agents.sources.connectors.attach_failed'));
        }
    }

    async function detach(slug: string, onSuccess: () => void): Promise<void> {
        try {
            await detachConnector(toValue(agentId), slug);
            onSuccess();
        } catch {
            toast.error(t('agents.sources.connectors.detach_failed'));
        }
    }

    // "Always on": the connector's tools ship on every turn, bypassing the
    // relevance router.
    async function setAlwaysAvailable(slug: string, value: boolean, onSuccess: () => void): Promise<void> {
        try {
            await setConnectorAlwaysAvailable(toValue(agentId), slug, value);
            onSuccess();
        } catch {
            toast.error(t('agents.sources.connectors.always_failed'));
        }
    }

    // Toggle = attach/detach. Rows in the 'available' state are not connected
    // in the workspace yet, so their switch is disabled and nothing happens.
    // Detaching also clears "always on" (the server drops the pivot row); a
    // new attachment starts with it on.
    async function toggleFeatured(row: FeaturedConnectorRow): Promise<void> {
        if (row.state === 'available' || !row.server) return;

        if (row.state === 'attached') {
            await detach(row.server.slug, () => updateFeatured(row, { state: 'unattached', always_available: false }));
        } else {
            await attach(row.server.slug, () => updateFeatured(row, { state: 'attached', always_available: true }));
        }
    }

    async function toggleCustom(row: CustomConnectorRow): Promise<void> {
        if (row.is_attached) {
            await detach(row.slug, () => updateCustom(row, { is_attached: false, always_available: false }));
        } else {
            await attach(row.slug, () => updateCustom(row, { is_attached: true, always_available: true }));
        }
    }

    async function toggleFeaturedAlways(row: FeaturedConnectorRow): Promise<void> {
        if (row.state !== 'attached' || !row.server) return;
        const next = !row.always_available;
        await setAlwaysAvailable(row.server.slug, next, () => updateFeatured(row, { always_available: next }));
    }

    async function toggleCustomAlways(row: CustomConnectorRow): Promise<void> {
        if (!row.is_attached) return;
        const next = !row.always_available;
        await setAlwaysAvailable(row.slug, next, () => updateCustom(row, { always_available: next }));
    }

    onMounted(load);

    return {
        loading,
        search,
        filteredFeatured,
        filteredCustom,
        toggleFeatured,
        toggleCustom,
        toggleFeaturedAlways,
        toggleCustomAlways,
    };
}
