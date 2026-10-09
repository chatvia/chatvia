/**
 * Copy-to-clipboard for the Deploy page's snippets and links, with a toast for
 * the result. Remembers which snippet was copied last, so only that button
 * shows "Copied!" for two seconds.
 */
import { useClipboard } from '@vueuse/core';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { toast } from 'vue-sonner';

export function useSnippetClipboard() {
    const { t } = useI18n();
    const { copy, copied } = useClipboard({ copiedDuring: 2000 });

    const lastKey = ref<string | null>(null);
    /** The key passed to the last successful copy, while its "Copied!" state lasts. */
    const copiedKey = computed(() => (copied.value ? lastKey.value : null));

    async function copySnippet(text: string, key: string | null = null): Promise<void> {
        // `copied` stays false when the browser offers no clipboard at all.
        const succeeded = await copy(text).then(
            () => copied.value,
            () => false,
        );
        if (!succeeded) {
            toast.error(t('agents.deploy.copy_failed'));
            return;
        }

        lastKey.value = key;
        toast.success(t('agents.deploy.copied'));
    }

    return { copiedKey, copySnippet };
}
