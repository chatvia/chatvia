<script setup lang="ts">
import { useBrand } from '@/composables/useBrand';
import type { HTMLAttributes } from 'vue';
defineOptions({
    inheritAttrs: false,
});

interface Props {
    className?: HTMLAttributes['class'];
}

defineProps<Props>();

const { brand } = useBrand();
// <!--        :class="['logo-adaptive', className]"-->
</script>

<template>
    <img src="/images/logo.svg" :alt="brand.name" :class="[className]" v-bind="$attrs" />
</template>

<style scoped>
.logo-adaptive {
    /* Light mode - dark logo */
    filter: brightness(0) saturate(100%) invert(0%) sepia(0%) saturate(0%) hue-rotate(0deg) brightness(0%) contrast(100%);
}

.dark .logo-adaptive {
    /* Dark mode - white logo */
    filter: brightness(0) saturate(100%) invert(100%) sepia(0%) saturate(0%) hue-rotate(0deg) brightness(100%) contrast(100%);
}
</style>
