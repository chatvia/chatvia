<script setup lang="ts">
/**
 * The sticky top bar of the agent area: breadcrumbs (Dashboard › Agents ›
 * agent name), the mobile sidebar toggle, and the theme / user menu
 * on large screens.
 */
import ThemeSwitcher from '@/components/ThemeSwitcher.vue';
import { SidebarTrigger } from '@/components/ui/sidebar';
import UserMenu from '@/components/UserMenu.vue';
import { dashboard } from '@/routes';
import { index as agentsIndex } from '@/routes/agents';
import { Link } from '@inertiajs/vue3';
import { ChevronRight } from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';

defineProps<{
    agentName: string;
}>();

const { t } = useI18n();
</script>

<template>
    <header class="bg-background border-border sticky top-0 z-40 border-b">
        <div class="px-4 lg:px-6">
            <div class="flex h-16 items-center justify-between">
                <!-- Start: mobile sidebar toggle and breadcrumbs -->
                <div class="flex items-center gap-4">
                    <SidebarTrigger class="md:hidden" />

                    <div class="flex items-center gap-2 text-sm">
                        <Link :href="dashboard.url()" class="text-muted-foreground hover:text-foreground hidden transition-colors md:inline">
                            {{ t('app.header.dashboard') }}
                        </Link>
                        <ChevronRight class="text-muted-foreground hidden h-4 w-4 md:block rtl:rotate-180" />
                        <Link :href="agentsIndex.url()" class="text-muted-foreground hover:text-foreground hidden transition-colors md:inline">
                            {{ t('app.header.agents') }}
                        </Link>
                        <ChevronRight class="text-muted-foreground hidden h-4 w-4 md:block rtl:rotate-180" />
                        <span class="text-foreground font-medium">{{ agentName }}</span>
                    </div>
                </div>

                <!-- End: theme and user menu -->
                <div class="flex items-center gap-3">
                    <div class="hidden items-center gap-2 lg:flex">
                        <ThemeSwitcher />
                        <UserMenu />
                    </div>
                </div>
            </div>
        </div>
    </header>
</template>
