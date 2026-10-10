<script setup lang="ts">
// Data retention card: how many days conversations and audit entries are
// kept. An empty field means "use the default" shown as the placeholder.
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { History } from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';
import type { RetentionLimits } from './useSecurityPolicyForm';

defineProps<{
    canManage: boolean;
    limits: RetentionLimits;
    errors: { retention_conversations_days?: string; retention_audit_days?: string };
}>();

const conversationsDays = defineModel<string>('conversationsDays', { required: true });
const auditDays = defineModel<string>('auditDays', { required: true });

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
            </div>
        </CardContent>
    </Card>
</template>
