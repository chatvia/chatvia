<script lang="ts" setup>
import { type AgentCapabilityRow, disableCapability, enableCapability } from '@/api/agentCapabilities';
import { errorMessage } from '@/api/http';
import { Button } from '@/components/ui/button';
import { Switch } from '@/components/ui/switch';
import { useConnectorText } from '@/composables/useConnectorText';
import AgentLayout from '@/layouts/AgentLayout.vue';
import { connectorLogoUrl } from '@/lib/connectorLogo';
import { edit as editConnectors } from '@/routes/integrations/connectors';
import { Link } from '@inertiajs/vue3';
import { Ban, LoaderCircle, TriangleAlert } from 'lucide-vue-next';
import { reactive, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { toast } from 'vue-sonner';

const props = defineProps<{
    agent: Domain.Agent.Data.Resources.Account.AgentResourceData;
    capabilities: AgentCapabilityRow[];
}>();

const { t } = useI18n();
const { connectorName, connectorDescription } = useConnectorText();

// Local, reactive copy so toggles update in place.
const rows = ref<AgentCapabilityRow[]>(props.capabilities.map((row) => ({ ...row })));
// Per-slug in-flight guard — first enable provisions the MCP server (a remote
// tools/list), so the switch can take a moment.
const busy = reactive<Record<string, boolean>>({});

async function toggle(row: AgentCapabilityRow): Promise<void> {
    const slug = row.connector.slug;
    if (busy[slug]) return;
    busy[slug] = true;

    const turningOn = !row.enabled;
    try {
        if (turningOn) {
            await enableCapability(props.agent.external_id, slug);
        } else {
            await disableCapability(props.agent.external_id, slug);
        }
        row.enabled = turningOn;
    } catch (error: unknown) {
        toast.error(errorMessage(error, turningOn ? t('agents.capabilities.enable_failed') : t('agents.capabilities.disable_failed')));
    } finally {
        busy[slug] = false;
    }
}
</script>

<template>
    <AgentLayout :agent="agent" :title="t('agents.capabilities.page_title')" :description="t('agents.capabilities.description')">
        <div class="flex justify-center">
            <div class="w-full max-w-4xl space-y-6">
                <div v-if="rows.length === 0" class="bg-card text-muted-foreground rounded-lg border py-12 text-center text-sm">
                    {{ t('agents.capabilities.empty') }}
                </div>

                <div v-else class="bg-card divide-y rounded-lg border">
                    <div v-for="row in rows" :key="row.connector.slug" class="flex items-center gap-4 p-4">
                        <div
                            class="flex h-10 w-10 shrink-0 items-center justify-center rounded-md"
                            :style="{ backgroundColor: '#' + row.connector.brandColor + '15' }"
                        >
                            <img :src="connectorLogoUrl(row.connector)" :alt="connectorName(row.connector)" class="h-5 w-5" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="leading-tight font-medium">{{ connectorName(row.connector) }}</p>
                            <p class="text-muted-foreground mt-1 text-xs">{{ connectorDescription(row.connector) }}</p>
                            <p v-if="!row.ready && row.requires" class="mt-1 flex items-center gap-1 text-xs text-amber-600 dark:text-amber-500">
                                <TriangleAlert class="h-3.5 w-3.5 shrink-0" />
                                {{
                                    row.enabled
                                        ? t('agents.capabilities.requires_disconnected', { name: connectorName(row.requires) })
                                        : t('agents.capabilities.requires_connector', { name: connectorName(row.requires) })
                                }}
                            </p>
                            <p v-if="!row.connector.available" class="text-muted-foreground mt-1 flex items-center gap-1 text-xs">
                                <Ban class="h-3.5 w-3.5 shrink-0" />
                                {{ t('agents.capabilities.turned_off_by_admin') }}
                            </p>
                        </div>
                        <LoaderCircle v-if="busy[row.connector.slug]" class="text-muted-foreground h-4 w-4 animate-spin" />
                        <Link v-if="!row.ready && !row.enabled && row.requires" :href="editConnectors.url()">
                            <Button variant="outline" size="sm">{{
                                t('agents.capabilities.connect_connector', { name: connectorName(row.requires) })
                            }}</Button>
                        </Link>
                        <Switch
                            v-else
                            :model-value="row.enabled"
                            :disabled="busy[row.connector.slug] || (!row.connector.available && !row.enabled)"
                            :aria-label="connectorName(row.connector)"
                            @update:model-value="toggle(row)"
                        />
                    </div>
                </div>
            </div>
        </div>
    </AgentLayout>
</template>
