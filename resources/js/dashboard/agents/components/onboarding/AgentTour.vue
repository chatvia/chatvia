<script setup lang="ts">
/**
 * The second part of the tour, on the first agent page opened after the
 * dashboard part (usually the Setup page of the agent just created): the
 * setup sections, the three surfaces, the live preview, then the sidebar's
 * knowledge, Playground and Deploy. Stops that are not on the page are left
 * out. Finishing or closing it ends the tour.
 */
import OnboardingTour, { type TourStop } from '@/dashboard/components/onboarding/OnboardingTour.vue';
import { useOnboardingTour } from '@/dashboard/composables/onboarding/useOnboardingTour';
import { computed, onMounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();
const { stage, moveTo } = useOnboardingTour();

const tour = ref<InstanceType<typeof OnboardingTour> | null>(null);

const stops = computed<TourStop[]>(() => [
    {
        target: 'agent-setup-sections',
        title: t('onboarding.agent.sections_title'),
        description: t('onboarding.agent.sections_text'),
        placement: 'end',
    },
    { target: 'agent-setup-surfaces', title: t('onboarding.agent.surfaces_title'), description: t('onboarding.agent.surfaces_text') },
    { target: 'agent-setup-preview', title: t('onboarding.agent.preview_title'), description: t('onboarding.agent.preview_text'), placement: 'top' },
    {
        target: 'agent-nav-knowledge',
        title: t('onboarding.agent.knowledge_title'),
        description: t('onboarding.agent.knowledge_text'),
        placement: 'end',
    },
    {
        target: 'agent-nav-playground',
        title: t('onboarding.agent.playground_title'),
        description: t('onboarding.agent.playground_text'),
        placement: 'end',
    },
    { target: 'agent-nav-deploy', title: t('onboarding.agent.deploy_title'), description: t('onboarding.agent.deploy_text'), placement: 'end' },
]);

onMounted(() => {
    if (stage.value === 'agent') {
        // Let the sidebar and the setup panels lay out first.
        setTimeout(() => tour.value?.start(), 400);
    }
});

function done(): void {
    void moveTo('done');
}
</script>

<template>
    <OnboardingTour ref="tour" :stops="stops" @finish="done" @skip="done" />
</template>
