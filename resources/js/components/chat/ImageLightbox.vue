<script setup lang="ts">
/**
 * Full-screen gallery for the images in one assistant answer. Opens on the
 * image that was clicked; arrows, swipes and thumbnails move between the
 * answer's images, Escape or a click on the backdrop closes it.
 */
import { useSwipe } from '@vueuse/core';
import { ChevronLeft, ChevronRight, ExternalLink, X } from 'lucide-vue-next';
import { DialogClose, DialogContent, DialogDescription, DialogOverlay, DialogPortal, DialogRoot, DialogTitle } from 'reka-ui';
import { computed, nextTick, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';

export interface LightboxImage {
    src: string;
    alt: string;
}

const props = withDefaults(
    defineProps<{
        images: LightboxImage[];
        isRTL?: boolean;
    }>(),
    { isRTL: false },
);

/** The shown image's position, or null while closed. */
const index = defineModel<number | null>('index', { required: true });

const { t } = useI18n();

const open = computed({
    get: () => index.value !== null && props.images.length > 0,
    set: (value) => {
        if (!value) index.value = null;
    },
});

const current = computed(() => (index.value === null ? null : (props.images[index.value] ?? null)));
const hasMany = computed(() => props.images.length > 1);

function step(by: number) {
    if (index.value === null || !hasMany.value) return;
    const count = props.images.length;
    index.value = (index.value + by + count) % count;
}

// Left and right follow the reading direction: in a right-to-left answer the
// left arrow goes forward.
const forward = computed(() => (props.isRTL ? -1 : 1));

function onKeydown(event: KeyboardEvent) {
    if (event.key === 'ArrowRight') step(forward.value);
    else if (event.key === 'ArrowLeft') step(-forward.value);
    else if (event.key === 'Home' && index.value !== null) index.value = 0;
    else if (event.key === 'End' && index.value !== null) index.value = props.images.length - 1;
    else return;
    event.preventDefault();
}

const stage = ref<HTMLElement | null>(null);
const { direction } = useSwipe(stage, {
    threshold: 40,
    onSwipeEnd: () => {
        if (direction.value === 'left') step(forward.value);
        else if (direction.value === 'right') step(-forward.value);
    },
});

// Keep the shown image's thumbnail in view as the strip scrolls.
const thumbnails = ref<HTMLElement | null>(null);
watch(index, () => nextTick(() => thumbnails.value?.querySelector('[aria-current="true"]')?.scrollIntoView({ block: 'nearest', inline: 'center' })));
</script>

<template>
    <DialogRoot v-model:open="open">
        <DialogPortal>
            <DialogOverlay
                class="data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0 fixed inset-0 z-50 bg-black/90 backdrop-blur-sm"
            />
            <DialogContent
                class="data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0 data-[state=closed]:zoom-out-95 data-[state=open]:zoom-in-95 fixed inset-0 z-50 flex flex-col text-white duration-200 outline-none"
                :dir="isRTL ? 'rtl' : 'ltr'"
                @keydown="onKeydown"
            >
                <DialogTitle class="sr-only">{{ t('chat.image_viewer.title') }}</DialogTitle>
                <DialogDescription class="sr-only">{{ current?.alt || t('chat.image_viewer.title') }}</DialogDescription>

                <!-- Top bar: position and actions. -->
                <div class="flex items-center justify-between gap-2 px-4 py-3">
                    <span v-if="hasMany" class="text-sm text-white/70 tabular-nums" aria-live="polite">
                        {{ t('chat.image_viewer.counter', { current: (index ?? 0) + 1, total: images.length }) }}
                    </span>
                    <span v-else />
                    <div class="flex items-center gap-1">
                        <a
                            v-if="current"
                            :href="current.src"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="inline-flex size-10 items-center justify-center rounded-full text-white/80 transition hover:bg-white/10 hover:text-white focus-visible:ring-2 focus-visible:ring-white/60 focus-visible:outline-none"
                            :title="t('chat.image_viewer.open_original')"
                            :aria-label="t('chat.image_viewer.open_original')"
                        >
                            <ExternalLink class="size-5" />
                        </a>
                        <DialogClose
                            class="inline-flex size-10 items-center justify-center rounded-full text-white/80 transition hover:bg-white/10 hover:text-white focus-visible:ring-2 focus-visible:ring-white/60 focus-visible:outline-none"
                            :title="t('shared.accessibility.close')"
                            :aria-label="t('shared.accessibility.close')"
                        >
                            <X class="size-5" />
                        </DialogClose>
                    </div>
                </div>

                <!-- Stage: a click on the empty space around the image closes it. -->
                <div ref="stage" class="relative flex min-h-0 flex-1 items-center justify-center px-4 sm:px-16" @click.self="open = false">
                    <Transition
                        mode="out-in"
                        enter-active-class="transition duration-200 ease-out"
                        enter-from-class="opacity-0 scale-[0.98]"
                        leave-active-class="transition duration-150 ease-in"
                        leave-to-class="opacity-0"
                    >
                        <img
                            v-if="current"
                            :key="current.src"
                            :src="current.src"
                            :alt="current.alt"
                            class="max-h-full max-w-full rounded-lg bg-white object-contain shadow-2xl select-none"
                            draggable="false"
                        />
                    </Transition>

                    <template v-if="hasMany">
                        <button
                            type="button"
                            class="absolute start-2 top-1/2 inline-flex size-11 -translate-y-1/2 items-center justify-center rounded-full bg-white/10 text-white transition hover:bg-white/20 focus-visible:ring-2 focus-visible:ring-white/60 focus-visible:outline-none sm:start-4"
                            :aria-label="t('chat.image_viewer.previous')"
                            @click="step(-1)"
                        >
                            <ChevronLeft class="size-6 rtl:rotate-180" />
                        </button>
                        <button
                            type="button"
                            class="absolute end-2 top-1/2 inline-flex size-11 -translate-y-1/2 items-center justify-center rounded-full bg-white/10 text-white transition hover:bg-white/20 focus-visible:ring-2 focus-visible:ring-white/60 focus-visible:outline-none sm:end-4"
                            :aria-label="t('chat.image_viewer.next')"
                            @click="step(1)"
                        >
                            <ChevronRight class="size-6 rtl:rotate-180" />
                        </button>
                    </template>
                </div>

                <!-- Caption and thumbnails. -->
                <div class="flex flex-col items-center gap-3 px-4 pt-3 pb-4">
                    <p v-if="current?.alt" class="max-w-3xl text-center text-sm text-white/80">{{ current.alt }}</p>
                    <div v-if="hasMany" ref="thumbnails" class="flex max-w-full gap-2 overflow-x-auto p-1">
                        <button
                            v-for="(image, i) in images"
                            :key="image.src"
                            type="button"
                            class="h-14 w-20 shrink-0 overflow-hidden rounded-md bg-white ring-2 transition focus-visible:outline-none"
                            :class="
                                i === index ? 'opacity-100 ring-white' : 'opacity-50 ring-transparent hover:opacity-80 focus-visible:ring-white/60'
                            "
                            :aria-current="i === index ? 'true' : undefined"
                            :aria-label="t('chat.image_viewer.counter', { current: i + 1, total: images.length })"
                            @click="index = i"
                        >
                            <img :src="image.src" alt="" class="size-full object-contain" draggable="false" />
                        </button>
                    </div>
                </div>
            </DialogContent>
        </DialogPortal>
    </DialogRoot>
</template>
