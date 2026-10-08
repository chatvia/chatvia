<script setup>
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import ChatComposer from '@/components/chat/ChatComposer.vue';
import ChatLayout from '@/components/chat/ChatLayout.vue';
import PoweredByFooter from '@/components/chat/PoweredByFooter.vue';
import HeroIcon from '@/components/ui/HeroIcon.vue';
import { normalizeLocale, resolveLocale, setI18nLocale } from '@/i18n';
import { resolveAppearanceText, resolveStarters } from '@/lib/appearanceText';
import { useBrand } from '@/composables/useBrand';
import { useColorTheme } from '@/composables/useColorTheme';
import { Head, router, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';

const { t, locale: i18nLocale } = useI18n();
const { brand } = useBrand();

// Per-chat upload ceiling — sourced from `agent.files.upload.max_files`
// via Inertia shared props (ChatHandleInertiaRequests). Falls back to 2
// (the config default) for the brief window before the prop hydrates.
const page = usePage();
const maxFilesPerChat = computed(() => page.props.maxFilesPerChat ?? 2);

const props = defineProps({
    appearance: {
        type: Object,
        default: () => ({
            color_theme: 'black',
            welcome_message: null,
            chat_window_title: null,
            avatar: null,
            logo: null,
            composer_placeholder: null,
        }),
    },
    removeSidebar: {
        type: Boolean,
        default: false,
    },
    hideSidebarOnPageLoad: {
        type: Boolean,
        default: false,
    },
    enableThemeSwitcher: {
        type: Boolean,
        default: true,
    },
    defaultTheme: {
        type: String,
        default: 'auto',
        validator: (value) => ['auto', 'light', 'dark'].includes(value),
    },
    enableChatExpand: {
        type: Boolean,
        default: true,
    },
    chatRoute: {
        type: String,
        default: 'chat.show',
    },
    newChatRoute: {
        type: String,
        default: 'chat.home',
    },
    agent_id: {
        type: Number,
        default: null,
    },
    chatviaBranding: {
        type: Boolean,
        default: true,
    },
    // Owner's pinned chat language ('auto' = detect from the visitor).
    locale: {
        type: String,
        default: 'auto',
    },
    // Whether to show the visitor language switcher in the header.
    enableLanguageSwitcher: {
        type: Boolean,
        default: false,
    },
    // Language codes the owner enabled in Setup → Languages. Empty ⇒ all locales.
    enabledLanguages: {
        type: Array,
        default: () => [],
    },
});

// The three appearance strings localized to the active UI locale (the header
// language switcher changes it, re-resolving these from the payload's maps).
const resolvedAppearance = computed(() => ({
    ...props.appearance,
    ...resolveAppearanceText(props.appearance, normalizeLocale(i18nLocale.value)),
}));

// Starters localized to the active language (re-resolves when the visitor
// switches language); each item carries its own translations/overrides.
const resolvedStarters = computed(() => resolveStarters(starters.value, normalizeLocale(i18nLocale.value)));

onMounted(() => {
    void setI18nLocale(resolveLocale(props.locale));
});

const selectedStarter = ref('');

const starters = ref([]);

// Usage checking
const USAGE_CHECK_INTERVAL = 60000; // 1 minute
const canSendMessages = ref(true);
const usageCheckInterval = ref(null);

const { applyColorTheme } = useColorTheme();

// Function to check usage status
const checkUsage = async () => {
    try {
        const response = await axios.get(route('chat.usage'));
        canSendMessages.value = response.data.can_send_messages;
    } catch (error) {
        console.error('Failed to check usage:', error);
        // On error, allow sending messages to prevent blocking the user
        canSendMessages.value = true;
    }
};

// Start periodic usage checking
const startUsageChecking = () => {
    // Check immediately
    checkUsage();

    // Then check at regular intervals
    usageCheckInterval.value = setInterval(checkUsage, USAGE_CHECK_INTERVAL);
};

// Stop periodic usage checking
const stopUsageChecking = () => {
    if (usageCheckInterval.value) {
        clearInterval(usageCheckInterval.value);
        usageCheckInterval.value = null;
    }
};

onMounted(() => {
    // Start usage checking
    startUsageChecking();

    // Apply color theme
    applyColorTheme(props.appearance);

    // Fetch starters
    axios.get(route('chat.starters')).then((response) => {
        starters.value = response.data;
    });
});

onUnmounted(() => {
    // Stop usage checking
    stopUsageChecking();
});

async function createNewConversation(message, attachments = []) {
    await axios.post(route('conversations.create'), { message, attachments }).then((response) => {
        router.visit(route(props.chatRoute, { conversationId: response.data.id, new: '1' }));
    });
}

function handleStarterClick(text) {
    selectedStarter.value = text;
}
</script>

<template>
    <ChatLayout
        :appearance="resolvedAppearance"
        :removeSidebar="props.removeSidebar"
        :hideSidebarOnPageLoad="props.hideSidebarOnPageLoad"
        :enableThemeSwitcher="props.enableThemeSwitcher"
        :defaultTheme="props.defaultTheme"
        :enableChatExpand="props.enableChatExpand"
        :enableLanguageSwitcher="props.enableLanguageSwitcher"
        :enabledLanguages="props.enabledLanguages"
        :newChatRoute="props.newChatRoute"
    >
        <Head :title="brand.name" />
        <template #content>
            <div class="flex h-full items-center justify-center px-4 py-8">
                <div class="mx-auto -mt-8 w-full max-w-3xl">
                    <!-- Logo -->
                    <div class="mb-5 flex justify-center">
                        <div class="flex h-12 w-12 items-center justify-center overflow-hidden">
                            <img
                                v-if="props.appearance.logo"
                                :src="props.appearance.logo"
                                :alt="t('home.logo_alt')"
                                class="h-full w-full object-contain"
                            />
                            <AppLogoIcon v-else class="h-9 w-9" />
                        </div>
                    </div>

                    <!-- Welcome message -->
                    <h1 class="text-foreground mb-7 text-center text-2xl font-semibold tracking-tight text-balance sm:text-3xl">
                        {{ resolvedAppearance.welcome_message || t('home.welcome_message') }}
                    </h1>

                    <div class="mx-auto w-full max-w-2xl">
                        <!-- Show composer if user can send messages -->
                        <ChatComposer
                            v-if="canSendMessages"
                            :text="selectedStarter"
                            :placeholder="resolvedAppearance.composer_placeholder"
                            :allow-file-uploads="appearance.allow_file_uploads !== false"
                            :allow-voice-input="appearance.allow_voice_input !== false"
                            :max-files="maxFilesPerChat"
                            :upload-endpoint="props.agent_id ? route('chat.messages.attachments.store') : undefined"
                            :upload-headers="props.agent_id ? { 'X-AGENT-ID': String(props.agent_id), 'X-CONVERSATION-ID': 'pending' } : undefined"
                            :delete-endpoint="props.agent_id ? (id) => route('chat.messages.attachments.delete', { attachment: id }) : undefined"
                            @messageAdded="createNewConversation"
                        />

                        <!-- Show limit message if user cannot send messages -->
                        <div
                            v-else
                            class="mx-auto flex max-w-md items-center justify-center rounded-lg border border-orange-200 bg-orange-50 p-4 dark:border-orange-800 dark:bg-orange-900/20"
                        >
                            <div class="text-center">
                                <p class="text-sm font-medium text-orange-800 dark:text-orange-200">{{ t('chat.message_limit_reached') }}</p>
                                <p class="mt-1 text-xs text-orange-600 dark:text-orange-300">{{ t('chat.upgrade_prompt') }}</p>
                            </div>
                        </div>

                        <!-- Conversation Starters -->
                        <div v-if="canSendMessages && resolvedStarters.length > 0" class="mt-6 flex flex-wrap justify-center gap-2.5">
                            <button
                                v-for="(starter, index) in resolvedStarters"
                                :key="index"
                                @click="handleStarterClick(starter.text)"
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

                        <!-- Branding: hidden when the workspace owns the remove-branding add-on -->
                        <PoweredByFooter :show="props.chatviaBranding" />
                    </div>
                </div>
            </div>
        </template>
    </ChatLayout>
</template>
