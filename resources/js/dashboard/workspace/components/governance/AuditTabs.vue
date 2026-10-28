<script setup lang="ts">
import { useTeamPermissions } from '@/dashboard/composables/useTeamPermissions';
import governance from '@/routes/governance';
import security from '@/routes/security';
import { Link } from '@inertiajs/vue3';
import { ScrollText } from 'lucide-vue-next';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

// The two workspace audit logs share one "Audit" page. This renders the shared
// heading plus a tab bar that navigates between the tool-call log
// (`governance.audit`, gated by `account.governance`) and the security log
// (`security.audit`, gated by `account.security`). Only tabs the team role can
// reach are shown; the hosting page declares which one is active.
defineProps<{ active: 'tools' | 'security' }>();

const { t } = useI18n();
const { hasPermission } = useTeamPermissions();

const tabs = computed(() =>
    [
        {
            key: 'tools' as const,
            label: t('workspace.audit.tabs.tool_calls'),
            href: governance.audit.url(),
            show: hasPermission('account.governance'),
        },
        { key: 'security' as const, label: t('workspace.audit.tabs.security'), href: security.audit.url(), show: hasPermission('account.security') },
    ].filter((tab) => tab.show),
);
</script>

<template>
    <div class="space-y-4">
        <h1 class="flex items-center gap-2 text-2xl font-bold tracking-tight">
            <ScrollText class="size-6" />
            {{ t('workspace.audit.title') }}
        </h1>

        <div v-if="tabs.length > 1" class="border-b">
            <nav class="-mb-px flex gap-6">
                <Link
                    v-for="tab in tabs"
                    :key="tab.key"
                    :href="tab.href"
                    :class="[
                        'border-b-2 px-1 pb-2 text-sm font-medium transition-colors',
                        tab.key === active ? 'border-primary text-foreground' : 'text-muted-foreground hover:text-foreground border-transparent',
                    ]"
                >
                    {{ tab.label }}
                </Link>
            </nav>
        </div>
    </div>
</template>
