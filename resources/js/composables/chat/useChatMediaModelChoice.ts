/**
 * The image and video model the chatter picked in the hosted chat's header,
 * from the ones the agent offers (`imageModels` / `videoModels` page props;
 * fewer than two: no picker). Each pick is remembered per agent in this
 * browser and sent with every message as `image_model` / `video_model`; one
 * the agent no longer offers falls back to the admin's model.
 *
 * The page creates it once and provides it, so the header's pickers and the
 * stream turn share the same picks.
 */
import { safeStorage } from '@/lib/safeStorage';
import { computed, inject, provide, ref, type ComputedRef, type InjectionKey, type WritableComputedRef } from 'vue';

export type OfferedMediaModel = Domain.Agent.Data.ModelChoice.OfferedMediaModelData;
export type MediaModelKind = 'image' | 'video';

export interface MediaModelPick {
    models: ComputedRef<OfferedMediaModel[]>;
    /** The picked model's id; null while the agent offers no choice. */
    modelId: WritableComputedRef<string | null>;
    picked: ComputedRef<OfferedMediaModel | null>;
}

export type ChatMediaModelChoice = Record<MediaModelKind, MediaModelPick>;

const KEY: InjectionKey<ChatMediaModelChoice> = Symbol('chatMediaModelChoice');

function pickFor(storageKey: string, offered: () => OfferedMediaModel[] | undefined): MediaModelPick {
    const models = computed(() => offered() ?? []);
    const fallback = (): string | null => models.value.find((model) => model.is_default)?.id ?? models.value[0]?.id ?? null;
    const stored = ref<string | null>(safeStorage.get(storageKey));

    // A remembered pick the agent no longer offers reads as the admin's model.
    const modelId = computed<string | null>({
        get: () => (models.value.some((model) => model.id === stored.value) ? stored.value : fallback()),
        set: (id) => {
            stored.value = id;
            if (id === null) safeStorage.remove(storageKey);
            else safeStorage.set(storageKey, id);
        },
    });

    return {
        models,
        modelId,
        picked: computed(() => models.value.find((model) => model.id === modelId.value) ?? null),
    };
}

export function provideChatMediaModelChoice(
    agentId: number | string,
    offered: { image: () => OfferedMediaModel[] | undefined; video: () => OfferedMediaModel[] | undefined },
): ChatMediaModelChoice {
    const choice: ChatMediaModelChoice = {
        image: pickFor(`chat.image-model.${agentId}`, offered.image),
        video: pickFor(`chat.video-model.${agentId}`, offered.video),
    };

    provide(KEY, choice);

    return choice;
}

/** The page's media model choice; null outside a page that offers one (the header then shows no pickers). */
export function useChatMediaModelChoice(): ChatMediaModelChoice | null {
    return inject(KEY, null);
}
