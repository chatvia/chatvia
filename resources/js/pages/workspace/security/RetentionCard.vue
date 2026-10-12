<script setup lang="ts">
// Data retention card: how many days conversations, leads, audit entries and
// analytics are kept, and when visitor details are cleared from chats. An
// empty field means "use the default" shown as the placeholder (for leads,
// analytics and anonymize: keep).
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { History } from 'lucide-vue-next';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import type { RetentionLimits } from './useSecurityPolicyForm';

const props = defineProps<{
    canManage: boolean;
    limits: RetentionLimits;
    errors: {
        retention_conversations_days?: string;
        retention_leads_days?: string;
        retention_audit_days?: string;
        retention_analytics_days?: string;
        anonymize_after_days?: string;
    };
}>();

const conversationsDays = defineModel<string>('conversationsDays', { required: true });
const leadsDays = defineModel<string>('leadsDays', { required: true });
const auditDays = defineModel<string>('auditDays', { required: true });
const analyticsDays = defineModel<string>('analyticsDays', { required: true });
const anonymizeDays = defineModel<string>('anonymizeDays', { required: true });

// Visitor details cannot outlive the chats: anonymizing is capped by the
// conversation retention typed above, else the install's.
const anonymizeMax = computed(() => {
    const conversations = Number(conversationsDays.value);

    return conversationsDays.value !== '' && conversations > 0
        ? Math.min(conversations, props.limits.conversations_max)
        : props.limits.conversations_max;
});

const { t } = useI18n();
</script>

<template>
    <Card class="overflow-hidden py-0">
        <CardContent class="p-0">
            <div class="flex items-start gap-3 p-5">
                <div class="bg-background text-muted-foreground flex size-10 shrink-0 items-center justify-center rounded-lg border">
                    <History class="size-5" />
                </div>
                <div class="space-y-0.5">
                    <p class="text-sm font-semibold">{{ t('workspace.security.retention.title') }}</p>
                    <p class="text-muted-foreground text-sm">{{ t('workspace.security.retention.help') }}</p>
                </div>
            </div>

            <div class="grid gap-4 border-t p-5 sm:grid-cols-2">
                <div class="space-y-1.5">
                    <Label for="ret-conv">{{ t('workspace.security.retention.conversations') }}</Label>
                    <Input
                        id="ret-conv"
                        v-model="conversationsDays"
                        type="number"
                        :min="limits.conversations_floor"
                        :max="limits.conversations_max"
                        :disabled="!canManage"
                        :placeholder="t('workspace.security.retention.plan_default', { days: limits.conversations_max })"
                    />
                    <p class="text-muted-foreground text-xs">
                        {{ t('workspace.security.retention.conversations_help', { max: limits.conversations_max }) }}
                    </p>
                    <p v-if="errors.retention_conversations_days" class="text-destructive text-xs">
                        {{ errors.retention_conversations_days }}
                    </p>
                </div>

                <div class="space-y-1.5">
                    <Label for="ret-audit">{{ t('workspace.security.retention.audit') }}</Label>
                    <Input
                        id="ret-audit"
                        v-model="auditDays"
                        type="number"
                        :min="limits.audit_floor"
                        :max="limits.audit_default"
                        :disabled="!canManage"
                        :placeholder="t('workspace.security.retention.plan_default', { days: limits.audit_default })"
                    />
                    <p class="text-muted-foreground text-xs">
                        {{
                            t('workspace.security.retention.audit_help', {
                                default: limits.audit_default,
                                floor: limits.audit_floor,
                            })
                        }}
                    </p>
                    <p v-if="errors.retention_audit_days" class="text-destructive text-xs">
                        {{ errors.retention_audit_days }}
                    </p>
                </div>

                <div class="space-y-1.5">
                    <Label for="ret-leads">{{ t('workspace.security.retention.leads') }}</Label>
                    <Input
                        id="ret-leads"
                        v-model="leadsDays"
                        type="number"
                        :min="limits.leads_floor"
                        :max="limits.max_days"
                        :disabled="!canManage"
                        :placeholder="t('workspace.security.retention.keep_forever')"
                    />
                    <p class="text-muted-foreground text-xs">{{ t('workspace.security.retention.leads_help') }}</p>
                    <p v-if="errors.retention_leads_days" class="text-destructive text-xs">
                        {{ errors.retention_leads_days }}
                    </p>
                </div>

                <div class="space-y-1.5">
                    <Label for="ret-analytics">{{ t('workspace.security.retention.analytics') }}</Label>
                    <Input
                        id="ret-analytics"
                        v-model="analyticsDays"
                        type="number"
                        :min="limits.analytics_floor"
                        :max="limits.max_days"
                        :disabled="!canManage"
                        :placeholder="t('workspace.security.retention.keep_forever')"
                    />
                    <p class="text-muted-foreground text-xs">
                        {{ t('workspace.security.retention.analytics_help', { floor: limits.analytics_floor }) }}
                    </p>
                    <p v-if="errors.retention_analytics_days" class="text-destructive text-xs">
                        {{ errors.retention_analytics_days }}
                    </p>
                </div>

                <div class="space-y-1.5">
                    <Label for="ret-anonymize">{{ t('workspace.security.retention.anonymize') }}</Label>
                    <Input
                        id="ret-anonymize"
                        v-model="anonymizeDays"
                        type="number"
                        :min="limits.anonymize_floor"
                        :max="anonymizeMax"
                        :disabled="!canManage"
                        :placeholder="t('workspace.security.retention.never')"
                    />
                    <p class="text-muted-foreground text-xs">
                        {{ t('workspace.security.retention.anonymize_help', { max: anonymizeMax }) }}
                    </p>
                    <p v-if="errors.anonymize_after_days" class="text-destructive text-xs">
                        {{ errors.anonymize_after_days }}
                    </p>
                </div>
            </div>
        </CardContent>
    </Card>
</template>
