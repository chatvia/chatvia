/**
 * Submits one Setup section's Inertia form with the save behaviour every
 * section shares: the section's "saving" flag, a success or error toast and,
 * when given, a follow-up such as reloading the live preview.
 */
import type { InertiaFormProps } from '@inertiajs/vue3';
import type { Ref } from 'vue';
import { toast } from 'vue-sonner';

type SubmittableForm = Pick<InertiaFormProps<object>, 'post' | 'patch'>;

/** What every Setup section composable needs: the agent id for its routes and a way to reload the preview after a save. */
export interface SetupSectionDeps {
    agentId: () => string;
    reloadPreview: () => void;
}

export interface SetupSubmitOptions {
    saving: Ref<boolean>;
    /** Toast text. A function when the text depends on the form state after the request. */
    success: string | (() => string);
    error: string | (() => string);
    /** Runs after the success toast. */
    onSuccess?: () => void;
}

const resolve = (text: string | (() => string)): string => (typeof text === 'function' ? text() : text);

export function submitSetupForm(form: SubmittableForm, method: 'post' | 'patch', url: string, options: SetupSubmitOptions): void {
    options.saving.value = true;
    form[method](url, {
        preserveScroll: true,
        onSuccess: () => {
            toast.success(resolve(options.success));
            options.onSuccess?.();
        },
        onError: () => toast.error(resolve(options.error)),
        onFinish: () => (options.saving.value = false),
    });
}
