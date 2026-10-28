<script setup lang="ts">
import { Button } from '@/shared/ui/button';
import type { Message } from '@/shared/types/chat';
import type { StreamErrorCode } from '@/shared/types/stream';
import { AlertTriangle, Ban, MessageSquareOff, RefreshCw, WifiOff } from 'lucide-vue-next';
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
}

const TREATMENT: Record<StreamErrorCode, Treatment> = {
    // The agent's workspace or the chatter used up this month's messages; the
    // server's message says which.
    message_limit_reached: {
        severity: 'block',
        icon: MessageSquareOff,
        titleKey: 'errors.message_limit_reached',
    },
    chatter_message_limit_reached: {
        severity: 'block',
        icon: MessageSquareOff,
        titleKey: 'errors.message_limit_reached',
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
    // A workspace admin blocked this visitor; trying again cannot help.
    access_blocked: {
        severity: 'block',
        icon: Ban,
        titleKey: 'errors.access_blocked',
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
            <div v-if="message.errorRetryable" class="mt-3 flex flex-wrap items-center gap-2">
                <Button v-if="message.errorRetryable" size="sm" variant="outline" @click="emit('retry')">
                    <RefreshCw class="me-1.5 h-3.5 w-3.5" />
                    {{ t('errors.try_again') }}
                </Button>
            </div>
        </div>
    </div>
</template>
