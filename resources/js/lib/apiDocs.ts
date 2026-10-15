import { ui as apiDocs } from '@/routes/scramble/docs';

/** The built-in API reference, opened at `operation` when one is given. */
export function apiDocsUrl(operation?: string): string {
    return operation ? `${apiDocs.url()}#/operations/${operation}` : apiDocs.url();
}
