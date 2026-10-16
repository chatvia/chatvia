import { describe, expect, it } from 'vitest';
import { formatFileSize } from './fileSize';

describe('formatFileSize', () => {
    it('shows bytes, kilobytes and megabytes the way an attachment chip needs', () => {
        expect(formatFileSize(512)).toBe('512 B');
        expect(formatFileSize(1023)).toBe('1023 B');
        expect(formatFileSize(1024)).toBe('1 KB');
        expect(formatFileSize(86_000)).toBe('84 KB');
        expect(formatFileSize(1024 * 1024)).toBe('1.0 MB');
        expect(formatFileSize(2_516_582)).toBe('2.4 MB');
    });

    it('shows no size for a zero or missing one', () => {
        expect(formatFileSize(0)).toBe('');
        expect(formatFileSize(null)).toBe('');
        expect(formatFileSize(undefined)).toBe('');
    });
});
