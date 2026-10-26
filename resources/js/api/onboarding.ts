/**
 * The dashboard tour API: saves how far the signed-in person got, so the tour
 * picks up on the next page and stays away once done. Null starts it again.
 */
import { update } from '@/routes/account/onboarding-tour';
import { patch } from './http';

export type OnboardingTourStage = App.Identity.Workspace.Enums.OnboardingTourStage;

export function saveOnboardingTourStage(stage: OnboardingTourStage | null): Promise<void> {
    return patch(update.url(), { stage });
}
