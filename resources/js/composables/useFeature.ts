import { usePage } from '@inertiajs/vue3';

/**
 * Read the install-wide switches for unfinished features shared from the
 * server (`chatvia.features`, e.g. `hasFeature('youtube_sources')`).
 */
export function useFeature() {
    const page = usePage();

    const hasFeature = (feature: string): boolean => {
        return page.props.features?.[feature] === true;
    };

    return { hasFeature };
}
