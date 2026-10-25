/**
 * The model the chatter picked for their messages in the hosted chat, from
 * the ones the agent offers (`models` page prop; empty: no picker). The pick
 * is remembered per agent in this browser and sent with every message as
 * `model_id`; one the agent no longer offers falls back to its own model.
 *
 * The page creates it once and provides it, so the composer's picker and the
 * regenerate menu under each answer share the same pick.
 */
import { safeStorage } from '@/lib/safeStorage';
import { computed, inject, provide, ref, type ComputedRef, type InjectionKey, type Ref } from 'vue';

export type OfferedModel = Domain.Agent.Data.ModelChoice.OfferedModelData;

export interface ChatModelChoice {
    models: ComputedRef<OfferedModel[]>;
    /** The picked model's id; null while the agent offers no choice. */
    modelId: Ref<number | null>;
    picked: ComputedRef<OfferedModel | null>;
    pick: (id: number) => void;
}

const KEY: InjectionKey<ChatModelChoice> = Symbol('chatModelChoice');

export function provideChatModelChoice(agentId: number | string, offered: () => OfferedModel[] | undefined): ChatModelChoice {
    const storageKey = `chat.model.${agentId}`;
    const models = computed(() => offered() ?? []);
    const fallback = (): number | null => models.value.find((model) => model.is_default)?.id ?? models.value[0]?.id ?? null;

    const remembered = Number(safeStorage.get(storageKey));
    const stored = ref<number | null>(Number.isInteger(remembered) && remembered > 0 ? remembered : null);

    // A remembered pick the agent no longer offers reads as its own model.
    const modelId = computed<number | null>({
        get: () => (models.value.some((model) => model.id === stored.value) ? stored.value : fallback()),
        set: (id) => {
            stored.value = id;
            if (id === null) safeStorage.remove(storageKey);
            else safeStorage.set(storageKey, String(id));
        },
    });

    const choice: ChatModelChoice = {
        models,
        modelId,
        picked: computed(() => models.value.find((model) => model.id === modelId.value) ?? null),
        pick: (id) => {
            modelId.value = id;
        },
    };

    provide(KEY, choice);

    return choice;
}

/** The page's model choice; null outside a page that offers one (the composer then shows no picker). */
export function useChatModelChoice(): ChatModelChoice | null {
    return inject(KEY, null);
}
