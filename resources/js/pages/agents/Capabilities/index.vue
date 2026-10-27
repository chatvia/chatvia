<script lang="ts" setup>
// Without a license key every capability is off in chats and cannot be turned
// on: the page explains it and links to buy a key (turning one off still works).
import {
    type AgentCapabilityRow,
    chooseCapabilityProvider,
    disableCapability,
    enableCapability,
    setCapabilityModelChoice,
} from '@/api/agentCapabilities';
import { errorMessage } from '@/api/http';
import LicenseNotice from '@/components/license/LicenseNotice.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import { useConnectorText } from '@/composables/useConnectorText';
import { useLicense } from '@/composables/useLicense';
import { useLocaleDate } from '@/composables/useLocaleDate';
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
const { intlLocale } = useLocaleDate();
const { licensed } = useLicense();

// Local, reactive copy so toggles update in place.
const rows = ref<AgentCapabilityRow[]>(props.capabilities.map((row) => ({ ...row })));
// Per-slug in-flight guard — first enable provisions the MCP server (a remote
// tools/list), so the switch can take a moment.
const busy = reactive<Record<string, boolean>>({});

/** The connectors a capability works through, as "Calendly or Cal.com" in the UI language. */
function providerNames(row: AgentCapabilityRow): string {
    const names = row.providers.map((provider) => connectorName(provider.connector));
    try {
        return new Intl.ListFormat(intlLocale.value, { type: 'disjunction' }).format(names);
    } catch {
        return names.join(' / ');
    }
}

const connectedProviders = (row: AgentCapabilityRow) => row.providers.filter((provider) => provider.connected);

async function chooseProvider(row: AgentCapabilityRow, connectorSlug: string): Promise<void> {
    const slug = row.connector.slug;
    if (busy[slug] || connectorSlug === row.provider) return;
    busy[slug] = true;

    const previous = row.provider;
    row.provider = connectorSlug;
    try {
        await chooseCapabilityProvider(props.agent.external_id, slug, connectorSlug);
    } catch (error: unknown) {
        row.provider = previous;
        toast.error(errorMessage(error, t('agents.capabilities.provider_failed')));
    } finally {
        busy[slug] = false;
    }
}

/** Image and Video generation: let chatters pick the model in the hosted chat's header. */
async function toggleModelChoice(row: AgentCapabilityRow): Promise<void> {
    const slug = row.connector.slug;
    if (busy[slug]) return;
    busy[slug] = true;

    const turningOn = !row.model_choice_enabled;
    try {
        await setCapabilityModelChoice(props.agent.external_id, slug, turningOn);
        row.model_choice_enabled = turningOn;
    } catch (error: unknown) {
        toast.error(errorMessage(error, t('agents.capabilities.model_choice_failed')));
    } finally {
        busy[slug] = false;
    }
}

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
                <LicenseNotice v-if="!licensed" />

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
                            <p
                                v-if="!row.ready && row.providers.length > 0"
                                class="mt-1 flex items-center gap-1 text-xs text-amber-600 dark:text-amber-500"
                            >
                                <TriangleAlert class="h-3.5 w-3.5 shrink-0" />
                                {{
                                    row.enabled
                                        ? t('agents.capabilities.requires_disconnected', { names: providerNames(row) })
                                        : t('agents.capabilities.requires_connector', { names: providerNames(row) })
                                }}
                            </p>
                            <p
                                v-if="!row.connector.available && (licensed || !row.connector.needsLicense)"
                                class="text-muted-foreground mt-1 flex items-center gap-1 text-xs"
                            >
                                <Ban class="h-3.5 w-3.5 shrink-0" />
                                {{ t('agents.capabilities.turned_off_by_admin') }}
                            </p>
                            <div v-if="connectedProviders(row).length > 1" class="mt-2 flex items-center gap-2">
                                <span class="text-muted-foreground text-xs">{{ t('agents.capabilities.book_through') }}</span>
                                <Select
                                    :model-value="row.provider ?? undefined"
                                    :disabled="busy[row.connector.slug]"
                                    @update:model-value="(value) => chooseProvider(row, String(value))"
                                >
                                    <SelectTrigger class="h-8 w-40" :aria-label="t('agents.capabilities.book_through')">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem
                                            v-for="option in connectedProviders(row)"
                                            :key="option.connector.slug"
                                            :value="option.connector.slug"
                                        >
                                            {{ connectorName(option.connector) }}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                            <div v-if="row.enabled && (row.model_choice_allowed || row.model_choice_enabled)" class="mt-2 flex items-center gap-2">
                                <Switch
                                    :id="`model-choice-${row.connector.slug}`"
                                    :model-value="row.model_choice_enabled"
                                    :disabled="busy[row.connector.slug] || (!row.model_choice_allowed && !row.model_choice_enabled)"
                                    @update:model-value="toggleModelChoice(row)"
                                />
                                <Label :for="`model-choice-${row.connector.slug}`" class="text-muted-foreground text-xs font-normal">
                                    {{
                                        row.connector.slug === 'video-generation'
                                            ? t('agents.capabilities.model_choice_video')
                                            : t('agents.capabilities.model_choice_image')
                                    }}
                                </Label>
                            </div>
                        </div>
                        <LoaderCircle v-if="busy[row.connector.slug]" class="text-muted-foreground h-4 w-4 animate-spin" />
                        <Link v-if="!row.ready && !row.enabled && row.providers.length > 0" :href="editConnectors.url()">
                            <Button variant="outline" size="sm">{{
                                t('agents.capabilities.connect_connector', { names: providerNames(row) })
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
