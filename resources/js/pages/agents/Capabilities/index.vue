<script lang="ts" setup>
import { type AgentCapabilityRow, disableCapability, enableCapability } from '@/api/agentCapabilities';
import { errorMessage } from '@/api/http';
import { Switch } from '@/components/ui/switch';
import { useConnectorText } from '@/composables/useConnectorText';
import AgentLayout from '@/layouts/AgentLayout.vue';
import { connectorLogoUrl } from '@/lib/connectorLogo';
import { LoaderCircle } from 'lucide-vue-next';
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
                        </div>
                        <LoaderCircle v-if="busy[row.connector.slug]" class="text-muted-foreground h-4 w-4 animate-spin" />
                        <Switch
                            :model-value="row.enabled"
                            :disabled="busy[row.connector.slug]"
                            :aria-label="connectorName(row.connector)"
                            @update:model-value="toggle(row)"
                        />
                    </div>
                </div>
            </div>
        </div>
    </AgentLayout>
</template>
