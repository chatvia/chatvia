<script lang="ts" setup>
import AllowedSitesForm from '@/dashboard/agents/components/deploy/AllowedSitesForm.vue';
import CustomDomainForm from '@/dashboard/agents/components/deploy/CustomDomainForm.vue';
import DeployApiDocsDialog from '@/dashboard/agents/components/deploy/DeployApiDocsDialog.vue';
import DeployCardLinks from '@/dashboard/agents/components/deploy/DeployCardLinks.vue';
import DeploySnippet from '@/dashboard/agents/components/deploy/DeploySnippet.vue';
import DeploySurfaceCard from '@/dashboard/agents/components/deploy/DeploySurfaceCard.vue';
import HostedChatLink from '@/dashboard/agents/components/deploy/HostedChatLink.vue';
import IframeEmbedBuilder from '@/dashboard/agents/components/deploy/IframeEmbedBuilder.vue';
import { barSnippet, pickSetting, type SurfaceSettings, widgetSnippet } from '@/dashboard/agents/components/deploy/installSnippets';
import { Alert, AlertDescription, AlertTitle } from '@/shared/ui/alert';
import { useSnippetClipboard } from '@/dashboard/agents/composables/deploy/useSnippetClipboard';
import { useSurfaceToggles } from '@/dashboard/agents/composables/deploy/useSurfaceToggles';
import AgentLayout from '@/dashboard/agents/layouts/AgentLayout.vue';
import { identity as agentIdentity, setup as agentSetup } from '@/routes/agents';
import { Head, Link } from '@inertiajs/vue3';
import { Command, Globe, Lock, MessageCircle } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps<{
    agent: Domain.Agent.Data.Resources.Account.AgentResourceData;
    authToken: string | null;
    embedScriptUrl: string;
    widgetSettings: SurfaceSettings;
    defaultWidget: SurfaceSettings;
    barSettings: SurfaceSettings;
    defaultBar: SurfaceSettings;
    surfaces: SurfaceSettings;
    customDomain: Domain.Agent.Data.Resources.Account.AgentDomainResourceData | null;
    customDomainOptions: Domain.Agent.Data.Resources.Account.CustomDomainOptionsData;
}>();

const { t } = useI18n();

const { surfaceEnabled, savingSurfaces, toggleSurface } = useSurfaceToggles(() => props.agent.external_id, props.surfaces);
const { copiedKey, copySnippet } = useSnippetClipboard();

// Private agents embed with a server-minted token: the snippets carry a
// `data-auth` placeholder and the page nudges the owner to make it public.
const isProtected = computed(() => !props.agent.is_public);
const shareUrl = computed(() => props.agent.permanent_url || props.agent.url);

const widgetCode = computed(() =>
    widgetSnippet({
        scriptUrl: props.embedScriptUrl,
        agentId: props.agent.external_id,
        isProtected: isProtected.value,
        language: pickSetting(props.widgetSettings, props.defaultWidget, 'language', 'auto'),
        theme: pickSetting(props.widgetSettings, props.defaultWidget, 'theme', 'auto'),
    }),
);
const barCode = computed(() => barSnippet({ scriptUrl: props.embedScriptUrl, agentId: props.agent.external_id, isProtected: isProtected.value }));
// The tags are values, not message text: vue-i18n warns about HTML in messages.
const installHint = computed(() => t('agents.deploy.install_hint', { body: '</body>', head: '<head>' }));

const setupUrl = computed(() => agentSetup.url(props.agent.external_id));
const securityUrl = computed(() => agentIdentity.url(props.agent.external_id));

const showApiDocsDialog = ref(false);

// Example code for the customer's own site, shown in the JavaScript API
// dialog (displayed and copied as text, never run here).
const API_EVENTS_EXAMPLE = `// When chat window opens
window.chatvia.on('open', () => {
  console.log('Chat opened');
});

// When chat window closes
window.chatvia.on('close', () => {
  console.log('Chat closed');
});

// When widget is ready
window.chatvia.on('ready', () => {
  console.log('Widget initialized');
});

// Unsubscribe from events
const unsubscribe = window.chatvia.on('open', myCallback);
unsubscribe(); // Stop listening

// Or use window.chatvia.off()
window.chatvia.off('open', myCallback); // Remove specific callback
window.chatvia.off('open'); // Remove all callbacks for this event`;
</script>

<template>
    <AgentLayout :agent="props.agent" :title="t('agents.deploy.title')" :description="t('agents.deploy.subtitle')">
        <Head :title="t('agents.deploy.head_title', { name: props.agent.name })" />

        <div class="flex justify-center">
            <div class="w-full max-w-3xl space-y-6">
                <!-- Private agent: once for the page, since it affects every surface below -->
                <Alert v-if="isProtected">
                    <Lock class="h-4 w-4" />
                    <AlertTitle>{{ t('agents.deploy.private_title') }}</AlertTitle>
                    <AlertDescription>
                        <p>{{ t('agents.deploy.private_description') }}</p>
                        <Link :href="securityUrl" class="text-primary font-medium hover:underline">{{
                            t('agents.deploy.open_access_settings')
                        }}</Link>
                    </AlertDescription>
                </Alert>

                <DeploySurfaceCard
                    :icon="MessageCircle"
                    :title="t('agents.deploy.widget.title')"
                    :description="t('agents.deploy.widget.description')"
                    :enabled="surfaceEnabled.widget"
                    :saving="savingSurfaces"
                    @toggle="(value) => toggleSurface('widget', value)"
                >
                    <DeploySnippet
                        :code="widgetCode"
                        :label="t('agents.deploy.install_label')"
                        :hint="installHint"
                        :copied="copiedKey === 'widget'"
                        @copy="copySnippet(widgetCode, 'widget')"
                    />
                    <AllowedSitesForm :agent-id="agent.external_id" :widget-settings="widgetSettings" :default-widget="defaultWidget" />
                    <DeployCardLinks :setup-url="setupUrl" @open-api-docs="showApiDocsDialog = true" />
                </DeploySurfaceCard>

                <DeploySurfaceCard
                    :icon="Globe"
                    :title="t('agents.deploy.chat.title')"
                    :description="t('agents.deploy.chat.description')"
                    :enabled="surfaceEnabled.hosted_chat"
                    :saving="savingSurfaces"
                    body-class="space-y-5"
                    @toggle="(value) => toggleSurface('hosted_chat', value)"
                >
                    <HostedChatLink :url="shareUrl" :copied="copiedKey === 'url'" @copy="copySnippet(shareUrl, 'url')" />
                    <CustomDomainForm :agent-id="agent.external_id" :domain="customDomain" :options="customDomainOptions" />
                    <IframeEmbedBuilder
                        :src="agent.chat_embed_url"
                        :agent-name="agent.name"
                        :copied="copiedKey === 'iframe'"
                        @copy="(code) => copySnippet(code, 'iframe')"
                    />
                </DeploySurfaceCard>

                <DeploySurfaceCard
                    :icon="Command"
                    :title="t('agents.deploy.bar.title')"
                    :description="t('agents.deploy.bar.description')"
                    :enabled="surfaceEnabled.bar"
                    :saving="savingSurfaces"
                    @toggle="(value) => toggleSurface('bar', value)"
                >
                    <DeploySnippet
                        :code="barCode"
                        :label="t('agents.deploy.install_label')"
                        :hint="installHint"
                        :copied="copiedKey === 'bar'"
                        @copy="copySnippet(barCode, 'bar')"
                    />
                    <DeployCardLinks :setup-url="setupUrl" @open-api-docs="showApiDocsDialog = true" />
                </DeploySurfaceCard>
            </div>
        </div>

        <DeployApiDocsDialog v-model:open="showApiDocsDialog" :events-example="API_EVENTS_EXAMPLE" />
    </AgentLayout>
</template>
