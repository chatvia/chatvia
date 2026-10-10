/**
 * Typed HTTP helpers shared by every `resources/js/api/*` module.
 *
 * Components should not call axios directly. Each feature area gets a small
 * module in `resources/js/api/` (e.g. `api/topics.ts`) whose functions take
 * typed arguments, build the URL with the Wayfinder route functions in
 * `@/routes` (`index.url(agentId)`), and return the typed response body. That keeps URLs, payload shapes and error handling in one
 * place and lets vue-tsc check what the pages use.
 *
 * app.ts makes axios send the page's CSRF token (utils/csrf.ts), and axios sends
 * an `Accept: application/json` header for same-origin requests, so Laravel
 * answers with JSON; these helpers only add typing and unwrap `response.data`.
 */
import axios, { type AxiosRequestConfig, isAxiosError } from 'axios';

export async function get<T>(url: string, config?: AxiosRequestConfig): Promise<T> {
    const response = await axios.get<T>(url, config);
    return response.data;
}

export async function post<T = void, B = unknown>(url: string, body?: B, config?: AxiosRequestConfig): Promise<T> {
    const response = await axios.post<T>(url, body, config);
    return response.data;
}

export async function put<T = void, B = unknown>(url: string, body?: B, config?: AxiosRequestConfig): Promise<T> {
    const response = await axios.put<T>(url, body, config);
    return response.data;
}

export async function patch<T = void, B = unknown>(url: string, body?: B, config?: AxiosRequestConfig): Promise<T> {
    const response = await axios.patch<T>(url, body, config);
    return response.data;
}

export async function del<T = void>(url: string, config?: AxiosRequestConfig): Promise<T> {
    const response = await axios.delete<T>(url, config);
    return response.data;
}

/**
 * The human-readable message of a failed request: the server's `message`
 * (Laravel validation and domain errors), else the given fallback.
 */
export function errorMessage(error: unknown, fallback: string): string {
    if (isAxiosError<{ message?: string }>(error)) {
        return error.response?.data?.message ?? fallback;
    }

    return fallback;
}

/** The first validation error per field from a 422 response, if any. */
export function validationErrors(error: unknown): Record<string, string> {
    if (!isAxiosError<{ errors?: Record<string, string[]> }>(error) || error.response?.status !== 422) {
        return {};
    }

    const errors = error.response.data?.errors ?? {};

    return Object.fromEntries(Object.entries(errors).map(([field, messages]) => [field, messages[0] ?? '']));
}
