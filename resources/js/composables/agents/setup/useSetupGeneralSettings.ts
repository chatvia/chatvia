/**
 * The Settings section: agent-wide switches that aren't tied to one surface.
 * Composer options (file uploads, voice input, voice conversations and the
 * agent's voice) and the reply options (the sources under an answer) live in
 * the `appearance` group;
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
    /** The voice agents speak with unless they pick their own. */
    default: string;
    choices: string[];
}

export function useSetupGeneralSettings(
    { agentId, reloadPreview }: SetupSectionDeps,
    appearance: SettingsReader,
    analytics: SettingsReader,
    voice: SetupVoiceOptions,
) {
    const { t } = useI18n();
    const settingsUrl = (group: 'appearance' | 'analytics') => updateSettings.url({ agent: agentId(), group });

    // Composer affordances — on by default; owners can hide the paperclip / mic.
    // Voice conversations start off: a call costs far more than a message.
    const composerForm = useForm({
        allow_file_uploads: appearance.boolean('allow_file_uploads', true),
        allow_voice_input: appearance.boolean('allow_voice_input', true),
        allow_voice_conversation: appearance.boolean('allow_voice_conversation', false),
        voice: appearance.string('voice', 'default'),
    });
    const isSavingComposer = ref(false);
    const saveComposer = (): void =>
        submitSetupForm(composerForm, 'post', settingsUrl('appearance'), {
            saving: isSavingComposer,
            success: t('agents.setup.toasts.composer_saved'),
            error: t('agents.setup.toasts.composer_error'),
            onSuccess: reloadPreview,
        });

    // Reply options. Sources start hidden: the button under small talk confuses chatters.
    const repliesForm = useForm({
        show_sources: appearance.boolean('show_sources', false),
    });
    const isSavingReplies = ref(false);
    const saveReplies = (): void =>
        submitSetupForm(repliesForm, 'post', settingsUrl('appearance'), {
            saving: isSavingReplies,
            success: t('agents.setup.toasts.replies_saved'),
            error: t('agents.setup.toasts.replies_error'),
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
        repliesForm,
        isSavingReplies,
        saveReplies,
        chatlogForm,
        isSavingChatlog,
        saveChatlog,
        analyticsForm,
        isSavingAnalytics,
        saveAnalytics,
    };
}

export type SetupGeneralSettings = ReturnType<typeof useSetupGeneralSettings>;
