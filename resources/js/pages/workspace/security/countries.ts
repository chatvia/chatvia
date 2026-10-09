/**
 * Country display helpers for the workspace Security page: the localized name
 * for an ISO code (from the countries list the server sends) and its flag.
 * Codes the list doesn't know fall back to the upper-cased code.
 */
import { computed } from 'vue';

export interface Country {
    code: string;
    name: string;
}

/** Path of the bundled flag image for a lowercase ISO country code. */
export const flagUrl = (code: string): string => `/images/flags/${code.toLowerCase()}.svg`;

export function useCountryNames(countries: () => Country[]) {
    const countryMap = computed(() => new Map(countries().map((c) => [c.code, c.name])));

    const countryName = (code: string): string => countryMap.value.get(code) ?? code.toUpperCase();

    return { countryName, flagUrl };
}
