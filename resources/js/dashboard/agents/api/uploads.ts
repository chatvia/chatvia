/**
 * File analysis uploads used by the knowledge-base file picker
 * (`useFileUploader`). The caller supplies the endpoint, because each source
 * type has its own analyze route; this module only owns the request shape.
 */
import { post } from '@/shared/api/http';
import type { AxiosProgressEvent } from 'axios';

/** How long an analysis upload may take before it is reported as timed out. */
const ANALYZE_TIMEOUT_MS = 30000;

export function analyzeUploadedFile<T>(url: string, file: File, onUploadProgress: (event: AxiosProgressEvent) => void): Promise<T> {
    const form = new FormData();
    form.append('file', file);

    return post<T>(url, form, {
        headers: { 'Content-Type': 'multipart/form-data' },
        timeout: ANALYZE_TIMEOUT_MS,
        onUploadProgress,
    });
}
