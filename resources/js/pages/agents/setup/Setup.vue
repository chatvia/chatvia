<script lang="ts" setup>
/**
 * One Setup page for every surface. A surface tab bar (Hosted Chat / Widget /
 * Command Bar) switches the live preview and the surface-only sections; the
 * left menu picks a section; the right pane shows the live preview.
 *
 * Every section's form state lives in a composable created here once and
 * shared with the panels through the setup context, so unsaved edits survive
 * switching sections and surfaces even though hidden panels unmount.
 */
import AgentPersonaEditor from '@/components/agents/AgentPersonaEditor.vue';
import SetupBarBehaviorPanel from '@/components/agents/setup/SetupBarBehaviorPanel.vue';
import SetupBrandingPanel from '@/components/agents/setup/SetupBrandingPanel.vue';
import SetupBubblesPanel from '@/components/agents/setup/SetupBubblesPanel.vue';
import SetupDomainPanel from '@/components/agents/setup/SetupDomainPanel.vue';
import SetupHostedBehaviorPanel from '@/components/agents/setup/SetupHostedBehaviorPanel.vue';
import SetupInfoPanel from '@/components/agents/setup/SetupInfoPanel.vue';
import SetupLanguagesPanel from '@/components/agents/setup/SetupLanguagesPanel.vue';
import SetupModelPanel from '@/components/agents/setup/SetupModelPanel.vue';
import SetupPreviewPane from '@/components/agents/setup/SetupPreviewPane.vue';
import SetupSectionMenu from '@/components/agents/setup/SetupSectionMenu.vue';
import SetupSettingsPanel from '@/components/agents/setup/SetupSettingsPanel.vue';
import SetupStartersPanel from '@/components/agents/setup/SetupStartersPanel.vue';
import SetupSurfaceTabs from '@/components/agents/setup/SetupSurfaceTabs.vue';
import SetupWidgetBehaviorPanel from '@/components/agents/setup/SetupWidgetBehaviorPanel.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { ResizableHandle, ResizablePanel, ResizablePanelGroup } from '@/components/ui/resizable';
import { settingsReader } from '@/composables/agents/setup/settingValues';
import type { SetupSectionDeps } from '@/composables/agents/setup/submitSetupForm';
import { useSetupBehavior } from '@/composables/agents/setup/useSetupBehavior';
import { useSetupBranding } from '@/composables/agents/setup/useSetupBranding';
import { useSetupBubbles } from '@/composables/agents/setup/useSetupBubbles';
import { provideSetupContext } from '@/composables/agents/setup/useSetupContext';
import { useSetupDomain } from '@/composables/agents/setup/useSetupDomain';
import { useSetupGeneralSettings } from '@/composables/agents/setup/useSetupGeneralSettings';
import { useSetupLanguages } from '@/composables/agents/setup/useSetupLanguages';
import { useSetupModel } from '@/composables/agents/setup/useSetupModel';
import { useSetupPreview } from '@/composables/agents/setup/useSetupPreview';
import { useSetupSections } from '@/composables/agents/setup/useSetupSections';
import { useSetupStarters } from '@/composables/agents/setup/useSetupStarters';
import { useSetupText } from '@/composables/agents/setup/useSetupText';
import AgentLayout from '@/layouts/AgentLayout.vue';
import { Shield } from 'lucide-vue-next';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

type Llm = Domain.Llm.Data.LlmModelData;

interface PromptOption {
    id: string;
    name: string;
    prompt: string;
}

interface Props {
    agent: Domain.Agent.Data.Resources.Account.AgentResourceData & { id?: number | string };
    authToken: string | null;
    starters: { id?: string; text: string; icon: string | null; translations?: Record<string, string>; overrides?: Record<string, string> }[];
    bubbles: { text: string; translations?: Record<string, string>; overrides?: Record<string, string> }[];
    hasKnowledgeBase: boolean;
    prompts: PromptOption[];
    hasPermission: boolean;

    appearanceSettings: Record<string, unknown>;
    defaultAppearance: Record<string, unknown>;
    widgetSettings: Record<string, unknown>;
    defaultWidget: Record<string, unknown>;
    barSettings: Record<string, unknown>;
    defaultBar: Record<string, unknown>;
    embedSettings: Record<string, unknown>;
    defaultEmbed: Record<string, unknown>;

    analyticsSettings: Record<string, unknown>;
    defaultAnalytics: Record<string, unknown>;

    catalog: Llm[];
    selectedModel: string | null;
    llmSettings: { temperature: number; top_k: number; top_p: number; presence_penalty: number; frequency_penalty: number };
    defaultLlmSettings: { temperature: number; top_k: number; top_p: number; presence_penalty: number; frequency_penalty: number };

    availableLanguages: Record<string, string>;

    domain: {
        value: string | null;
        cname_target: string;
    };
}

const props = defineProps<Props>();
const { t } = useI18n();

const locked = computed(() => !props.hasPermission);

const preview = useSetupPreview(
    () => props.agent,
    () => props.authToken,
);
const { surface, isResizing } = preview;
const { active: activeSection, surfaceTabs, sections } = useSetupSections(surface);

// Settings groups as first sent; each section seeds its form from them.
const appearance = settingsReader(props.appearanceSettings, props.defaultAppearance);
const analytics = settingsReader(props.analyticsSettings, props.defaultAnalytics);

const deps: SetupSectionDeps = { agentId: () => props.agent.external_id, reloadPreview: preview.reload };

provideSetupContext({
    locked,
    preview,
    text: useSetupText(deps, appearance),
    generalSettings: useSetupGeneralSettings(deps, appearance, analytics),
    languages: useSetupLanguages(deps, appearance, () => props.availableLanguages),
    starters: useSetupStarters(deps, props.starters),
    bubbles: useSetupBubbles(deps, props.bubbles),
    model: useSetupModel(deps, props.llmSettings.temperature),
    branding: useSetupBranding(deps, appearance, () => props.appearanceSettings),
    behavior: useSetupBehavior(
        deps,
        settingsReader(props.embedSettings, props.defaultEmbed),
        settingsReader(props.widgetSettings, props.defaultWidget),
        settingsReader(props.barSettings, props.defaultBar),
    ),
    domain: useSetupDomain(deps, () => props.domain),
});
</script>

<template>
    <AgentLayout :agent="agent" :title="t('agents.setup.title')" hide-header no-padding full-height>
        <div class="bg-background flex h-full flex-col">
            <SetupSurfaceTabs v-model="surface" :tabs="surfaceTabs" />

            <!-- Layout: (section menu + section panel) | drag-resizable | live preview -->
            <ResizablePanelGroup direction="horizontal" auto-save-id="agent-setup-preview" class="min-w-0 flex-1">
                <ResizablePanel :default-size="62" :min-size="35">
                    <div class="flex h-full min-h-0">
                        <SetupSectionMenu v-model="activeSection" :items="sections" />

                        <div class="flex min-w-0 flex-1 flex-col overflow-hidden">
                            <Alert v-if="locked" variant="destructive" class="rounded-none border-x-0 border-t-0">
                                <Shield class="h-4 w-4" />
                                <AlertTitle>{{ t('agents.setup.view_only_title') }}</AlertTitle>
                                <AlertDescription>{{ t('agents.setup.view_only_description') }}</AlertDescription>
                            </Alert>

                            <div class="flex-1 overflow-y-auto" :class="{ 'pointer-events-none opacity-60 select-none': locked }">
                                <SetupInfoPanel v-if="activeSection === 'info'" />
                                <SetupSettingsPanel v-if="activeSection === 'settings'" />

                                <!-- Persona saves itself via agents.update. -->
                                <div v-if="activeSection === 'persona'" class="space-y-6 p-6">
                                    <AgentPersonaEditor
                                        :agent-id="agent.external_id"
                                        :initial-instructions="agent.system_instructions"
                                        :initial-prompt-id="agent.prompt_id ?? null"
                                        :locked="locked"
                                        @saved="preview.reload"
                                    />
                                </div>

                                <SetupLanguagesPanel v-if="activeSection === 'languages'" :available-languages="availableLanguages" />
                                <SetupStartersPanel
                                    v-if="activeSection === 'starters'"
                                    :agent-id="agent.external_id"
                                    :has-knowledge-base="hasKnowledgeBase"
                                />
                                <SetupBubblesPanel v-if="activeSection === 'bubbles'" />
                                <SetupModelPanel v-if="activeSection === 'model'" />
                                <SetupBrandingPanel v-if="activeSection === 'branding'" />

                                <!-- Behavior swaps its fields with the surface tab. -->
                                <template v-if="activeSection === 'behavior'">
                                    <SetupHostedBehaviorPanel v-if="surface === 'hosted_chat'" :available-languages="availableLanguages" />
                                    <SetupWidgetBehaviorPanel v-else-if="surface === 'widget'" :available-languages="availableLanguages" />
                                    <SetupBarBehaviorPanel v-else />
                                </template>

                                <SetupDomainPanel v-if="activeSection === 'domain'" :domain="domain" />
                            </div>
                        </div>
                    </div>
                </ResizablePanel>

                <ResizableHandle with-handle @dragging="isResizing = $event" />

                <ResizablePanel :default-size="38" :min-size="25">
                    <SetupPreviewPane />
                </ResizablePanel>
            </ResizablePanelGroup>
        </div>
    </AgentLayout>
</template>
