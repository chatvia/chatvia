/**
 * The Settings section: agent-wide switches that aren't tied to one surface.
 * Composer options (file uploads, voice input, voice conversations and the
 * agent's voice, reading replies aloud and its voice), the reply options (the sources under an answer) and the
 * feedback options (the "What was wrong?" note after a thumbs-down) live in the `appearance` group;
 * the chat log and the analytics dimensions live in the `analytics` group.
 * The chat log is the analytics' master switch: with nothing stored there is
 * nothing to analyse, so the dimensions are forced off while it is off.
 */
import { update as updateSettings } from '@/routes/agents/settings';
import { useForm } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import type { SettingsReader } from './settingValues';
import { submitSetupForm, type SetupSectionDeps } from './submitSetupForm';

/** What this install offers for voice input and voice conversations (sent by the Setup page). */
export interface SetupVoiceOptions {
    /** Speech to text is set up; without it the voice-input switch is locked. */
    inputAvailable: boolean;
    /** Set up on this install (Settings → AI models); without it the switch is off and locked. */
    available: boolean;
    /** The install has a license key; without one voice conversations are locked (a free-use limit). */
    licensed: boolean;
    /** The voice agents speak with unless they pick their own. */
    default: string;
    choices: string[];
}

/** What this install offers for reading replies aloud (sent by the Setup page). */
export interface SetupReadAloudOptions {
    /** Text to speech is set up (Settings → AI models); without it the switch is off and locked. */
    available: boolean;
    /** The id of the voice agents read with unless they pick their own. */
    default: string;
    /** The voices of the service in use (ElevenLabs voices are the account's own). */
    choices: { id: string; label: string }[];
}

export function useSetupGeneralSettings(
    { agentId, reloadPreview }: SetupSectionDeps,
    appearance: SettingsReader,
    analytics: SettingsReader,
    voice: SetupVoiceOptions,
    readAloud: SetupReadAloudOptions,
    fileUploadsAvailable: boolean,
) {
    const { t } = useI18n();
    const settingsUrl = (group: 'appearance' | 'analytics') => updateSettings.url({ agent: agentId(), group });

    // Composer affordances — on by default; owners can hide the paperclip / mic.
    // Voice conversations and reading aloud start off: they cost far more than a message.
    const composerForm = useForm({
        allow_file_uploads: appearance.boolean('allow_file_uploads', true),
        allow_voice_input: appearance.boolean('allow_voice_input', true),
        allow_voice_conversation: appearance.boolean('allow_voice_conversation', false),
        voice: appearance.string('voice', 'default'),
        allow_read_aloud: appearance.boolean('allow_read_aloud', false),
        read_aloud_voice: appearance.string('read_aloud_voice', 'default'),
    });
    const isSavingComposer = ref(false);
    const saveComposer = (): void =>
        submitSetupForm(composerForm, 'post', settingsUrl('appearance'), {
            saving: isSavingComposer,
            success: t('agents.setup.toasts.composer_saved'),
            error: t('agents.setup.toasts.composer_error'),
            onSuccess: reloadPreview,
        });

    // How answers show their sources. Hidden by default: sources under small talk confuse chatters.
    const repliesForm = useForm({
        sources_display: appearance.string('sources_display') || 'hidden',
    });
    const isSavingReplies = ref(false);
    const saveReplies = (): void =>
        submitSetupForm(repliesForm, 'post', settingsUrl('appearance'), {
            saving: isSavingReplies,
            success: t('agents.setup.toasts.replies_saved'),
            error: t('agents.setup.toasts.replies_error'),
            onSuccess: reloadPreview,
        });

    // The optional "What was wrong?" note after a thumbs-down. On by default; off keeps the thumbs-down.
    const feedbackForm = useForm({
        allow_feedback_comment: appearance.boolean('allow_feedback_comment', true),
    });
    const isSavingFeedback = ref(false);
    const saveFeedback = (): void =>
        submitSetupForm(feedbackForm, 'post', settingsUrl('appearance'), {
            saving: isSavingFeedback,
            success: t('agents.setup.toasts.feedback_saved'),
            error: t('agents.setup.toasts.feedback_error'),
            onSuccess: reloadPreview,
        });

    // Chat log — off stops StoreMessageJob, so nothing is logged.
    const chatlogForm = useForm({
        chatlog: analytics.boolean('chatlog', true),
    });
    const isSavingChatlog = ref(false);

    // Analytics dimensions start off whenever the chat log is off, whatever their saved value.
    const analyticsForm = useForm({
        sentiment: chatlogForm.chatlog && analytics.boolean('sentiment', true),
        tone: chatlogForm.chatlog && analytics.boolean('tone', true),
        topics: chatlogForm.chatlog && analytics.boolean('topics', true),
    });
    const isSavingAnalytics = ref(false);

    const disableAllAnalytics = (): void => {
        analyticsForm.sentiment = false;
        analyticsForm.tone = false;
        analyticsForm.topics = false;
    };

    // Turning the chat log off drops the analytics switches right away.
    watch(
        () => chatlogForm.chatlog,
        (on) => {
            if (!on) {
                disableAllAnalytics();
            }
        },
    );

    const saveChatlog = (): void => {
        // With logging off the analytics can't run — store them off in the same write.
        if (!chatlogForm.chatlog) {
            disableAllAnalytics();
        }
        submitSetupForm(
            chatlogForm.transform((data) => (data.chatlog ? data : { chatlog: false, sentiment: false, tone: false, topics: false })),
            'post',
            settingsUrl('analytics'),
            {
                saving: isSavingChatlog,
                success: t('agents.setup.toasts.chatlog_saved'),
                error: t('agents.setup.toasts.chatlog_error'),
            },
        );
    };

    const saveAnalytics = (): void => {
        if (!chatlogForm.chatlog) {
            disableAllAnalytics();
        }
        submitSetupForm(analyticsForm, 'post', settingsUrl('analytics'), {
            saving: isSavingAnalytics,
            success: t('agents.setup.toasts.analytics_saved'),
            error: t('agents.setup.toasts.analytics_error'),
        });
    };

    return {
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
    };
}

export type SetupGeneralSettings = ReturnType<typeof useSetupGeneralSettings>;
