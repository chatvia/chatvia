/**
 * Compact file sizes for chat attachment chips ("512 B", "84 KB", "2.4 MB").
 * Returns an empty string for a zero or missing size, so the chip shows no size.
 */
export function formatFileSize(bytes: number | null | undefined): string {
    if (!bytes) return '';
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1024 * 1024) return `${Math.round(bytes / 1024)} KB`;

    return `${(bytes / 1024 / 1024).toFixed(1)} MB`;
}
