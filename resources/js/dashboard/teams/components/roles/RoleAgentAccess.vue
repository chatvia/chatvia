<script setup lang="ts">
// "Agent access" section of the role form: either every agent (now and in
// the future) or a hand-picked list. Ticking "all agents" clears the list.
import { Bot, Check, ChevronDown } from 'lucide-vue-next';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

import InputError from '@/shared/components/InputError.vue';
import { Button } from '@/shared/ui/button';
import { Checkbox } from '@/shared/ui/checkbox';
import { Label } from '@/shared/ui/label';
import { Popover, PopoverContent, PopoverTrigger } from '@/shared/ui/popover';
import { ScrollArea } from '@/shared/ui/scroll-area';

type TeamAgentData = Domain.Membership.Team.Data.Resources.Team.TeamAgentData;

const allAgents = defineModel<boolean>('allAgents', { required: true });
const agentIds = defineModel<string[]>('agentIds', { required: true });

const props = defineProps<{
    agents: TeamAgentData[];
    error?: string;
}>();

const { t } = useI18n();

const selectedAgentNames = computed(() => {
    if (allAgents.value) return t('teams.roles.all_agents');
    if (agentIds.value.length === 0) return t('teams.roles.select_agents');
    const names = props.agents.filter((a) => agentIds.value.includes(a.external_id)).map((a) => a.name);
    if (names.length <= 2) return names.join(', ');
    return t('teams.roles.agents_more', { names: names.slice(0, 2).join(', '), count: names.length - 2 });
});

const onAllAgentsChange = (checked: boolean | 'indeterminate') => {
    allAgents.value = checked === true;
    if (checked) agentIds.value = [];
};

const toggleAgentSelection = (agentId: string) => {
    if (agentIds.value.includes(agentId)) {
        agentIds.value = agentIds.value.filter((id) => id !== agentId);
    } else {
        agentIds.value = [...agentIds.value, agentId];
    }
};
</script>

<template>
    <section class="space-y-3">
        <div class="flex items-center gap-2">
            <Bot class="text-muted-foreground size-4" />
            <h3 class="text-sm font-semibold tracking-tight">{{ t('teams.roles.agent_access') }}</h3>
        </div>
        <p class="text-muted-foreground ps-6 text-sm">{{ t('teams.roles.agent_access_description') }}</p>
        <div class="space-y-4 ps-6">
            <div class="flex items-center gap-3">
                <Checkbox id="all-agents" :model-value="allAgents" @update:model-value="onAllAgentsChange" />
                <Label for="all-agents" class="cursor-pointer">
                    <span class="font-medium">{{ t('teams.roles.all_agents') }}</span>
                    <span class="text-muted-foreground ms-2 text-sm">{{ t('teams.roles.all_agents_description') }}</span>
                </Label>
            </div>

            <div v-if="!allAgents">
                <Label class="text-muted-foreground mb-2 block text-sm">{{ t('teams.roles.select_specific') }}</Label>
                <Popover>
                    <PopoverTrigger as-child>
                        <Button variant="outline" class="w-full justify-between">
                            <span class="flex items-center gap-2 truncate">
                                <Bot class="h-4 w-4 shrink-0" />
                                {{ selectedAgentNames }}
                            </span>
                            <ChevronDown class="h-4 w-4 shrink-0 opacity-50" />
                        </Button>
                    </PopoverTrigger>
                    <PopoverContent class="w-[300px] p-0" align="start">
                        <ScrollArea class="h-[200px]">
                            <div class="space-y-1 p-2">
                                <div
                                    v-for="agent in agents"
                                    :key="agent.external_id"
                                    @click="toggleAgentSelection(agent.external_id)"
                                    class="hover:bg-accent flex cursor-pointer items-center gap-2 rounded-md px-2 py-1.5"
                                >
                                    <div
                                        class="flex h-4 w-4 items-center justify-center rounded border"
                                        :class="agentIds.includes(agent.external_id) ? 'bg-primary border-primary' : 'border-input'"
                                    >
                                        <Check v-if="agentIds.includes(agent.external_id)" class="text-primary-foreground h-3 w-3" />
                                    </div>
                                    <span class="text-sm">{{ agent.name }}</span>
                                </div>
                                <div v-if="agents.length === 0" class="text-muted-foreground px-2 py-4 text-center text-sm">
                                    {{ t('teams.roles.no_agents_available') }}
                                </div>
                            </div>
                        </ScrollArea>
                    </PopoverContent>
                </Popover>
                <InputError :message="error" class="mt-2" />
            </div>
        </div>
    </section>
</template>
