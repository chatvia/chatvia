<script setup lang="ts">
/**
 * Setup → Settings: agent-wide switches that aren't tied to one surface —
 * composer options (with voice conversations and the agent's voice), reply
 * options (the sources under an answer), the chat log, and which
 * conversation analytics run.
 * Each group is its own form with its own Save button.
 */
import SetupSaveFooter from '@/components/agents/setup/SetupSaveFooter.vue';
import SetupSectionHeading from '@/components/agents/setup/SetupSectionHeading.vue';
import SetupSwitchRow from '@/components/agents/setup/SetupSwitchRow.vue';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Separator } from '@/components/ui/separator';
import { useSetupContext } from '@/composables/agents/setup/useSetupContext';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();
const { locked, generalSettings } = useSetupContext();
const {
    composerForm,
    isSavingComposer,
    saveComposer,
    voice,
    fileUploadsAvailable,
    repliesForm,
    isSavingReplies,
    saveReplies,
    chatlogForm,
    isSavingChatlog,
    saveChatlog,
    analyticsForm,
    isSavingAnalytics,
    saveAnalytics,
} = generalSettings;

// While the install has no chat file uploads the switch shows off; the
// agent's own choice is kept for when an admin turns them on again.
const fileUploads = computed({
    get: (): boolean => fileUploadsAvailable && composerForm.allow_file_uploads,
    set: (value: boolean): void => {
        composerForm.allow_file_uploads = value;
    },
});

/** A voice's name as shown: "Marin". */
const voiceName = (voice: string): string => voice.charAt(0).toUpperCase() + voice.slice(1);

const help = computed(() => ({
    voiceConversation: {
        ariaLabel: t('agents.setup.settings.voice_conversation_aria'),
        title: t('agents.setup.settings.voice_conversation_help_title'),
        paragraphs: [t('agents.setup.settings.voice_conversation_help_1'), t('agents.setup.settings.voice_conversation_help_2')],
    },
    fileUploads: {
        ariaLabel: t('agents.setup.settings.file_uploads_aria'),
        title: t('agents.setup.settings.file_uploads_help_title'),
        paragraphs: [t('agents.setup.settings.file_uploads_help_1'), t('agents.setup.settings.file_uploads_help_2')],
    },
    storeConversations: {
        ariaLabel: t('agents.setup.settings.store_conversations_aria'),
        title: t('agents.setup.settings.store_conversations_help_title'),
        paragraphs: [t('agents.setup.settings.store_conversations_help_1'), t('agents.setup.settings.store_conversations_help_2')],
    },
    sentiment: {
        ariaLabel: t('agents.setup.settings.sentiment_aria'),
        title: t('agents.setup.settings.sentiment_help_title'),
        paragraphs: [t('agents.setup.settings.sentiment_help_1'), t('agents.setup.settings.sentiment_help_2')],
    },
    tone: {
        ariaLabel: t('agents.setup.settings.tone_aria'),
        title: t('agents.setup.settings.tone_help_title'),
        paragraphs: [t('agents.setup.settings.tone_help_1'), t('agents.setup.settings.tone_help_2')],
    },
    topics: {
        ariaLabel: t('agents.setup.settings.topics_aria'),
        title: t('agents.setup.settings.topics_help_title'),
        paragraphs: [t('agents.setup.settings.topics_help_1'), t('agents.setup.settings.topics_help_2')],
    },
}));
</script>

<template>
    <div class="max-w-3xl space-y-8 p-6">
        <!-- Composer options — show/hide the paperclip + mic buttons in the chat composer. -->
        <form @submit.prevent="saveComposer" class="space-y-3">
            <SetupSectionHeading :title="t('agents.setup.settings.composer_title')" :description="t('agents.setup.settings.composer_description')" />
            <div class="divide-y rounded-md border">
                <SetupSwitchRow
                    id="allow-file-uploads"
                    v-model="fileUploads"
                    :label="t('agents.setup.settings.file_uploads_label')"
                    :hint="fileUploadsAvailable ? t('agents.setup.settings.file_uploads_hint') : t('agents.setup.settings.file_uploads_unavailable')"
                    :help="help.fileUploads"
                    :disabled="locked || !fileUploadsAvailable"
                />
                <SetupSwitchRow
                    id="allow-voice-input"
                    v-model="composerForm.allow_voice_input"
                    :label="t('agents.setup.settings.voice_input_label')"
                    :hint="voice.inputAvailable ? t('agents.setup.settings.voice_input_hint') : t('agents.setup.settings.voice_input_unavailable')"
                    :disabled="locked || !voice.inputAvailable"
                />
                <div>
                    <SetupSwitchRow
                        id="allow-voice-conversation"
                        v-model="composerForm.allow_voice_conversation"
                        :label="t('agents.setup.settings.voice_conversation_label')"
                        :hint="
                            voice.available
                                ? t('agents.setup.settings.voice_conversation_hint')
                                : t('agents.setup.settings.voice_conversation_unavailable')
                        "
                        :help="help.voiceConversation"
                        :disabled="locked || !voice.available"
                    />
                    <!-- The agent's voice, shown while voice conversations are on. -->
                    <div v-if="voice.available && composerForm.allow_voice_conversation" class="flex items-center justify-between gap-3 px-4 pb-3">
                        <Label for="agent-voice" class="text-muted-foreground text-sm">{{ t('agents.setup.settings.voice_label') }}</Label>
                        <Select v-model="composerForm.voice" :disabled="locked">
                            <SelectTrigger id="agent-voice" class="w-48">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="default">{{
                                    t('agents.setup.settings.voice_default', { voice: voiceName(voice.default) })
                                }}</SelectItem>
                                <SelectItem v-for="choice in voice.choices" :key="choice" :value="choice">{{ voiceName(choice) }}</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                </div>
            </div>
            <SetupSaveFooter submit :saving="isSavingComposer" />
        </form>

        <Separator />

        <!-- Reply options — what chatters see under an answer. -->
        <form @submit.prevent="saveReplies" class="space-y-3">
            <SetupSectionHeading :title="t('agents.setup.settings.replies_title')" :description="t('agents.setup.settings.replies_description')" />
            <div class="divide-y rounded-md border">
                <SetupSwitchRow
                    id="show-sources"
                    v-model="repliesForm.show_sources"
                    :label="t('agents.setup.settings.show_sources_label')"
                    :hint="t('agents.setup.settings.show_sources_hint')"
                    :disabled="locked"
                />
            </div>
            <SetupSaveFooter submit :saving="isSavingReplies" />
        </form>

        <Separator />

        <!-- Chat log — master switch for storing conversations. -->
        <form @submit.prevent="saveChatlog" class="space-y-3">
            <SetupSectionHeading :title="t('agents.setup.settings.chatlog_title')" :description="t('agents.setup.settings.chatlog_description')" />
            <div class="divide-y rounded-md border">
                <SetupSwitchRow
                    id="chatlog-enabled"
                    v-model="chatlogForm.chatlog"
                    :label="t('agents.setup.settings.store_conversations_label')"
                    :hint="t('agents.setup.settings.store_conversations_hint')"
                    :help="help.storeConversations"
                    :disabled="locked"
                />
            </div>
            <SetupSaveFooter submit :saving="isSavingChatlog" />
        </form>

        <Separator />

        <!-- Analytics — the optional LLM-powered conversation insights. Need the chat log. -->
        <form @submit.prevent="saveAnalytics" class="space-y-3">
            <SetupSectionHeading
                :title="t('agents.setup.settings.analytics_title')"
                :description="t('agents.setup.settings.analytics_description')"
            />
            <div class="divide-y rounded-md border" :class="{ 'opacity-60': !chatlogForm.chatlog }">
                <SetupSwitchRow
                    id="analytics-sentiment"
                    v-model="analyticsForm.sentiment"
                    :label="t('agents.setup.settings.sentiment_label')"
                    :hint="t('agents.setup.settings.sentiment_hint')"
                    :help="help.sentiment"
                    :disabled="locked || !chatlogForm.chatlog"
                />
                <SetupSwitchRow
                    id="analytics-tone"
                    v-model="analyticsForm.tone"
                    :label="t('agents.setup.settings.tone_label')"
                    :hint="t('agents.setup.settings.tone_hint')"
                    :help="help.tone"
                    :disabled="locked || !chatlogForm.chatlog"
                />
                <SetupSwitchRow
                    id="analytics-topics"
                    v-model="analyticsForm.topics"
                    :label="t('agents.setup.settings.topics_label')"
                    :hint="t('agents.setup.settings.topics_hint')"
                    :help="help.topics"
                    :disabled="locked || !chatlogForm.chatlog"
                />
            </div>
            <SetupSaveFooter submit :saving="isSavingAnalytics" :disabled="!chatlogForm.chatlog" />
        </form>
    </div>
</template>
