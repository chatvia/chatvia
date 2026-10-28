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
import AgentPersonaEditor from '@/dashboard/agents/components/AgentPersonaEditor.vue';
import SetupBarBehaviorPanel from '@/dashboard/agents/components/setup/SetupBarBehaviorPanel.vue';
import SetupBrandingPanel from '@/dashboard/agents/components/setup/SetupBrandingPanel.vue';
import SetupBubblesPanel from '@/dashboard/agents/components/setup/SetupBubblesPanel.vue';
import SetupHostedBehaviorPanel from '@/dashboard/agents/components/setup/SetupHostedBehaviorPanel.vue';
import SetupInfoPanel from '@/dashboard/agents/components/setup/SetupInfoPanel.vue';
import SetupLanguagesPanel from '@/dashboard/agents/components/setup/SetupLanguagesPanel.vue';
import SetupModelPanel from '@/dashboard/agents/components/setup/SetupModelPanel.vue';
import SetupPreviewPane from '@/dashboard/agents/components/setup/SetupPreviewPane.vue';
import SetupSectionMenu from '@/dashboard/agents/components/setup/SetupSectionMenu.vue';
import SetupSettingsPanel from '@/dashboard/agents/components/setup/SetupSettingsPanel.vue';
import SetupStartersPanel from '@/dashboard/agents/components/setup/SetupStartersPanel.vue';
import SetupSurfaceTabs from '@/dashboard/agents/components/setup/SetupSurfaceTabs.vue';
import SetupWidgetBehaviorPanel from '@/dashboard/agents/components/setup/SetupWidgetBehaviorPanel.vue';
import { Alert, AlertDescription, AlertTitle } from '@/shared/ui/alert';
import { ResizableHandle, ResizablePanel, ResizablePanelGroup } from '@/shared/ui/resizable';
import { settingsReader } from '@/dashboard/agents/composables/setup/settingValues';
import type { SetupSectionDeps } from '@/dashboard/agents/composables/setup/submitSetupForm';
import { useSetupBehavior } from '@/dashboard/agents/composables/setup/useSetupBehavior';
import { useSetupBranding } from '@/dashboard/agents/composables/setup/useSetupBranding';
import { useSetupBubbles } from '@/dashboard/agents/composables/setup/useSetupBubbles';
import { provideSetupContext } from '@/dashboard/agents/composables/setup/useSetupContext';
import {
    useSetupGeneralSettings,
    type SetupReadAloudOptions,
    type SetupVoiceOptions,
} from '@/dashboard/agents/composables/setup/useSetupGeneralSettings';
import { useSetupLanguages } from '@/dashboard/agents/composables/setup/useSetupLanguages';
import { useSetupModel, type ModelChoice } from '@/dashboard/agents/composables/setup/useSetupModel';
import { useSetupPreview } from '@/dashboard/agents/composables/setup/useSetupPreview';
import { useSetupSections } from '@/dashboard/agents/composables/setup/useSetupSections';
import { useSetupStarters } from '@/dashboard/agents/composables/setup/useSetupStarters';
import { useSetupText } from '@/dashboard/agents/composables/setup/useSetupText';
import AgentLayout from '@/dashboard/agents/layouts/AgentLayout.vue';
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

    voice: SetupVoiceOptions;
    readAloud: SetupReadAloudOptions;
    /** Chat file uploads are on for this install and a model reads them; without it the switch is off and locked. */
    fileUploadsAvailable: boolean;

    catalog: Llm[];
    selectedModel: number | null;
    defaultModel: number | null;
    llmSettings: { temperature: number; top_k: number; top_p: number; presence_penalty: number; frequency_penalty: number };
    defaultLlmSettings: { temperature: number; top_k: number; top_p: number; presence_penalty: number; frequency_penalty: number };
    /** How hard the agent reasons (off, low, medium, high), and whether the admin lets agents reason at all. */
    reasoningEffort: string | null;
    reasoningAllowed: boolean;
    /** Which other models chatters may pick from, and whether the admin lets agents offer a choice at all. */
    modelChoice: ModelChoice;
    modelChoiceAllowed: boolean;

    availableLanguages: Record<string, string>;
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
    generalSettings: useSetupGeneralSettings(deps, appearance, analytics, props.voice, props.readAloud, props.fileUploadsAvailable),
    languages: useSetupLanguages(deps, appearance, () => props.availableLanguages),
    starters: useSetupStarters(deps, props.starters),
    bubbles: useSetupBubbles(deps, props.bubbles),
    model: useSetupModel(deps, props.selectedModel, props.llmSettings, props.reasoningEffort, props.modelChoice),
    branding: useSetupBranding(deps, appearance, () => props.appearanceSettings),
    behavior: useSetupBehavior(
        deps,
        settingsReader(props.embedSettings, props.defaultEmbed),
        settingsReader(props.widgetSettings, props.defaultWidget),
        settingsReader(props.barSettings, props.defaultBar),
    ),
});
</script>

<template>
    <AgentLayout :agent="agent" :title="t('agents.setup.title')" hide-header no-padding full-height>
        <div class="bg-background flex h-full flex-col">
            <SetupSurfaceTabs v-model="surface" :tabs="surfaceTabs" data-tour="agent-setup-surfaces" />

            <!-- Layout: (section menu + section panel) | drag-resizable | live preview -->
            <ResizablePanelGroup direction="horizontal" auto-save-id="agent-setup-preview" class="min-w-0 flex-1">
                <ResizablePanel :default-size="62" :min-size="35">
                    <div class="flex h-full min-h-0">
                        <SetupSectionMenu v-model="activeSection" :items="sections" data-tour="agent-setup-sections" />

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
                                <SetupModelPanel
                                    v-if="activeSection === 'model'"
                                    :catalog="catalog"
                                    :default-model="defaultModel"
                                    :reasoning-allowed="reasoningAllowed"
                                    :model-choice-allowed="modelChoiceAllowed"
                                />
                                <SetupBrandingPanel v-if="activeSection === 'branding'" />

                                <!-- Behavior swaps its fields with the surface tab. -->
                                <template v-if="activeSection === 'behavior'">
                                    <SetupHostedBehaviorPanel v-if="surface === 'hosted_chat'" :available-languages="availableLanguages" />
                                    <SetupWidgetBehaviorPanel v-else-if="surface === 'widget'" :available-languages="availableLanguages" />
                                    <SetupBarBehaviorPanel v-else />
                                </template>
                            </div>
                        </div>
                    </div>
                </ResizablePanel>

                <ResizableHandle with-handle @dragging="isResizing = $event" />

                <ResizablePanel :default-size="38" :min-size="25">
                    <SetupPreviewPane data-tour="agent-setup-preview" />
                </ResizablePanel>
            </ResizablePanelGroup>
        </div>
    </AgentLayout>
</template>
