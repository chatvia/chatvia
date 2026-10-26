<script setup lang="ts">
/**
 * The first part of the tour, on the dashboard: a welcome window that says
 * what the tour shows and how to use it, then the header (agents, inbox, ask
 * your data, teams, notifications, the user menu) and, for someone who may
 * create agents, the Create agent button. Its last step opens the create
 * dialog and leaves the agent part for the new agent's page.
 */
import OnboardingTour, { type TourStop } from '@/components/onboarding/OnboardingTour.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { useOnboardingTour } from '@/composables/onboarding/useOnboardingTour';
import { ArrowLeftRight, Compass, Menu, X } from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps<{ canCreateAgent: boolean }>();

const emit = defineEmits<{ 'create-agent': [] }>();

const { t } = useI18n();
const { enabled, stage, moveTo } = useOnboardingTour();

const welcomeOpen = ref(false);
const tour = ref<InstanceType<typeof OnboardingTour> | null>(null);

const stops = computed<TourStop[]>(() => {
    const list: TourStop[] = [
        { target: 'nav-agents', title: t('onboarding.dashboard.agents_title'), description: t('onboarding.dashboard.agents_text') },
        { target: 'nav-inbox', title: t('onboarding.dashboard.inbox_title'), description: t('onboarding.dashboard.inbox_text') },
        { target: 'nav-ask-your-data', title: t('onboarding.dashboard.ask_title'), description: t('onboarding.dashboard.ask_text') },
        { target: 'team-switcher', title: t('onboarding.dashboard.teams_title'), description: t('onboarding.dashboard.teams_text') },
        { target: 'notifications', title: t('onboarding.dashboard.notifications_title'), description: t('onboarding.dashboard.notifications_text') },
        { target: 'user-menu', title: t('onboarding.dashboard.menu_title'), description: t('onboarding.dashboard.menu_text') },
    ];

    if (props.canCreateAgent) {
        list.push({
            target: 'create-agent',
            title: t('onboarding.dashboard.create_title'),
            description: t('onboarding.dashboard.create_text'),
            nextLabel: t('onboarding.dashboard.create_button'),
            clickable: true,
        });
    }

    return list;
});

onMounted(() => {
    if (enabled.value && stage.value === null) {
        welcomeOpen.value = true;
    }
});

function startTour(): void {
    welcomeOpen.value = false;
    // After the dialog has closed and given the page back.
    setTimeout(() => tour.value?.start(), 200);
}

function notNow(): void {
    welcomeOpen.value = false;
    void moveTo('done');
}

function onWelcomeOpenChange(open: boolean): void {
    if (!open && welcomeOpen.value) notNow();
}

function onFinish(): void {
    if (props.canCreateAgent) {
        void moveTo('agent');
        emit('create-agent');
    } else {
        void moveTo('done');
    }
}

function onSkip(): void {
    void moveTo('done');
}

/** The person clicked Create agent themselves while the tour was on it. */
function createAgentClicked(): void {
    if (tour.value?.active) {
        tour.value.stop();
        void moveTo('agent');
    }
}

defineExpose({ createAgentClicked });
</script>

<template>
    <Dialog :open="welcomeOpen" @update:open="onWelcomeOpenChange">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>{{ t('onboarding.welcome.title') }}</DialogTitle>
                <DialogDescription>{{ t('onboarding.welcome.description') }}</DialogDescription>
            </DialogHeader>
            <div class="space-y-3 text-sm">
                <p class="font-medium">{{ t('onboarding.welcome.how_title') }}</p>
                <ul class="text-muted-foreground space-y-2">
                    <li class="flex gap-2">
                        <ArrowLeftRight class="mt-0.5 h-4 w-4 shrink-0" />
                        <span>{{ t('onboarding.welcome.how_move') }}</span>
                    </li>
                    <li class="flex gap-2">
                        <X class="mt-0.5 h-4 w-4 shrink-0" />
                        <span>{{ t('onboarding.welcome.how_skip') }}</span>
                    </li>
                    <li class="flex gap-2">
                        <Menu class="mt-0.5 h-4 w-4 shrink-0" />
                        <span>{{ t('onboarding.welcome.how_again') }}</span>
                    </li>
                </ul>
            </div>
            <DialogFooter class="gap-2">
                <Button variant="outline" @click="notNow">{{ t('onboarding.welcome.not_now') }}</Button>
                <Button class="gap-2" @click="startTour">
                    <Compass class="h-4 w-4" />
                    {{ t('onboarding.welcome.start') }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>

    <OnboardingTour ref="tour" :stops="stops" @finish="onFinish" @skip="onSkip" />
</template>
