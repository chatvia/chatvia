<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import { BotMessageSquare } from 'lucide-vue-next';
defineOptions({
    inheritAttrs: false,
});

interface Props {
    className?: HTMLAttributes['class'];
}

defineProps<Props>();
</script>

<template>
    <BotMessageSquare :class="className" v-bind="$attrs" />
</template>
