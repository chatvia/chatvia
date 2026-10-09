/**
 * Requests behind the signed-in user's own account: re-confirming the password
 * before a security-sensitive change, signing out, and reading the
 * authenticator-app setup (QR code, secret key, recovery codes).
 */
import { isAxiosError } from 'axios';

import { get, post } from './http';

/** The QR code for a pending authenticator-app setup. */
export interface TwoFactorQrCode {
    svg?: string;
    url?: string;
}

/** The authenticator secret, for typing into the app by hand. */
export interface TwoFactorSecretKey {
    secretKey?: string;
}

/**
 * The two-factor reads resolve on HTTP errors instead of rejecting: an expired
 * password confirmation (423) or a missing secret (404) just leaves the fields
 * empty, and the settings page falls back to its "nothing to show" state.
 */
const twoFactorReadConfig = {
    headers: { Accept: 'application/json' },
    validateStatus: () => true,
};

/** Re-confirm the current password; rejects with a 422 when it is wrong. */
export function confirmPassword(password: string): Promise<void> {
    return post(route('password.confirm.store'), { password });
}

/**
 * The message for a failed `confirmPassword()` that was a validation error
 * (HTTP 422): the password field error, else the server message, else the
 * fallback. Null when the request failed for any other reason.
 */
export function passwordConfirmationError(error: unknown, fallback: string): string | null {
    if (!isAxiosError<{ message?: string; errors?: { password?: string[] } }>(error) || error.response?.status !== 422) {
        return null;
    }

    return error.response.data.errors?.password?.[0] ?? error.response.data.message ?? fallback;
}

/** End the current session. */
export function logout(): Promise<void> {
    return post(route('logout'));
}

/** The QR code SVG for the pending authenticator setup. Fields are missing when the request failed. */
export function getTwoFactorQrCode(): Promise<TwoFactorQrCode> {
    return get<TwoFactorQrCode>(route('two-factor.qr-code'), twoFactorReadConfig);
}

/** The authenticator secret key. The field is missing when the request failed. */
export function getTwoFactorSecretKey(): Promise<TwoFactorSecretKey> {
    return get<TwoFactorSecretKey>(route('two-factor.secret-key'), twoFactorReadConfig);
}

/** The unused recovery codes; empty when there are none or the request failed. */
export async function getTwoFactorRecoveryCodes(): Promise<string[]> {
    const codes = await get<unknown>(route('two-factor.recovery-codes'), twoFactorReadConfig);

    return Array.isArray(codes) ? codes : [];
}
