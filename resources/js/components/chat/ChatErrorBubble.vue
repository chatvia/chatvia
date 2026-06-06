<script setup lang="ts">
import { Button } from '@/components/ui/button';
import type { Message } from '@/types/chat';
import type { StreamErrorCode } from '@/types/stream';
import { AlertTriangle, CreditCard, RefreshCw, WifiOff } from 'lucide-vue-next';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps<{
    message: Message;
}>();

const emit = defineEmits<{
    retry: [];
}>();

const { t } = useI18n();

type Severity = 'block' | 'warn' | 'transient';

interface Treatment {
    severity: Severity;
    icon: typeof AlertTriangle;
    titleKey: string;
    cta?: { labelKey: string; href: string };
}

const TREATMENT: Record<StreamErrorCode, Treatment> = {
    subscription_expired: {
        severity: 'block',
        icon: CreditCard,
        titleKey: 'errors.subscription_expired',
        cta: { labelKey: 'errors.renew_subscription', href: '/my/billing' },
    },
    message_limit_reached: {
        severity: 'block',
        icon: CreditCard,
        titleKey: 'errors.message_limit_reached',
        cta: { labelKey: 'errors.upgrade_plan', href: '/my/billing' },
    },
    token_limit_reached: {
        severity: 'block',
        icon: CreditCard,
        titleKey: 'errors.token_limit_reached',
        cta: { labelKey: 'errors.upgrade_plan', href: '/my/billing' },
    },
    upstream_unavailable: {
        severity: 'transient',
        icon: WifiOff,
        titleKey: 'errors.model_unavailable',
    },
    rate_limited: {
        severity: 'transient',
        icon: WifiOff,
        titleKey: 'errors.too_many_requests',
    },
    network: {
        severity: 'transient',
        icon: WifiOff,
        titleKey: 'errors.connection_lost',
    },
    parse: {
        severity: 'warn',
        icon: AlertTriangle,
        titleKey: 'errors.unexpected_response',
    },
    internal: {
        severity: 'warn',
        icon: AlertTriangle,
        titleKey: 'errors.something_went_wrong',
    },
};

const treatment = computed<Treatment>(() => TREATMENT[props.message.errorCode ?? 'internal'] ?? TREATMENT.internal);

const containerClass = computed(() => {
    switch (treatment.value.severity) {
        case 'block':
            return 'border-destructive/30 bg-destructive/5';
        case 'warn':
            return 'border-amber-500/30 bg-amber-500/5';
        case 'transient':
        default:
            return 'border-border bg-muted/40';
    }
});

const iconClass = computed(() => {
    switch (treatment.value.severity) {
        case 'block':
            return 'text-destructive';
        case 'warn':
            return 'text-amber-600';
        case 'transient':
        default:
            return 'text-muted-foreground';
    }
});
</script>

<template>
    <div :class="['flex w-full items-start gap-3 rounded-xl border px-4 py-3', containerClass]" role="alert">
        <component :is="treatment.icon" :class="['mt-0.5 h-5 w-5 shrink-0', iconClass]" />
        <div class="min-w-0 flex-1">
            <p class="text-sm font-medium">{{ t(treatment.titleKey) }}</p>
            <p class="text-muted-foreground mt-0.5 text-sm leading-relaxed">{{ message.content }}</p>
            <div v-if="treatment.cta || message.errorRetryable" class="mt-3 flex flex-wrap items-center gap-2">
                <a v-if="treatment.cta" :href="treatment.cta.href" target="_top">
                    <Button size="sm" variant="default">{{ t(treatment.cta.labelKey) }}</Button>
                </a>
                <Button v-if="message.errorRetryable" size="sm" variant="outline" @click="emit('retry')">
                    <RefreshCw class="mr-1.5 h-3.5 w-3.5" />
                    {{ t('errors.try_again') }}
                </Button>
            </div>
        </div>
    </div>
</template>
