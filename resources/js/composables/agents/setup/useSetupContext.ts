/**
 * Shares the Setup page's section state with its panel components. The page
 * creates every section composable once and provides them here, so form edits
 * survive switching sections and preview surfaces even though each panel
 * unmounts when hidden. Panels inject the context and bind to its forms.
 */
import { inject, provide, type ComputedRef, type InjectionKey } from 'vue';
import type { SetupBehavior } from './useSetupBehavior';
import type { SetupBranding } from './useSetupBranding';
import type { SetupBubbles } from './useSetupBubbles';
import type { SetupGeneralSettings } from './useSetupGeneralSettings';
import type { SetupLanguages } from './useSetupLanguages';
import type { SetupModel } from './useSetupModel';
import type { SetupPreview } from './useSetupPreview';
import type { SetupStarters } from './useSetupStarters';
import type { SetupText } from './useSetupText';

export interface SetupContext {
    /** True when the user may only view the setup (no `agent.setup` permission). */
    locked: ComputedRef<boolean>;
    preview: SetupPreview;
    text: SetupText;
    generalSettings: SetupGeneralSettings;
    languages: SetupLanguages;
    starters: SetupStarters;
    bubbles: SetupBubbles;
    model: SetupModel;
    branding: SetupBranding;
    behavior: SetupBehavior;
}

const SETUP_CONTEXT: InjectionKey<SetupContext> = Symbol('agent-setup');

export function provideSetupContext(context: SetupContext): void {
    provide(SETUP_CONTEXT, context);
}

export function useSetupContext(): SetupContext {
    const context = inject(SETUP_CONTEXT);
    if (!context) {
        throw new Error('useSetupContext() must be used inside the agent Setup page.');
    }
    return context;
}
