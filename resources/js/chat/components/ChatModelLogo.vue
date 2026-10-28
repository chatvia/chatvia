<script setup lang="ts">
/**
 * A model maker's logo (models.dev, one-colour SVG) drawn as a mask the text
 * colour fills, so it reads in light and dark themes; a generic chip when
 * the model has none.
 */
import { Sparkles } from 'lucide-vue-next';

defineProps<{ logo: string | null }>();
</script>

<template>
    <span
        v-if="logo"
        aria-hidden="true"
        class="inline-block size-4 shrink-0 bg-current"
        :style="{ maskImage: `url(${logo})`, maskSize: 'contain', maskRepeat: 'no-repeat', maskPosition: 'center' }"
    />
    <Sparkles v-else aria-hidden="true" class="size-4 shrink-0" />
</template>
