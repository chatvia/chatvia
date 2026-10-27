<script setup lang="ts">
/**
 * The hosted chat's empty "new chat" screen: logo, welcome line, composer and
 * conversation starters. Sending the first message creates the conversation
 * and moves to the chat page, which streams the reply. A voice call opens its
 * own conversation; once it ends, that conversation is named and opened.
 */
import { createConversation, fetchStarters, generateConversationTitle, type VoiceCallStarted } from '@/api/chat';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import ChatComposer from '@/components/chat/ChatComposer.vue';
import ChatLayout from '@/components/chat/ChatLayout.vue';
import PoweredByFooter from '@/components/chat/PoweredByFooter.vue';
import VoiceModeOverlay from '@/components/chat/voice/VoiceModeOverlay.vue';
import HeroIcon from '@/components/common/HeroIcon.vue';
import { chatPages } from '@/composables/chat/chatPages';
import { provideChatMediaModelChoice, type OfferedMediaModel } from '@/composables/chat/useChatMediaModelChoice';
import { provideChatModelChoice, type OfferedModel } from '@/composables/chat/useChatModelChoice';
import { useChatSurface } from '@/composables/chat/useChatSurface';
import { useAppName } from '@/composables/useAppName';
import { normalizeLocale } from '@/i18n';
import { resolveStarters } from '@/lib/appearanceText';
import { deleteMethod as deleteAttachment, store as storeAttachment } from '@/routes/chat/messages/attachments';
import type { ChatAppearance, ChatAttachment, ChatDefaultTheme, ConversationStarter } from '@/types/chat';
import { Head, router } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';

interface Props {
    appearance?: ChatAppearance;
    removeSidebar?: boolean;
    hideSidebarOnPageLoad?: boolean;
    enableThemeSwitcher?: boolean;
    defaultTheme?: ChatDefaultTheme;
    enableChatExpand?: boolean;
    /** On the iframe-embed pages, so the first message opens the embed conversation page. */
    embedded?: boolean;
    agent_id?: number | null;
    /** Owner's pinned chat language ('auto' = detect from the visitor). */
    locale?: string;
    /** Whether to show the visitor language switcher in the header. */
    enableLanguageSwitcher?: boolean;
    /** Language codes the owner enabled in Setup → Languages. Empty means all locales. */
    enabledLanguages?: string[];
    /** Whether the composer offers PDF uploads: the owner allows them and the server can read PDFs. */
    allowFileUploads?: boolean;
    /** The MIME types the file picker offers: what the server's model for chat files reads. */
    uploadAccept?: string;
    /** Whether the composer offers dictation: the owner allows it and the server has speech to text set up. */
    allowVoiceInput?: boolean;
    /** Whether the composer offers voice conversations: the owner turned them on and the server has them set up. */
    voiceConversation?: boolean;
    /** The models the chatter may pick from; empty hides the picker. */
    models?: OfferedModel[];
    /** The image and video models the chatter may pick from in the header; fewer than two hide the picker. */
    imageModels?: OfferedMediaModel[];
    videoModels?: OfferedMediaModel[];
}

const props = withDefaults(defineProps<Props>(), {
    appearance: () => ({
        color_theme: 'black',
        welcome_message: null,
        chat_window_title: null,
        avatar: null,
        logo: null,
        composer_placeholder: null,
        notice: null,
    }),
    removeSidebar: false,
    hideSidebarOnPageLoad: false,
    enableThemeSwitcher: true,
    defaultTheme: 'auto',
    enableChatExpand: true,
    embedded: false,
    agent_id: null,
    locale: 'auto',
    enableLanguageSwitcher: false,
    enabledLanguages: () => [],
    allowFileUploads: false,
    uploadAccept: 'application/pdf',
    allowVoiceInput: false,
    voiceConversation: false,
    models: () => [],
    imageModels: () => [],
    videoModels: () => [],
});

const { t, locale: i18nLocale } = useI18n();
const { resolvedAppearance, maxFilesPerChat, messageMaxLength, canSendMessages } = useChatSurface(props);
const appName = useAppName();

const starters = ref<ConversationStarter[]>([]);
const selectedStarter = ref('');

// Re-resolves when the visitor switches language; each starter carries its own translations.
const resolvedStarters = computed(() => resolveStarters(starters.value, normalizeLocale(i18nLocale.value)));

// Uploads need an agent to scope them to; without one the composer hides the upload button.
// The first reply streams on the conversation page, which reads the same remembered pick.
provideChatModelChoice(props.agent_id ?? 'none', () => props.models);
provideChatMediaModelChoice(props.agent_id ?? 'none', { image: () => props.imageModels, video: () => props.videoModels });

const uploadEndpoint = computed(() => (props.agent_id ? storeAttachment.url() : undefined));
const uploadHeaders = computed(() => (props.agent_id ? { 'X-AGENT-ID': String(props.agent_id), 'X-CONVERSATION-ID': 'pending' } : undefined));
const deleteEndpoint = computed(() => (props.agent_id ? (attachmentId: string) => deleteAttachment.url({ attachment: attachmentId }) : undefined));

onMounted(async () => {
    starters.value = await fetchStarters();
});

const isVoiceOpen = ref(false);
/** The conversation the voice call opened, once the server answered. */
let voiceConversationId: string | null = null;
/** Whether anything was said in the call (an empty call opens no thread). */
let voiceSpoke = false;

function onVoiceStarted(call: VoiceCallStarted) {
    voiceConversationId = call.conversation_id;
}

function onVoiceTurn() {
    voiceSpoke = true;
}

async function closeVoice() {
    isVoiceOpen.value = false;

    if (!voiceConversationId || !voiceSpoke) return;

    const id = voiceConversationId;
    voiceConversationId = null;
    // Named from what was said, as after a first typed reply.
    await generateConversationTitle(id).catch(() => null);
    router.visit(chatPages(props.embedded).show(id));
}

async function createNewConversation(message: string, attachments: ChatAttachment[] = []) {
    const { id } = await createConversation(message, attachments);
    router.visit(chatPages(props.embedded).show(id, { query: { new: '1' } }));
}
</script>

<template>
    <ChatLayout
        :appearance="resolvedAppearance"
        :removeSidebar="removeSidebar"
        :hideSidebarOnPageLoad="hideSidebarOnPageLoad"
        :enableThemeSwitcher="enableThemeSwitcher"
        :defaultTheme="defaultTheme"
        :enableChatExpand="enableChatExpand"
        :enableLanguageSwitcher="enableLanguageSwitcher"
        :enabledLanguages="enabledLanguages"
        :embedded="embedded"
    >
        <Head :title="appName" />
        <template #content>
            <div class="relative flex h-full items-center justify-center px-4 py-8">
                <div class="mx-auto -mt-8 w-full max-w-3xl">
                    <div class="mb-5 flex justify-center">
                        <div class="flex h-12 w-12 items-center justify-center overflow-hidden">
                            <img v-if="appearance.logo" :src="appearance.logo" :alt="t('home.logo_alt')" class="h-full w-full object-contain" />
                            <AppLogoIcon v-else class="h-9 w-9" />
                        </div>
                    </div>

                    <h1 class="text-foreground mb-7 text-center text-2xl font-semibold tracking-tight text-balance sm:text-3xl">
                        {{ resolvedAppearance.welcome_message || t('home.welcome_message') }}
                    </h1>

                    <div class="mx-auto w-full max-w-2xl">
                        <ChatComposer
                            v-if="canSendMessages"
                            :text="selectedStarter"
                            :placeholder="resolvedAppearance.composer_placeholder ?? undefined"
                            :allow-file-uploads="allowFileUploads"
                            :upload-accept="uploadAccept"
                            :allow-voice-input="allowVoiceInput"
                            :allow-voice-conversation="voiceConversation"
                            :max-files="maxFilesPerChat"
                            :max-length="messageMaxLength"
                            :upload-endpoint="uploadEndpoint"
                            :upload-headers="uploadHeaders"
                            :delete-endpoint="deleteEndpoint"
                            @messageAdded="createNewConversation"
                            @startVoice="isVoiceOpen = true"
                        />

                        <div
                            v-else
                            class="mx-auto flex max-w-md items-center justify-center rounded-lg border border-orange-200 bg-orange-50 p-4 dark:border-orange-800 dark:bg-orange-900/20"
                        >
                            <div class="text-center">
                                <p class="text-sm font-medium text-orange-800 dark:text-orange-200">{{ t('chat.message_limit_reached') }}</p>
                                <p class="mt-1 text-xs text-orange-600 dark:text-orange-300">{{ t('chat.limit_resets_next_month') }}</p>
                            </div>
                        </div>

                        <div v-if="canSendMessages && resolvedStarters.length > 0" class="mt-6 flex flex-wrap justify-center gap-2.5">
                            <button
                                v-for="(starter, index) in resolvedStarters"
                                :key="index"
                                @click="selectedStarter = starter.text"
                                class="group border-border bg-card hover:border-muted-foreground/30 hover:bg-muted/60 flex cursor-pointer items-center gap-2 rounded-2xl border px-4 py-2.5 text-start shadow-sm transition-all duration-150 hover:-translate-y-px hover:shadow-md"
                            >
                                <HeroIcon
                                    v-if="starter.icon"
                                    :name="starter.icon"
                                    class="text-muted-foreground group-hover:text-primary h-4 w-4 shrink-0 transition-colors"
                                />
                                <span class="text-foreground/80 group-hover:text-foreground line-clamp-2 max-w-[280px] text-sm leading-snug">
                                    {{ starter.text }}
                                </span>
                            </button>
                        </div>

                        <p
                            v-if="resolvedAppearance.notice"
                            dir="auto"
                            class="text-muted-foreground/70 mx-auto mt-2 max-w-2xl px-4 text-center text-[11px] leading-snug"
                        >
                            {{ resolvedAppearance.notice }}
                        </p>

                        <PoweredByFooter />
                    </div>
                </div>

                <VoiceModeOverlay v-if="isVoiceOpen" @started="onVoiceStarted" @turn="onVoiceTurn" @close="closeVoice" />
            </div>
        </template>
    </ChatLayout>
</template>
