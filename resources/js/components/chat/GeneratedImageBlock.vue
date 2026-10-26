<script setup lang="ts">
/**
 * An image the agent made for the chatter. While its background job runs it
 * shows a placeholder; once made, the picture opens full size on click and
 * can be downloaded; if it could not be made, a short reason.
 */
import type { GeneratedMediaArtifactData } from '@/types/generatedMedia';
import { Download, ImageOff } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import ImageLightbox, { type LightboxImage } from './ImageLightbox.vue';

const props = defineProps<{
    media: GeneratedMediaArtifactData;
}>();

const { t } = useI18n();

const alt = computed(() =>
    props.media.prompt ? t('chat.generated_media.image_alt', { prompt: props.media.prompt }) : t('chat.generated_media.image'),
);
const images = computed<LightboxImage[]>(() => (props.media.url ? [{ src: props.media.url, alt: alt.value }] : []));
const lightboxIndex = ref<number | null>(null);

const failureText = computed(() => {
    if (props.media.failure === 'refused') return t('chat.generated_media.refused');
    if (props.media.failure === 'timed_out') return t('chat.generated_media.timed_out');
    return t('chat.generated_media.image_failed');
});
</script>

<template>
    <div class="generated-image-block not-prose my-3 max-w-md">
        <div v-if="media.status === 'ready' && media.url" class="group bg-card relative overflow-hidden rounded-md border">
            <button
                type="button"
                class="block w-full cursor-zoom-in"
                :aria-label="t('chat.image_viewer.view_named', { name: alt })"
                @click="lightboxIndex = 0"
            >
                <img :src="media.url" :alt="alt" class="block h-auto w-full" loading="lazy" />
            </button>
            <a
                :href="media.url"
                download
                class="bg-background/90 text-foreground absolute end-2 top-2 inline-flex items-center gap-1 rounded-md border px-2 py-1 text-xs opacity-0 shadow-sm transition-opacity group-hover:opacity-100 focus:opacity-100"
            >
                <Download class="h-3.5 w-3.5" aria-hidden="true" />
                {{ t('chat.generated_media.download') }}
            </a>
            <ImageLightbox v-model:index="lightboxIndex" :images="images" />
        </div>

        <div
            v-else-if="media.status === 'failed'"
            class="bg-card text-muted-foreground flex items-center gap-2 rounded-md border px-3 py-2 text-sm"
            role="status"
        >
            <ImageOff class="h-4 w-4 shrink-0" aria-hidden="true" />
            {{ failureText }}
        </div>

        <div
            v-else
            class="bg-muted text-muted-foreground flex aspect-square w-full animate-pulse items-center justify-center rounded-md border text-sm"
            role="status"
        >
            {{ t('chat.generated_media.image_pending') }}
        </div>
    </div>
</template>
