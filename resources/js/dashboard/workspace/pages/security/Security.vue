<script setup lang="ts">
// Workspace Security settings: network access control (IP allowlist and
// country rules), data retention and the SIEM webhook, saved together with one
// button. All three need an Enterprise license; blocked visitors do not.
import type { BlockedVisitor } from '@/dashboard/api/blocking';
import LicenseNotice from '@/dashboard/components/license/LicenseNotice.vue';
import BlockedVisitorsCard from '@/dashboard/components/security/BlockedVisitorsCard.vue';
import { Button } from '@/shared/ui/button';
import { useEnterprise } from '@/dashboard/workspace/composables/useEnterprise';
import { useTeamPermissions } from '@/dashboard/composables/useTeamPermissions';
import AppLayout from '@/dashboard/layouts/AppLayout.vue';
import WorkspaceLayout from '@/dashboard/workspace/layouts/Layout.vue';
import { type BreadcrumbItem } from '@/shared/types';
import { Head } from '@inertiajs/vue3';
import { LoaderCircle, Lock, Save } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import type { Country } from './countries';
import NetworkAccessCard from './NetworkAccessCard.vue';
import RetentionCard from './RetentionCard.vue';
import SiemWebhookCard from './SiemWebhookCard.vue';
import { useSecurityPolicyForm, type RetentionLimits, type SecurityPolicy } from './useSecurityPolicyForm';

interface Props {
    policy: SecurityPolicy;
    countries: Country[];
    limits: RetentionLimits;
    // The viewer's own country (lowercase ISO code) for the live lockout warning;
    // null when it can't be resolved (private IP / provider miss).
    current_country: string | null;
    // Visitors blocked from chatting (made from an agent's Guardrails activity).
    blocked_visitors: BlockedVisitor[];
}

const props = defineProps<Props>();

const { t } = useI18n();
const { hasPermission } = useTeamPermissions();

const { enterprise } = useEnterprise();

const canManage = computed(() => hasPermission('account.security'));
// The network, retention and SIEM settings are Enterprise features: without a
// key they show their saved values but cannot change.
const canEdit = computed(() => canManage.value && enterprise.value);

const breadcrumbs: BreadcrumbItem[] = [{ title: t('workspace.security.breadcrumb'), href: '/my/workspace/security' }];

const { form, save } = useSecurityPolicyForm(props.policy);

// Unblocking answers with no page reload: drop the row here.
const blockedVisitors = ref<BlockedVisitor[]>([...props.blocked_visitors]);
const dropBlock = (blockId: string) => {
    blockedVisitors.value = blockedVisitors.value.filter((block) => block.external_id !== blockId);
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="t('workspace.security.title')" />

        <WorkspaceLayout width="wide">
            <div class="w-full max-w-3xl space-y-6">
                <div>
                    <h1 class="flex items-center gap-2 text-2xl font-bold tracking-tight">
                        <Lock class="size-6" />
                        {{ t('workspace.security.title') }}
                    </h1>
                    <p class="text-muted-foreground mt-1 text-sm">{{ t('workspace.security.description') }}</p>
                </div>

                <LicenseNotice v-if="!enterprise" />

                <NetworkAccessCard
                    v-model:ip-allowlist-mode="form.ip_allowlist_mode"
                    v-model:ip-allowlist="form.ip_allowlist"
                    v-model:geo-mode="form.geo_mode"
                    v-model:geo-countries="form.geo_countries"
                    :can-manage="canEdit"
                    :countries="props.countries"
                    :current-country="props.current_country"
                    :errors="form.errors"
                />

                <RetentionCard
                    v-model:conversations-days="form.retention_conversations_days"
                    v-model:leads-days="form.retention_leads_days"
                    v-model:audit-days="form.retention_audit_days"
                    v-model:analytics-days="form.retention_analytics_days"
                    v-model:anonymize-days="form.anonymize_after_days"
                    :can-manage="canEdit"
                    :limits="props.limits"
                    :errors="form.errors"
                />

                <SiemWebhookCard
                    v-model:enabled="form.webhook_enabled"
                    v-model:url="form.webhook_url"
                    v-model:secret="form.webhook_secret"
                    :can-manage="canEdit"
                    :errors="form.errors"
                />

                <div v-if="canEdit" class="flex justify-end">
                    <Button :disabled="form.processing" @click="save">
                        <LoaderCircle v-if="form.processing" class="me-2 h-4 w-4 animate-spin" />
                        <Save v-else class="me-2 h-4 w-4" />
                        {{ t('workspace.security.save') }}
                    </Button>
                </div>

                <!-- Saved on its own: Unblock applies right away, not with Save. -->
                <BlockedVisitorsCard :blocks="blockedVisitors" :can-manage="canManage" @unblocked="dropBlock" />
            </div>
        </WorkspaceLayout>
    </AppLayout>
</template>
