<script setup lang="ts">
/**
 * Setup → Settings: agent-wide switches that aren't tied to one surface —
 * composer options (with voice conversations, reading replies aloud and the
 * agent's voices), reply
 * options (the sources under an answer), feedback options (the note after a
 * thumbs-down), the chat log, and which
 * conversation analytics run.
 * Each group is its own form with its own Save button.
 */
import SetupSaveFooter from '@/dashboard/agents/components/setup/SetupSaveFooter.vue';
import SetupSectionHeading from '@/dashboard/agents/components/setup/SetupSectionHeading.vue';
import SetupSwitchRow from '@/dashboard/agents/components/setup/SetupSwitchRow.vue';
import { Label } from '@/shared/ui/label';
import { RadioGroup, RadioGroupItem } from '@/shared/ui/radio-group';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/shared/ui/select';
import { Separator } from '@/shared/ui/separator';
import { useSetupContext } from '@/dashboard/agents/composables/setup/useSetupContext';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();
const { locked, generalSettings } = useSetupContext();
const {
    composerForm,
    isSavingComposer,
    saveComposer,
    voice,
    readAloud,
    fileUploadsAvailable,
    repliesForm,
    isSavingReplies,
    saveReplies,
    feedbackForm,
    isSavingFeedback,
    saveFeedback,
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

/** The name of the default read-aloud voice, by its id (ElevenLabs ids are not names). */
const readAloudDefaultName = computed(
    (): string => readAloud.choices.find((choice) => choice.id === readAloud.default)?.label ?? voiceName(readAloud.default),
);

// A voice the service no longer has is shown as the default, which is what plays.
const readAloudVoice = computed({
    get: (): string => (readAloud.choices.some((choice) => choice.id === composerForm.read_aloud_voice) ? composerForm.read_aloud_voice : 'default'),
    set: (value: string): void => {
        composerForm.read_aloud_voice = value;
    },
});

const help = computed(() => ({
    voiceConversation: {
        ariaLabel: t('agents.setup.settings.voice_conversation_aria'),
        title: t('agents.setup.settings.voice_conversation_help_title'),
        paragraphs: [t('agents.setup.settings.voice_conversation_help_1'), t('agents.setup.settings.voice_conversation_help_2')],
    },
    readAloud: {
        ariaLabel: t('agents.setup.settings.read_aloud_aria'),
        title: t('agents.setup.settings.read_aloud_help_title'),
        paragraphs: [t('agents.setup.settings.read_aloud_help_1'), t('agents.setup.settings.read_aloud_help_2')],
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

// The ways an answer can show its sources (SourcesDisplay), each with a label and hint key.
const SOURCES_DISPLAY: Domain.Agent.Enums.SourcesDisplay[] = ['hidden', 'list', 'citations'];
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
                            !voice.licensed
                                ? t('license.unavailable')
                                : voice.available
                                  ? t('agents.setup.settings.voice_conversation_hint')
                                  : t('agents.setup.settings.voice_conversation_unavailable')
                        "
                        :help="help.voiceConversation"
                        :disabled="locked || !voice.available || !voice.licensed"
                    />
                    <!-- The agent's voice, shown while voice conversations are on. -->
                    <div
                        v-if="voice.available && voice.licensed && composerForm.allow_voice_conversation"
                        class="flex items-center justify-between gap-3 px-4 pb-3"
                    >
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
                <div>
                    <SetupSwitchRow
                        id="allow-read-aloud"
                        v-model="composerForm.allow_read_aloud"
                        :label="t('agents.setup.settings.read_aloud_label')"
                        :hint="readAloud.available ? t('agents.setup.settings.read_aloud_hint') : t('agents.setup.settings.read_aloud_unavailable')"
                        :help="help.readAloud"
                        :disabled="locked || !readAloud.available"
                    />
                    <!-- The voice replies are read with, shown while reading aloud is on. -->
                    <div
                        v-if="readAloud.available && composerForm.allow_read_aloud && readAloud.choices.length > 0"
                        class="flex items-center justify-between gap-3 px-4 pb-3"
                    >
                        <Label for="agent-read-aloud-voice" class="text-muted-foreground text-sm">{{
                            t('agents.setup.settings.read_aloud_voice_label')
                        }}</Label>
                        <Select v-model="readAloudVoice" :disabled="locked">
                            <SelectTrigger id="agent-read-aloud-voice" class="w-48">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="default">{{
                                    t('agents.setup.settings.voice_default', { voice: readAloudDefaultName })
                                }}</SelectItem>
                                <SelectItem v-for="choice in readAloud.choices" :key="choice.id" :value="choice.id">{{ choice.label }}</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                </div>
            </div>
            <SetupSaveFooter submit :saving="isSavingComposer" />
        </form>

        <Separator />

        <!-- Sources — how an answer shows the knowledge it used. -->
        <form @submit.prevent="saveReplies" class="space-y-3">
            <SetupSectionHeading :title="t('agents.setup.settings.sources_title')" :description="t('agents.setup.settings.sources_description')" />
            <RadioGroup v-model="repliesForm.sources_display" :disabled="locked" class="divide-y rounded-md border">
                <label
                    v-for="option in SOURCES_DISPLAY"
                    :key="option"
                    :for="`sources-display-${option}`"
                    class="hover:bg-muted/40 flex cursor-pointer items-start gap-3 p-4 transition"
                >
                    <RadioGroupItem :id="`sources-display-${option}`" :value="option" class="mt-0.5" />
                    <span class="min-w-0 space-y-1">
                        <span class="block text-sm font-medium">{{ t(`agents.setup.settings.sources_${option}`) }}</span>
                        <span class="text-muted-foreground block text-sm">{{ t(`agents.setup.settings.sources_${option}_hint`) }}</span>
                    </span>
                </label>
            </RadioGroup>
            <SetupSaveFooter submit :saving="isSavingReplies" />
        </form>

        <Separator />

        <!-- Feedback — whether a thumbs-down opens the optional "What was wrong?" note. -->
        <form @submit.prevent="saveFeedback" class="space-y-3">
            <SetupSectionHeading :title="t('agents.setup.settings.feedback_title')" :description="t('agents.setup.settings.feedback_description')" />
            <div class="divide-y rounded-md border">
                <SetupSwitchRow
                    id="allow-feedback-comment"
                    v-model="feedbackForm.allow_feedback_comment"
                    :label="t('agents.setup.settings.feedback_comment_label')"
                    :hint="t('agents.setup.settings.feedback_comment_hint')"
                    :disabled="locked"
                />
            </div>
            <SetupSaveFooter submit :saving="isSavingFeedback" />
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
