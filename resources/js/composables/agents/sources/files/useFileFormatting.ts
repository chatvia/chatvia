/**
 * Display helpers for knowledge files: the short type badge ("PDF", "Word"),
 * a human file size, and the file's length — in minutes for audio and video,
 * in pages for everything else.
 */
import { useI18n } from 'vue-i18n';

/** Just the fields that tell a file's kind and length. */
export interface FileKind {
    type?: string;
    mime_type?: string;
    length?: number;
}

const TYPE_LABELS: Record<string, string> = {
    'application/pdf': 'PDF',
    'application/msword': 'Word',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document': 'Word',
};

const SIZE_UNITS = ['B', 'KB', 'MB', 'GB'];

/** Audio and video use the plan's minutes; every other file uses pages. */
export function usesMinutesQuota(file: FileKind | null | undefined): boolean {
    if (!file) return false;
    if (file.type === 'audio' || file.type === 'video') return true;

    const mimeType = file.mime_type || '';
    return mimeType.startsWith('audio/') || mimeType.startsWith('video/');
}

export function formatFileSize(sizeInBytes: number): string {
    let size = sizeInBytes;
    let unitIndex = 0;

    while (size >= 1024 && unitIndex < SIZE_UNITS.length - 1) {
        size /= 1024;
        unitIndex++;
    }

    return `${size.toFixed(1)} ${SIZE_UNITS[unitIndex]}`;
}

export function useFileFormatting() {
    const { t } = useI18n();

    const formatFileType = (mimeType: string): string =>
        TYPE_LABELS[mimeType] || mimeType.split('/')[1]?.toUpperCase() || t('agents.sources.files.type_unknown');

    /** "12 pages" / "3 minutes", or '' when the length is not known yet. */
    const formatFileLength = (file: FileKind): string => {
        if (!file.length) return '';

        return usesMinutesQuota(file) ? t('agents.sources.files.minutes_count', file.length) : t('agents.sources.files.pages_count', file.length);
    };

    return { formatFileType, formatFileSize, formatFileLength };
}
