<script lang="ts" setup>
import { connectorLogoUrl } from '@/lib/connectorLogo';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Switch } from '@/components/ui/switch';
import { useConnectorText } from '@/composables/useConnectorText';
import AgentLayout from '@/layouts/AgentLayout.vue';
import type { Connector } from '@/types/connector';
import axios from 'axios';
import { LoaderCircle, Sparkles } from 'lucide-vue-next';
import { reactive, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { toast } from 'vue-sonner';

interface CapabilityRow {
    connector: Connector;
    enabled: boolean;
}

interface Props {
    agent: { external_id: string; name: string };
    capabilities: CapabilityRow[];
}

const props = defineProps<Props>();

const { t } = useI18n();
const { connectorName, connectorDescription } = useConnectorText();

// Local, reactive copy so toggles update in place.
const rows = ref<CapabilityRow[]>(props.capabilities.map((r) => ({ ...r })));
// Per-slug in-flight guard — first enable provisions the MCP server (a remote
// tools/list), so the switch can take a moment.
const busy = reactive<Record<string, boolean>>({});

function logoUrl(c: Connector): string {
    return connectorLogoUrl(c);
}

async function toggle(row: CapabilityRow): Promise<void> {
    if (busy[row.connector.slug]) return;
    busy[row.connector.slug] = true;

    const turningOn = !row.enabled;
    try {
        if (turningOn) {
            await axios.post(route('agents.capabilities.enable', { agent: props.agent.external_id, slug: row.connector.slug }));
        } else {
            await axios.delete(route('agents.capabilities.disable', { agent: props.agent.external_id, slug: row.connector.slug }));
        }
        row.enabled = turningOn;
    } catch (err: unknown) {
        const message =
            (axios.isAxiosError(err) && (err.response?.data as { message?: string })?.message) ||
            t(turningOn ? 'agents.capabilities.enable_failed' : 'agents.capabilities.disable_failed');
        toast.error(message);
    } finally {
        busy[row.connector.slug] = false;
    }
}
</script>

<template>
    <AgentLayout :agent="agent" :title="t('agents.capabilities.page_title')">
        <div class="flex justify-center">
            <div class="w-full max-w-4xl space-y-6">
                <Card>
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2">
                            <Sparkles class="h-5 w-5" />
                            {{ t('agents.capabilities.title') }}
                        </CardTitle>
                        <CardDescription>{{ t('agents.capabilities.description') }}</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div v-if="rows.length === 0" class="text-muted-foreground rounded-md border py-12 text-center text-sm">
                            {{ t('agents.capabilities.empty') }}
                        </div>

                        <div v-else class="divide-y rounded-lg border">
                            <div v-for="row in rows" :key="row.connector.slug" class="flex items-center gap-4 p-4">
                                <div
                                    class="flex h-10 w-10 shrink-0 items-center justify-center rounded-md"
                                    :style="{ backgroundColor: '#' + row.connector.brandColor + '15' }"
                                >
                                    <img :src="logoUrl(row.connector)" :alt="connectorName(row.connector)" class="h-5 w-5" />
                                </div>
                                <div class="min-w-0 flex-1">
                                    <p class="leading-tight font-medium">{{ connectorName(row.connector) }}</p>
                                    <p class="text-muted-foreground mt-1 text-xs">{{ connectorDescription(row.connector) }}</p>
                                </div>
                                <LoaderCircle v-if="busy[row.connector.slug]" class="text-muted-foreground h-4 w-4 animate-spin" />
                                <Switch :model-value="row.enabled" :disabled="busy[row.connector.slug]" @update:model-value="toggle(row)" />
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>
    </AgentLayout>
</template>
