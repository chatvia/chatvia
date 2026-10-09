<script lang="ts" setup>
// Banner for a site-wide discount on the plans page, with its end date and
// time when it has one.
import { useDateFormat } from '@/composables/useDateFormat';
import { Sparkles } from 'lucide-vue-next';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps<{
    percentage: number;
    endsAt: string | null;
}>();

const { t } = useI18n();
const { formatDateTime } = useDateFormat();

const formatDiscountEndDate = computed(() => (props.endsAt ? formatDateTime(new Date(props.endsAt)) : ''));
</script>

<template>
    <div class="rounded-lg bg-gradient-to-r from-green-500 to-emerald-600 p-4 text-center text-white">
        <div class="mb-2 flex items-center justify-center gap-2">
            <Sparkles class="h-5 w-5" />
            <span class="text-lg font-bold">{{ t('billing.plans.discount.banner', { percent: percentage }) }}</span>
            <Sparkles class="h-5 w-5" />
        </div>
        <p class="text-green-100">
            <template v-if="endsAt">{{ t('billing.plans.discount.ends', { date: formatDiscountEndDate }) }}</template>
            <template v-else>{{ t('billing.plans.discount.limited') }}</template>
        </p>
    </div>
</template>
