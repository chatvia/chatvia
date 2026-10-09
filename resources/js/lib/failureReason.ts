/**
 * The text to show after a failed request: the server's `message` when there
 * is one, otherwise the error's own message (e.g. "Network Error"), otherwise
 * an empty string. Used in toasts like "Could not delete: {error}".
 */
import { errorMessage } from '@/api/http';

export function failureReason(error: unknown): string {
    return errorMessage(error, error instanceof Error ? error.message : '');
}
