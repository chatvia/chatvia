// Augment Inertia's `PageProps` so `$page.props.X` and `usePage().props.X`
// resolve through the project's existing `SharedData` shape. This lets us
// reference props in templates without per-call-site casts and keeps the
// canonical definition in `resources/js/types/index.d.ts`.

import type { Page } from '@inertiajs/core';
import type { SharedData } from './index';

declare module '@inertiajs/core' {
    // eslint-disable-next-line @typescript-eslint/no-empty-object-type
    interface PageProps extends SharedData {}
}

// `$page` is exposed by `@inertiajs/vue3` on every component instance. Declare
// it here so templates can reach `$page.props.X` without a `usePage()` import.
declare module '@vue/runtime-core' {
    interface ComponentCustomProperties {
        $page: Page<SharedData>;
    }
}

export {};
