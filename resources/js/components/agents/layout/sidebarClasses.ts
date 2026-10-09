/**
 * Tailwind classes shared by the agent sidebar's links: the active page is
 * shown inverted (dark on light, light on dark), icon included.
 */
export const ACTIVE_LINK_CLASS =
    'data-[active=true]:bg-neutral-900 data-[active=true]:font-semibold data-[active=true]:text-white dark:data-[active=true]:bg-neutral-100 dark:data-[active=true]:text-neutral-900 [&[data-active=true]_svg]:text-white dark:[&[data-active=true]_svg]:text-neutral-900';
