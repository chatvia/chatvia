<script setup lang="ts">
// Placeholder for a feature the current plan does not include: a lock, the
// given explanation and, when billing is on, an "Upgrade now" link.
import { Button } from '@/components/ui/button';
import { useBilling } from '@/composables/useBilling';
import { Link } from '@inertiajs/vue3';
import { Lock, Zap } from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';

interface Props {
    description: string;
}

defineProps<Props>();

const { t } = useI18n();
// No plans to upgrade to when the install runs without billing.
const { billingEnabled } = useBilling();
</script>

<template>
    <div class="flex min-h-[calc(100vh-20rem)] items-center justify-center">
        <div class="w-full max-w-md space-y-8 text-center">
            <!-- Lock Icon -->
            <div class="from-primary/10 to-primary/5 mx-auto flex h-20 w-20 items-center justify-center rounded-full bg-gradient-to-br">
                <Lock class="text-primary h-10 w-10" />
            </div>

            <!-- Description -->
            <div class="space-y-3">
                <p class="text-muted-foreground text-lg">
                    {{ description }}
                </p>
            </div>

            <!-- Upgrade Button -->
            <Button v-if="billingEnabled" as-child size="lg" class="gap-2 shadow-lg transition-all duration-300 hover:shadow-xl">
                <Link :href="route('billing.packages')">
                    <Zap class="h-5 w-5" />
                    {{ t('shared.upgrade_now') }}
                </Link>
            </Button>
        </div>
    </div>
</template>
