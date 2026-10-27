{{--
    The admin panel's logo: the ChatVia.ai mark on an ink tile (as in the
    favicon), the app's name (Settings → Platform) and an "Admin" tag. The mark uses the same paths
    as resources/js/components/AppLogoIcon.vue. Styled by the panel theme
    (resources/css/filament/admin/theme.css), so it follows dark mode.
--}}
<span class="cv-brand">
    <span class="cv-brand__mark" aria-hidden="true">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64" fill="currentColor">
            <path d="M44.6 18.4A18 18 0 1 0 44.6 41.6" fill="none" stroke="currentColor" stroke-width="10" stroke-linecap="round" />
            <path d="M23.5 45.5 21.8 52.2C17.6 55.2 12.2 57 6.4 57.6 5.2 57.7 4.6 56.4 5.4 55.6 8.8 51.8 10.6 47 11 42L16.5 38.5Z" />
            <circle cx="53" cy="30" r="6" />
        </svg>
    </span>
    <span class="cv-brand__name">{{ config('app.name') }}</span>
    <span class="cv-brand__tag">Admin</span>
</span>
