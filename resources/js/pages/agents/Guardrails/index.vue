<script setup lang="ts">
// Guardrails page for one agent, in four tabs: Configuration with the safety
// settings (PII redaction, injection and toxicity checks with their mode and
// sensitivity, blocked topics, allowed scope, refusal message, holding answers
// until checked, alert emails), "Test a message" against those settings,
// Activity with the trigger log, and Blocked: who cannot chat with the agent
// (loaded when the tab opens).
import HelpPopover from '@/components/common/HelpPopover.vue';
import BlockedList from '@/components/security/BlockedList.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Textarea } from '@/components/ui/textarea';
import { useTeamPermissions } from '@/composables/useTeamPermissions';
import AgentLayout from '@/layouts/AgentLayout.vue';
import { Head } from '@inertiajs/vue3';
import {
    Ban,
    FlaskConical,
    History,
    LoaderCircle,
    MessageSquareOff,
    Save,
    ShieldAlert,
    ShieldOff,
    SlidersHorizontal,
    Target,
    UserRound,
    UserX,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import AiCheckBadge from './AiCheckBadge.vue';
import BlockedTopicsDialog from './BlockedTopicsDialog.vue';
import GuardrailTestCard from './GuardrailTestCard.vue';
import GuardrailTriggerLog from './GuardrailTriggerLog.vue';
import { FILTER_LABELS, labelFor, MODE_LABELS, SENSITIVITY_LABELS } from './labels';
import PiiFiltersDialog from './PiiFiltersDialog.vue';
import type { GuardMode, GuardrailLogFilters, GuardrailTriggerPage, GuardSensitivity, GuardSettings, PiiFilterOption } from './types';
import { GUARDS, useGuardrailSettings } from './useGuardrailSettings';

const { t } = useI18n();

interface Props {
    agent: { external_id: string; name: string };
    settings: GuardSettings;
    availableFilters: PiiFilterOption[];
    defaultFilters: string[];
    triggers: GuardrailTriggerPage;
    guardTypes: string[];
    outcomes: string[];
    filters: GuardrailLogFilters;
}

const props = defineProps<Props>();

const { hasPermission } = useTeamPermissions();
const canManage = computed(() => hasPermission('agent.guardrails.manage'));
const canViewChats = computed(() => hasPermission('agent.chat.view'));

// A filtered log link opens on the Activity tab.
const activeTab = ref<'config' | 'test' | 'activity' | 'blocked'>(
    props.filters.guard_type || props.filters.outcome || props.filters.visitor ? 'activity' : 'config',
);

const { form, save, toggle, piiEnabled, togglePii, selectedFilters } = useGuardrailSettings(props, canManage);

// Underline tabs: one rule across the column with the active tab marked on it,
// so the tabs read as part of the page, not as a control floating above it.
const TABS_LIST_CLASS = 'h-auto w-full justify-start gap-1 rounded-none border-b bg-transparent p-0';
const TAB_CLASS =
    'text-muted-foreground hover:text-foreground data-[state=active]:text-foreground data-[state=active]:border-primary dark:data-[state=active]:border-primary -mb-px h-auto flex-none cursor-pointer rounded-none border-0 border-b-2 border-transparent bg-transparent px-3 pt-1 pb-2.5 data-[state=active]:bg-transparent data-[state=active]:shadow-none dark:data-[state=active]:bg-transparent';

const MODES: GuardMode[] = ['block', 'monitor'];
const SENSITIVITIES: GuardSensitivity[] = ['high', 'medium', 'low'];

// The PII filters and blocked topics are shown as badges on the card; each
// list is edited in its own dialog that commits on "Done".
const filtersOpen = ref(false);
const topicsOpen = ref(false);
</script>

<template>
    <AgentLayout :agent="props.agent" :title="t('agents.guardrails.title')" :description="t('agents.guardrails.description')">
        <Head :title="t('agents.guardrails.head_title', { name: props.agent.name })" />

        <div class="flex justify-center">
            <div class="min-h-[calc(100vh-220px)] w-full max-w-5xl">
                <Tabs v-model="activeTab">
                    <TabsList :class="TABS_LIST_CLASS">
                        <TabsTrigger value="config" :class="TAB_CLASS">
                            <SlidersHorizontal class="h-4 w-4" />
                            {{ t('agents.guardrails.configuration') }}
                        </TabsTrigger>
                        <TabsTrigger v-if="canManage" value="test" :class="TAB_CLASS">
                            <FlaskConical class="h-4 w-4" />
                            {{ t('agents.guardrails.test_title') }}
                        </TabsTrigger>
                        <TabsTrigger value="activity" :class="TAB_CLASS">
                            <History class="h-4 w-4" />
                            {{ t('agents.guardrails.activity') }}
                        </TabsTrigger>
                        <TabsTrigger value="blocked" :class="TAB_CLASS">
                            <UserX class="h-4 w-4" />
                            {{ t('agents.guardrails.blocked_tab') }}
                        </TabsTrigger>
                    </TabsList>

                    <!-- Settings -->
                    <TabsContent value="config" class="mt-6">
                        <Card>
                            <CardContent class="space-y-6">
                                <!-- Master switch -->
                                <div
                                    class="flex items-center justify-between gap-4 rounded-md border p-3"
                                    :class="{ 'cursor-pointer': canManage }"
                                    @click="toggle('enabled')"
                                >
                                    <div>
                                        <!-- The "?" says what guardrails are and which checks add AI calls. -->
                                        <div class="flex items-center gap-1.5">
                                            <Label class="pointer-events-none text-sm font-medium">{{
                                                t('agents.guardrails.enable_guardrails')
                                            }}</Label>
                                            <HelpPopover
                                                :label="t('agents.guardrails.about_aria')"
                                                :title="t('agents.guardrails.about_title')"
                                                :paragraphs="[
                                                    t('agents.guardrails.about_body'),
                                                    t('agents.guardrails.about_ai_calls'),
                                                    t('agents.guardrails.about_cost'),
                                                ]"
                                            />
                                        </div>
                                        <p class="text-muted-foreground text-sm">{{ t('agents.guardrails.master_switch') }}</p>
                                    </div>
                                    <Switch v-model="form.enabled" :disabled="!canManage" class="pointer-events-none" />
                                </div>

                                <!-- Per-guard toggles — only shown once the master is on.
                                     px-[13px] mirrors the master card's content inset
                                     (1px border + p-3) so every label, switch, and input
                                     lines up with the Enable-guardrails row above. -->
                                <div v-if="form.enabled" class="space-y-3 px-[13px]">
                                    <!-- PII detection & redaction — master toggle + managed filter list -->
                                    <div class="space-y-2">
                                        <div
                                            class="flex items-start justify-between gap-4"
                                            :class="{ 'cursor-pointer': canManage }"
                                            @click="togglePii"
                                        >
                                            <div>
                                                <Label class="pointer-events-none text-sm font-medium">{{ t('agents.guardrails.pii_label') }}</Label>
                                                <p class="text-muted-foreground text-sm">
                                                    {{ t('agents.guardrails.pii_help') }}
                                                </p>
                                            </div>
                                            <Switch :model-value="piiEnabled" :disabled="!canManage" class="pointer-events-none" />
                                        </div>

                                        <div v-if="piiEnabled" class="flex flex-wrap items-center gap-2">
                                            <Badge
                                                v-for="filter in selectedFilters"
                                                :key="filter.key"
                                                :variant="form.pii_actions[filter.key] === 'block' ? 'destructive' : 'secondary'"
                                                class="font-normal"
                                                >{{ labelFor(t, FILTER_LABELS, filter.key) }}</Badge
                                            >
                                            <Badge
                                                v-for="pattern in form.custom_patterns"
                                                :key="pattern.name"
                                                :variant="pattern.action === 'block' ? 'destructive' : 'outline'"
                                                class="font-normal"
                                                >{{ pattern.name }}</Badge
                                            >
                                            <span v-if="form.allowlist.length" class="text-muted-foreground text-sm">{{
                                                t('agents.guardrails.allowlist_count', { count: form.allowlist.length }, form.allowlist.length)
                                            }}</span>
                                            <Button
                                                v-if="canManage"
                                                type="button"
                                                variant="link"
                                                class="h-auto p-0 text-sm"
                                                @click="filtersOpen = true"
                                            >
                                                <ShieldAlert class="me-1.5 h-4 w-4" />
                                                {{ t('agents.guardrails.manage_filters') }}
                                            </Button>
                                        </div>
                                    </div>

                                    <div v-for="guard in GUARDS" :key="guard.key" class="space-y-2">
                                        <div
                                            class="flex items-start justify-between gap-4"
                                            :class="{ 'cursor-pointer': canManage }"
                                            @click="toggle(guard.key)"
                                        >
                                            <div>
                                                <div class="flex flex-wrap items-center gap-2">
                                                    <Label class="pointer-events-none text-sm font-medium">{{ t(guard.label) }}</Label>
                                                    <AiCheckBadge />
                                                </div>
                                                <p class="text-muted-foreground text-sm">{{ t(guard.help) }}</p>
                                            </div>
                                            <Switch v-model="form[guard.key]" :disabled="!canManage" class="pointer-events-none" />
                                        </div>
                                        <!-- What the guard does, and how easily it blocks. -->
                                        <div v-if="form[guard.key]" class="flex flex-wrap items-center gap-2">
                                            <Select v-model="form[guard.mode]" :disabled="!canManage">
                                                <SelectTrigger class="h-8 w-44" :aria-label="t('agents.guardrails.mode')">
                                                    <SelectValue />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem v-for="mode in MODES" :key="mode" :value="mode">{{
                                                        t(MODE_LABELS[mode])
                                                    }}</SelectItem>
                                                </SelectContent>
                                            </Select>
                                            <Select v-model="form[guard.sensitivity]" :disabled="!canManage">
                                                <SelectTrigger class="h-8 w-48" :aria-label="t('agents.guardrails.sensitivity')">
                                                    <SelectValue />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem v-for="level in SENSITIVITIES" :key="level" :value="level">{{
                                                        t(SENSITIVITY_LABELS[level])
                                                    }}</SelectItem>
                                                </SelectContent>
                                            </Select>
                                        </div>
                                    </div>
                                    <p class="text-muted-foreground text-xs">{{ t('agents.guardrails.mode_help') }}</p>

                                    <!-- Blocked topics -->
                                    <div class="space-y-1.5 pt-2">
                                        <div class="flex flex-wrap items-center justify-between gap-2">
                                            <div class="flex flex-wrap items-center gap-2">
                                                <Label>{{ t('agents.guardrails.blocked_topics') }}</Label>
                                                <AiCheckBadge />
                                            </div>
                                            <Select v-model="form.topics_mode" :disabled="!canManage">
                                                <SelectTrigger class="h-8 w-44" :aria-label="t('agents.guardrails.mode')">
                                                    <SelectValue />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem v-for="mode in MODES" :key="mode" :value="mode">{{
                                                        t(MODE_LABELS[mode])
                                                    }}</SelectItem>
                                                </SelectContent>
                                            </Select>
                                        </div>
                                        <div class="flex flex-wrap items-center gap-2">
                                            <Badge
                                                v-for="topic in form.blocked_topics"
                                                :key="topic.name"
                                                variant="secondary"
                                                class="gap-1 font-normal"
                                            >
                                                <UserRound v-if="topic.action === 'handoff'" class="h-3 w-3" />
                                                <MessageSquareOff v-else-if="topic.action === 'reply'" class="h-3 w-3" />
                                                {{ topic.name }}
                                            </Badge>
                                            <span v-if="!form.blocked_topics.length" class="text-muted-foreground text-sm">{{
                                                t('agents.guardrails.no_blocked_topics')
                                            }}</span>
                                            <Button
                                                v-if="canManage"
                                                type="button"
                                                variant="link"
                                                class="h-auto p-0 text-sm"
                                                @click="topicsOpen = true"
                                            >
                                                <Ban class="me-1.5 h-4 w-4" />
                                                {{ t('agents.guardrails.manage_blocked_topics') }}
                                            </Button>
                                        </div>
                                        <p class="text-muted-foreground text-xs">
                                            {{ t('agents.guardrails.blocked_topics_help') }}
                                        </p>
                                    </div>

                                    <!-- Allowed scope -->
                                    <div class="space-y-1.5">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <Label for="scope">{{ t('agents.guardrails.allowed_scope') }}</Label>
                                            <AiCheckBadge />
                                        </div>
                                        <div class="relative">
                                            <Target class="text-muted-foreground absolute start-3 top-3 h-4 w-4" />
                                            <Textarea
                                                id="scope"
                                                v-model="form.allowed_scope"
                                                :disabled="!canManage"
                                                rows="2"
                                                class="ps-9"
                                                :placeholder="t('agents.guardrails.allowed_scope_placeholder')"
                                            />
                                        </div>
                                        <p class="text-muted-foreground text-xs">{{ t('agents.guardrails.allowed_scope_help') }}</p>
                                    </div>

                                    <!-- Refusal message -->
                                    <div class="space-y-1.5">
                                        <Label for="refusal">{{ t('agents.guardrails.refusal_message') }}</Label>
                                        <div class="relative">
                                            <MessageSquareOff class="text-muted-foreground absolute start-3 top-1/2 h-4 w-4 -translate-y-1/2" />
                                            <Input
                                                id="refusal"
                                                v-model="form.refusal_message"
                                                :disabled="!canManage"
                                                class="ps-9"
                                                :placeholder="t('agents.guardrails.refusal_message_placeholder')"
                                            />
                                        </div>
                                        <p class="text-muted-foreground text-xs">{{ t('agents.guardrails.refusal_message_help') }}</p>
                                    </div>

                                    <!-- Hold answers until checked -->
                                    <div
                                        class="flex items-start justify-between gap-4 pt-2"
                                        :class="{ 'cursor-pointer': canManage }"
                                        @click="toggle('check_before_showing')"
                                    >
                                        <div>
                                            <Label class="pointer-events-none text-sm font-medium">{{
                                                t('agents.guardrails.check_before_showing')
                                            }}</Label>
                                            <p class="text-muted-foreground text-sm">{{ t('agents.guardrails.check_before_showing_help') }}</p>
                                        </div>
                                        <Switch v-model="form.check_before_showing" :disabled="!canManage" class="pointer-events-none" />
                                    </div>

                                    <!-- Alert emails -->
                                    <div
                                        class="flex items-start justify-between gap-4"
                                        :class="{ 'cursor-pointer': canManage }"
                                        @click="toggle('alert_owner')"
                                    >
                                        <div>
                                            <Label class="pointer-events-none text-sm font-medium">{{ t('agents.guardrails.alert_owner') }}</Label>
                                            <p class="text-muted-foreground text-sm">{{ t('agents.guardrails.alert_owner_help') }}</p>
                                        </div>
                                        <Switch v-model="form.alert_owner" :disabled="!canManage" class="pointer-events-none" />
                                    </div>
                                </div>

                                <div v-if="canManage" class="flex justify-end">
                                    <Button :disabled="form.processing" @click="save">
                                        <LoaderCircle v-if="form.processing" class="me-2 h-4 w-4 animate-spin" />
                                        <Save v-else class="me-2 h-4 w-4" />
                                        {{ t('agents.guardrails.save') }}
                                    </Button>
                                </div>
                            </CardContent>
                        </Card>
                    </TabsContent>

                    <!-- Test a message with the Configuration tab's settings, saved or not.
                         With guardrails off the live chat runs no guards, so a test
                         would mislead: say so instead. -->
                    <TabsContent v-if="canManage" value="test" class="mt-6">
                        <GuardrailTestCard v-if="form.enabled" :agent-id="props.agent.external_id" :settings="form" />
                        <Card v-else>
                            <CardContent class="text-muted-foreground flex items-center gap-2 text-sm">
                                <ShieldOff class="h-4 w-4 shrink-0" />
                                {{ t('agents.guardrails.test_off') }}
                            </CardContent>
                        </Card>
                    </TabsContent>

                    <!-- Trigger log -->
                    <TabsContent value="activity" class="mt-6">
                        <GuardrailTriggerLog
                            :agent="props.agent"
                            :triggers="props.triggers"
                            :guard-types="props.guardTypes"
                            :outcomes="props.outcomes"
                            :filters="props.filters"
                            :can-view-chats="canViewChats"
                        />
                    </TabsContent>

                    <!-- Who cannot chat with the agent; loaded each time the tab opens -->
                    <TabsContent value="blocked" class="mt-6">
                        <BlockedList :agent-id="props.agent.external_id" />
                    </TabsContent>
                </Tabs>
            </div>
        </div>

        <!-- Manage PII filters -->
        <PiiFiltersDialog
            v-model:open="filtersOpen"
            v-model="form.filters"
            v-model:actions="form.pii_actions"
            v-model:patterns="form.custom_patterns"
            v-model:allowlist="form.allowlist"
            :available-filters="props.availableFilters"
            :can-manage="canManage"
        />

        <!-- Manage blocked topics -->
        <BlockedTopicsDialog v-model:open="topicsOpen" v-model="form.blocked_topics" />
    </AgentLayout>
</template>
