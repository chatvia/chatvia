<script setup lang="ts">
// Header call-to-action for the workspace owner: an "Upgrade" link on the free
// plan, a trophy with a "subscribed" tooltip on a paid plan. Hidden for
// teammates and on installs without billing.
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import { useBilling } from '@/composables/useBilling';
import type { Auth } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import { Trophy, Zap } from 'lucide-vue-next';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

const page = usePage();
const auth = computed(() => page.props.auth as Auth);
// Employees (teammates) don't manage billing — the upgrade prompt is owner-only.
const isEmployee = computed(() => auth.value.user.type === 'employee');
// Nothing to upgrade or show when the install runs without billing.
const { billingEnabled } = useBilling();
</script>

<template>
    <Link
        v-if="billingEnabled && !auth.user.is_paid && !isEmployee"
        :href="route('billing.packages')"
        class="bg-primary text-primary-foreground hover:bg-primary/90 focus:ring-primary/20 group hidden transform items-center gap-1.5 rounded-md px-4 py-2 text-sm font-medium transition-all duration-300 ease-out hover:scale-105 hover:shadow-lg focus:ring-2 focus:ring-offset-2 focus:outline-none active:scale-95 lg:flex"
    >
        <Zap class="h-3.5 w-3.5 transition-transform duration-300 group-hover:rotate-12" />
        {{ t('app.header.upgrade') }}
    </Link>
    <TooltipProvider v-else-if="billingEnabled && auth.user.is_paid && !isEmployee" :delay-duration="0">
        <Tooltip>
            <TooltipTrigger :aria-label="t('app.header.subscribed')">
                <span class="hidden h-9 w-9 items-center justify-center rounded-md lg:flex">
                    <Trophy class="h-4.5 w-4.5 text-amber-500" />
                </span>
            </TooltipTrigger>
            <TooltipContent>
                <p>{{ t('app.header.subscribed') }}</p>
            </TooltipContent>
        </Tooltip>
    </TooltipProvider>
</template>
