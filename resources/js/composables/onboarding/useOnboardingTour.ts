import { saveOnboardingTourStage, type OnboardingTourStage } from '@/api/onboarding';
import { dashboard } from '@/routes';
import { router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

/**
 * The person's place in the dashboard tour (shared as `onboardingTour`): null
 * while the admin has it off, else the stage (null = not started, `agent` =
 * the agent part is next, `done`). Moving on updates the page props at once
 * and saves in the background, so the next page already knows.
 */
export function useOnboardingTour() {
    const page = usePage();

    const enabled = computed(() => page.props.onboardingTour !== null && page.props.onboardingTour !== undefined);
    const stage = computed<OnboardingTourStage | null>(() => page.props.onboardingTour?.stage ?? null);

    function moveTo(next: OnboardingTourStage | null): Promise<void> {
        if (page.props.onboardingTour) {
            page.props.onboardingTour.stage = next;
        }

        return saveOnboardingTourStage(next).catch((error: unknown) => console.error('Saving the tour step failed', error));
    }

    /** "Take the tour" in the user menu: back to the start, on the dashboard. */
    async function restart(): Promise<void> {
        await moveTo(null);
        router.visit(dashboard.url());
    }

    return { enabled, stage, moveTo, restart };
}
