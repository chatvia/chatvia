<script setup lang="ts">
import { CheckCircle } from 'lucide-vue-next';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

interface Props {
    show: boolean;
    message?: string;
    icon?: boolean;
    class?: string;
}

const props = withDefaults(defineProps<Props>(), {
    icon: true,
    class: '',
});

const { t } = useI18n();

const displayMessage = computed(() => props.message ?? t('shared.saved_successfully'));
const classes = computed(() => `flex items-center text-sm text-green-600 ${props.class}`);
</script>

<template>
    <Transition
        enter-active-class="transition ease-in-out duration-300"
        enter-from-class="opacity-0 transform translate-x-4"
        enter-to-class="opacity-100 transform translate-x-0"
        leave-active-class="transition ease-in-out duration-300"
        leave-from-class="opacity-100 transform translate-x-0"
        leave-to-class="opacity-0 transform translate-x-4"
    >
        <div v-show="show" :class="classes" class="mx-2">
            <CheckCircle v-if="icon" class="mx-1 size-4" />
            <span>{{ displayMessage }}</span>
        </div>
    </Transition>
</template>
