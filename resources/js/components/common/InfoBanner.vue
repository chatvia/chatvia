<script setup lang="ts">
/**
 * Dismissible coloured notice. Once dismissed it stays hidden for this browser
 * (remembered under `storageKey`).
 */
import { safeStorage } from '@/lib/safeStorage';
import { ref, onMounted, computed } from 'vue';
import { X } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import { useI18n } from 'vue-i18n';

interface Props {
    title?: string;
    message?: string;
    storageKey?: string;
    variant?: 'info' | 'warning' | 'success';
}

const props = withDefaults(defineProps<Props>(), {
    message: '',
    storageKey: 'info-banner-dismissed',
    variant: 'info',
});

const { t } = useI18n();

const displayTitle = computed(() => props.title ?? t('shared.information'));

const isVisible = ref(true);

const dismissBanner = () => {
    isVisible.value = false;
    safeStorage.set(props.storageKey, 'true');
};

onMounted(() => {
    // Check if user has previously dismissed this banner
    const isDismissed = safeStorage.get(props.storageKey);
    if (isDismissed === 'true') {
        isVisible.value = false;
    }
});

// Dynamic styling based on variant
const getVariantStyles = () => {
    switch (props.variant) {
        case 'warning':
            return 'bg-yellow-50 dark:bg-yellow-950/20 border-yellow-200 dark:border-yellow-800 text-yellow-700 dark:text-yellow-300';
        case 'success':
            return 'bg-green-50 dark:bg-green-950/20 border-green-200 dark:border-green-800 text-green-700 dark:text-green-300';
        case 'info':
        default:
            return 'bg-blue-50 dark:bg-blue-950/20 border-blue-200 dark:border-blue-800 text-blue-700 dark:text-blue-300';
    }
};

const getDotColor = () => {
    switch (props.variant) {
        case 'warning':
            return 'bg-yellow-500';
        case 'success':
            return 'bg-green-500';
        case 'info':
        default:
            return 'bg-blue-500';
    }
};

const getButtonStyles = () => {
    switch (props.variant) {
        case 'warning':
            return 'text-yellow-600 hover:text-yellow-700 hover:bg-yellow-100 dark:text-yellow-400 dark:hover:text-yellow-300 dark:hover:bg-yellow-900/30';
        case 'success':
            return 'text-green-600 hover:text-green-700 hover:bg-green-100 dark:text-green-400 dark:hover:text-green-300 dark:hover:bg-green-900/30';
        case 'info':
        default:
            return 'text-blue-600 hover:text-blue-700 hover:bg-blue-100 dark:text-blue-400 dark:hover:text-blue-300 dark:hover:bg-blue-900/30';
    }
};
</script>

<template>
    <div v-if="isVisible" :class="`rounded-lg border p-4 ${getVariantStyles()}`">
        <div class="flex items-start justify-between gap-3">
            <div class="flex items-start gap-3">
                <div :class="`mt-2 h-2 w-2 flex-shrink-0 rounded-full ${getDotColor()}`"></div>
                <div class="text-sm">
                    <span class="font-medium">{{ displayTitle }}:</span> {{ message }}
                </div>
            </div>
            <Button variant="ghost" size="sm" :class="`h-6 w-6 p-0 ${getButtonStyles()}`" @click="dismissBanner">
                <X class="h-4 w-4" />
                <span class="sr-only">{{ t('shared.dismiss_banner') }}</span>
            </Button>
        </div>
    </div>
</template>
