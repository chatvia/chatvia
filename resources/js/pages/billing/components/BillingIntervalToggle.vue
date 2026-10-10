<script lang="ts" setup>
// Monthly / yearly switch above the plan cards, with the yearly saving badge.
import { Badge } from '@/components/ui/badge';
import { Label } from '@/components/ui/label';
import { useI18n } from 'vue-i18n';

defineProps<{
    /** The "save X%" badge on the yearly option, or null for no badge. */
    savingsText: string | null;
}>();

const isYearly = defineModel<boolean>({ required: true });

const { t } = useI18n();
</script>

<template>
    <div class="flex items-center justify-center">
        <div class="bg-muted flex items-center gap-3 rounded-lg p-1">
            <Label
                for="billing-toggle"
                class="cursor-pointer rounded-md px-3 py-2 text-sm font-medium transition-colors"
                :class="{ 'bg-background shadow-sm': !isYearly }"
                @click="
                    () => {
                        isYearly = false;
                    }
                "
            >
                {{ t('billing.plans.monthly') }}
            </Label>

            <Label
                for="billing-toggle"
                class="flex cursor-pointer items-center gap-2 rounded-md px-3 py-2 text-sm font-medium transition-colors"
                :class="{ 'bg-background shadow-sm': isYearly }"
                @click="
                    () => {
                        isYearly = true;
                    }
                "
            >
                {{ t('billing.plans.yearly') }}
                <Badge v-if="savingsText" variant="secondary" class="text-xs">{{ savingsText }}</Badge>
            </Label>
        </div>
    </div>
</template>
