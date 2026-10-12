<script setup lang="ts">
// Header dropdown that lists the user's teams (loaded the first time it opens),
// switches the active team, and offers a quick "create team" dialog.
import { listSwitcherTeams, type SwitcherTeam } from '@/api/teams';
import CreateTeamDialog from '@/components/CreateTeamDialog.vue';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { getInitials } from '@/composables/useInitials';
import { index as teamsIndex, switchMethod as switchTeamRoute } from '@/routes/teams';
import type { Auth, SharedData } from '@/types';
import { Link, router, usePage } from '@inertiajs/vue3';
import { Check, ChevronsUpDown, Loader2, Plus, Settings } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

const page = usePage<SharedData>();
const auth = computed(() => page.props.auth as Auth);
const currentTeam = computed(() => page.props.currentTeam);
// Teammates are blocked from the teams-management routes by the backend
// `DenyIfTeammate` middleware, so they must not see Manage/Create here.
const isTeammate = computed(() => auth.value.is_workspace_owner !== true);

const isOpen = ref(false);
const isCreateDialogOpen = ref(false);
const teams = ref<SwitcherTeam[]>([]);
const isLoading = ref(false);

const fetchTeams = async () => {
    if (teams.value.length > 0) return;
    isLoading.value = true;
    try {
        teams.value = await listSwitcherTeams();
    } finally {
        isLoading.value = false;
    }
};

watch(isOpen, (open) => {
    if (open) fetchTeams();
});

const isSwitching = ref(false);

const switchTeam = (team: SwitcherTeam) => {
    if (team.id === currentTeam.value?.id || isSwitching.value) return;

    isSwitching.value = true;
    isOpen.value = false;

    router.post(
        // Route binding is by external_id (see Team::getRouteKeyName) — keep
        // the in-memory id around for the currentTeam comparison above but
        // put the public UUID in the URL.
        switchTeamRoute.url(team.external_id),
        {},
        {
            preserveState: false,
            onFinish: () => {
                isSwitching.value = false;
            },
        },
    );
};

const openCreateDialog = () => {
    isOpen.value = false;
    isCreateDialogOpen.value = true;
};
</script>

<template>
    <div>
        <DropdownMenu v-model:open="isOpen">
            <DropdownMenuTrigger as-child>
                <Button variant="outline" class="h-9 gap-2 px-3">
                    <Avatar class="size-5">
                        <AvatarFallback class="bg-primary/10 text-primary text-[10px] font-medium">
                            {{ currentTeam ? getInitials(currentTeam.name) : '?' }}
                        </AvatarFallback>
                    </Avatar>
                    <span class="max-w-[120px] truncate text-sm font-medium">
                        {{ currentTeam?.name || t('team_switcher.select_team') }}
                    </span>
                    <ChevronsUpDown class="size-4 opacity-50" />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="start" class="w-56">
                <DropdownMenuLabel class="text-muted-foreground text-xs">{{ t('teams.index.title') }}</DropdownMenuLabel>
                <div class="max-h-[300px] overflow-y-auto">
                    <div v-if="isLoading" class="flex justify-center px-2 py-4">
                        <Loader2 class="text-muted-foreground size-4 animate-spin" />
                    </div>
                    <template v-else>
                        <DropdownMenuItem v-for="team in teams" :key="team.id" class="cursor-pointer gap-2" @select.prevent="switchTeam(team)">
                            <Avatar class="size-6">
                                <AvatarFallback class="bg-primary/10 text-primary text-[10px] font-medium">
                                    {{ getInitials(team.name) }}
                                </AvatarFallback>
                            </Avatar>
                            <span class="flex-1 truncate">{{ team.name }}</span>
                            <Check v-if="team.id === currentTeam?.id" class="text-primary size-4" />
                        </DropdownMenuItem>
                        <div v-if="teams.length === 0" class="text-muted-foreground px-2 py-4 text-center text-sm">
                            {{ t('team_switcher.no_teams') }}
                        </div>
                    </template>
                </div>
                <template v-if="!isTeammate">
                    <DropdownMenuSeparator />
                    <DropdownMenuItem as-child>
                        <Link :href="teamsIndex.url()" class="cursor-pointer gap-2">
                            <Settings class="size-4" />
                            {{ t('team_switcher.manage_teams') }}
                        </Link>
                    </DropdownMenuItem>
                    <DropdownMenuItem class="cursor-pointer gap-2" @click="openCreateDialog">
                        <Plus class="size-4" />
                        {{ t('teams.index.create_team') }}
                    </DropdownMenuItem>
                </template>
            </DropdownMenuContent>
        </DropdownMenu>

        <!-- Clearing the list makes the next open refetch it, new team included. -->
        <CreateTeamDialog v-model:open="isCreateDialogOpen" @created="teams = []" />
    </div>
</template>
