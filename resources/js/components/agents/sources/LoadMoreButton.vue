<script setup lang="ts">
/**
 * The "Load more" button under a source list the server sends one page at a
 * time. It shows a spinner while the next page loads.
 */
import { Button } from '@/components/ui/button';
import { LoaderCircle } from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';

defineProps<{
    isLoading: boolean;
}>();

const emit = defineEmits<{
    click: [];
}>();

const { t } = useI18n();
</script>

<template>
    <div class="flex justify-center pt-2">
        <Button variant="outline" size="sm" :disabled="isLoading" @click="emit('click')">
            <LoaderCircle v-if="isLoading" class="me-2 h-4 w-4 animate-spin" />
            {{ t('agents.sources.common.load_more') }}
        </Button>
    </div>
</template>
