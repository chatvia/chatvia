<script setup lang="ts">
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import TeamSwitcher from '@/components/TeamSwitcher.vue';
import ThemeSwitcher from '@/components/ThemeSwitcher.vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { DropdownMenu, DropdownMenuContent, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import {
    NavigationMenu,
    NavigationMenuItem,
    NavigationMenuLink,
    NavigationMenuList,
    navigationMenuTriggerStyle,
} from '@/components/ui/navigation-menu';
import { Sheet, SheetContent, SheetHeader, SheetTitle, SheetTrigger } from '@/components/ui/sheet';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import UpgradeButton from '@/components/UpgradeButton.vue';
import UserMenuContent from '@/components/UserMenuContent.vue';
import { getInitials } from '@/composables/useInitials';
import { useTeamPermissions } from '@/composables/useTeamPermissions';
import type { Auth, BreadcrumbItem, NavItem } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import { BarChart3, BookOpen, BotMessageSquare, Code2, CreditCard, HelpCircle, LayoutGrid, Menu, Sparkles, Users } from 'lucide-vue-next';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

interface Props {
    breadcrumbs?: BreadcrumbItem[];
}

const props = withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
});

const page = usePage();
const auth = computed(() => page.props.auth as Auth);

const isCurrentRoute = computed(() => (url: string) => {
    const currentUrl = page.url;

    // Extract pathname from both URLs to handle absolute vs relative URLs
    const getCurrentPath = (fullUrl: string) => {
        try {
            return new URL(fullUrl, window.location.origin).pathname;
        } catch {
            return fullUrl.startsWith('/') ? fullUrl : '/' + fullUrl;
        }
    };

    const currentPath = getCurrentPath(currentUrl);
    const targetPath = getCurrentPath(url);

    // Exact match
    if (currentPath === targetPath) return true;

    // For sub-routes, check if current path starts with the menu path
    // But exclude dashboard to avoid false positives
    if (targetPath !== '/' && targetPath !== '/dashboard' && currentPath.startsWith(targetPath)) {
        return true;
    }

    return false;
});

const activeItemStyles = computed(
    () => (url: string) => (isCurrentRoute.value(url) ? 'text-neutral-900 dark:bg-neutral-800 dark:text-neutral-100' : ''),
);

const isStaff = computed(() => auth.value.user.type === 'staff');

const { isOwner } = useTeamPermissions();

const mainNavItems = computed<NavItem[]>(() => {
    const items: NavItem[] = [
        {
            title: t('app.header.dashboard'),
            href: route('dashboard'),
            icon: LayoutGrid,
        },
        {
            title: t('app.header.agents'),
            href: route('agents.index'),
            icon: BotMessageSquare,
        },
        {
            title: t('app.header.usage'),
            href: route('usage'),
            icon: BarChart3,
        },
    ];

    // "Ask your data" is owner-only — it exposes whole-account numbers
    // (including identified users) and is gated server-side to the owner.
    if (!isStaff.value && isOwner.value) {
        items.push({
            title: t('app.header.ask_your_data'),
            href: route('analytics.chat'),
            icon: Sparkles,
        });
    }

    // Billing is workspace-owner only — employees/teammates are blocked from
    // the billing routes server-side (DenyIfEmployee), and staff never see it.
    if (!isStaff.value && isOwner.value) {
        items.push({
            title: t('app.header.billing'),
            href: route('billing.index'),
            icon: CreditCard,
        });
    }

    items.push({
        title: t('app.header.support'),
        href: route('support'),
        icon: HelpCircle,
    });

    return items;
});

const rightNavItems = computed<NavItem[]>(() => [
    {
        title: t('app.header.documentation'),
        href: 'https://docs.chatvia.ai',
        icon: BookOpen,
    },
    {
        title: t('app.header.api'),
        href: '/docs/api',
        icon: Code2,
    },
]);
</script>

<template>
    <!-- Fixed header -->
    <div class="bg-background fixed top-0 right-0 left-0 z-50">
        <div class="border-sidebar-border/80 border-b">
            <div class="mx-auto flex h-16 items-center px-4 md:max-w-7xl">
                <!-- Mobile Menu -->
                <div class="lg:hidden">
                    <Sheet>
                        <SheetTrigger :as-child="true">
                            <Button variant="ghost" size="icon" class="me-2 h-9 w-9">
                                <Menu class="h-5 w-5" />
                            </Button>
                        </SheetTrigger>
                        <SheetContent side="left" class="w-[300px] p-6">
                            <SheetTitle class="sr-only">{{ t('app.header.nav_menu_sr') }}</SheetTitle>
                            <SheetHeader class="flex justify-start text-left">
                                <AppLogoIcon class="size-6 text-black dark:text-white" />
                            </SheetHeader>
                            <div class="flex h-full flex-1 flex-col justify-between space-y-4 py-6">
                                <nav class="-mx-3 space-y-1">
                                    <Link
                                        v-for="item in mainNavItems"
                                        :key="item.title"
                                        :href="item.href"
                                        class="hover:bg-accent flex items-center gap-x-3 rounded-lg px-3 py-2 text-sm font-medium"
                                        :class="activeItemStyles(item.href)"
                                    >
                                        <component v-if="item.icon" :is="item.icon" class="h-5 w-5" />
                                        {{ item.title }}
                                    </Link>
                                    <!-- Teams Link -->
                                    <Link
                                        :href="route('teams.index')"
                                        class="hover:bg-accent flex items-center gap-x-3 rounded-lg px-3 py-2 text-sm font-medium"
                                        :class="activeItemStyles(route('teams.index'))"
                                    >
                                        <Users class="h-5 w-5" />
                                        {{ t('app.header.teams') }}
                                    </Link>
                                </nav>
                                <div class="flex flex-col space-y-4">
                                    <a
                                        v-for="item in rightNavItems"
                                        :key="item.title"
                                        :href="item.href"
                                        rel="noopener noreferrer"
                                        class="flex items-center space-x-2 text-sm font-medium"
                                    >
                                        <component v-if="item.icon" :is="item.icon" class="h-5 w-5" />
                                        <span>{{ item.title }}</span>
                                    </a>
                                </div>
                            </div>
                        </SheetContent>
                    </Sheet>
                </div>

                <Link :href="route('dashboard')" class="flex items-center gap-x-2">
                    <AppLogoIcon class="size-10" />
                </Link>

                <!-- Desktop Menu -->
                <div class="hidden h-full lg:flex lg:flex-1">
                    <NavigationMenu class="ml-10 flex h-full items-stretch">
                        <NavigationMenuList class="flex h-full items-stretch space-x-2">
                            <NavigationMenuItem v-for="(item, index) in mainNavItems" :key="index" class="relative flex h-full items-center">
                                <Link :href="item.href">
                                    <NavigationMenuLink
                                        :class="[navigationMenuTriggerStyle(), activeItemStyles(item.href), 'flex cursor-pointer items-center px-3']"
                                    >
                                        <component v-if="item.icon" :is="item.icon" class="me-2 h-4 w-4" />
                                        {{ item.title }}
                                    </NavigationMenuLink>
                                </Link>
                                <div
                                    v-if="isCurrentRoute(item.href)"
                                    class="absolute bottom-0 left-0 h-0.5 w-full translate-y-px bg-black dark:bg-white"
                                ></div>
                            </NavigationMenuItem>
                        </NavigationMenuList>
                    </NavigationMenu>
                </div>

                <div class="ml-auto flex items-center space-x-2">
                    <TeamSwitcher class="hidden md:flex" />
                    <div class="relative flex items-center space-x-1">
                        <UpgradeButton />

                        <div class="hidden space-x-1 lg:flex">
                            <template v-for="item in rightNavItems" :key="item.title">
                                <TooltipProvider :delay-duration="0">
                                    <Tooltip>
                                        <TooltipTrigger>
                                            <Button variant="ghost" size="icon" as-child class="group h-9 w-9 cursor-pointer">
                                                <a :href="item.href" target="_blank" rel="noopener noreferrer">
                                                    <span class="sr-only">{{ item.title }}</span>
                                                    <component :is="item.icon" class="size-5 opacity-80 group-hover:opacity-100" />
                                                </a>
                                            </Button>
                                        </TooltipTrigger>
                                        <TooltipContent>
                                            <p>{{ item.title }}</p>
                                        </TooltipContent>
                                    </Tooltip>
                                </TooltipProvider>
                            </template>
                        </div>
                    </div>
                    <ThemeSwitcher />
                    <DropdownMenu>
                        <DropdownMenuTrigger :as-child="true">
                            <Button
                                variant="ghost"
                                size="icon"
                                class="focus-within:ring-primary relative size-10 w-auto rounded-full p-1 focus-within:ring-2"
                            >
                                <Avatar class="size-8 overflow-hidden rounded-full">
                                    <AvatarImage v-if="auth.user.avatar" :src="auth.user.avatar" :alt="auth.user.name" />
                                    <AvatarFallback class="rounded-lg bg-neutral-200 font-semibold text-black dark:bg-neutral-700 dark:text-white">
                                        {{ getInitials(auth.user?.name) }}
                                    </AvatarFallback>
                                </Avatar>
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end" class="w-56">
                            <UserMenuContent :user="auth.user" />
                        </DropdownMenuContent>
                    </DropdownMenu>
                </div>
            </div>
        </div>

        <div v-if="props.breadcrumbs.length > 1" class="border-sidebar-border/70 flex w-full border-b">
            <div class="mx-auto flex h-12 w-full items-center justify-start px-4 text-neutral-500 md:max-w-7xl">
                <Breadcrumbs :breadcrumbs="breadcrumbs" />
            </div>
        </div>
    </div>
    <div :class="props.breadcrumbs.length > 1 ? 'h-28' : 'h-16'"></div>
</template>

<style scoped>
/* No custom styles needed */
</style>
