/**
 * Setup shared by the two hosted chat pages (Home and Chat): applies the
 * owner's language and colour theme on mount, localises the appearance strings
 * to the active UI language, and exposes the per-chat upload ceiling and
 * whether the visitor can still send messages this month.
 */
import { resolveAppearanceText } from '@/chat/lib/appearanceText';
import { useColorTheme } from '@/shared/composables/useColorTheme';
import { normalizeLocale, resolveLocale, setI18nLocale } from '@/shared/i18n';
import type { ChatAppearance } from '@/shared/types/chat';
import type { PageProps } from '@inertiajs/core';
import { usePage } from '@inertiajs/vue3';
import { computed, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { useSendCapacity } from './useSendCapacity';

/** Matches the server default for `agent.files.upload.max_files`, used until the shared prop arrives. */
const DEFAULT_MAX_FILES_PER_CHAT = 2;

interface ChatSurfaceSource {
    appearance: ChatAppearance;
    /** The owner's pinned chat language; 'auto' follows the visitor. */
    locale: string;
}

export function useChatSurface(source: ChatSurfaceSource) {
    const { locale: i18nLocale } = useI18n();
    const page = usePage<PageProps & { maxFilesPerChat?: number; messageMaxLength?: number; defaultLocale?: string }>();
    const { applyColorTheme } = useColorTheme();
    const { canSendMessages } = useSendCapacity();

    // The header's language switcher changes the i18n locale; these strings
    // re-resolve instantly from the translation maps already in the payload.
    const resolvedAppearance = computed<ChatAppearance>(() => ({
        ...source.appearance,
        ...resolveAppearanceText(source.appearance, normalizeLocale(i18nLocale.value)),
    }));

    // Shared by ChatHandleInertiaRequests from `agent.files.upload.max_files`.
    const maxFilesPerChat = computed(() => page.props.maxFilesPerChat ?? DEFAULT_MAX_FILES_PER_CHAT);

    // Shared from `guardrails.max_message_chars`; the server refuses a longer message.
    const messageMaxLength = computed(() => page.props.messageMaxLength);

    onMounted(() => {
        // Honour the owner's pinned language, else the visitor's stored/browser
        // choice, else the install's default language.
        void setI18nLocale(resolveLocale(source.locale, page.props.defaultLocale));
        applyColorTheme(source.appearance);
    });

    return { resolvedAppearance, maxFilesPerChat, messageMaxLength, canSendMessages };
}
