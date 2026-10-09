/**
 * Copy-to-clipboard with one shared "just copied" marker for the Identity
 * page. Each copy button passes its own key, so only the button that copied
 * last shows its checkmark, for two seconds.
 */
import { useClipboard } from '@vueuse/core';
import { ref } from 'vue';

export function useCopiedKey() {
    const { copy: writeClipboard } = useClipboard({ legacy: true });

    const copiedKey = ref<string | null>(null);

    const copy = async (text: string, key: string): Promise<void> => {
        await writeClipboard(text);
        copiedKey.value = key;
        setTimeout(() => {
            if (copiedKey.value === key) copiedKey.value = null;
        }, 2000);
    };

    return { copiedKey, copy };
}
