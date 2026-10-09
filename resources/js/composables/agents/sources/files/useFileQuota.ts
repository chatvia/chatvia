/**
 * The plan quota a files page works against: minutes for audio and video,
 * pages for everything else. Adds what is being uploaded right now to what the
 * server already counted, and tells whether training would go over the limit.
 */
import type { SourceFile } from '@/api/fileSources';
import { fetchUsage, type UsageStats } from '@/api/usage';
import type { UploadingFileData } from '@/composables/useFileUploader';
import { computed, ref, type Ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { toast } from 'vue-sonner';
import { usesMinutesQuota } from './useFileFormatting';

interface FileUsage {
    pages: UsageStats;
    minutes?: UsageStats;
}

/** Sums the length (pages or minutes) of the files that match `predicate`. */
const sumLength = <T>(items: T[], length: (item: T) => number, predicate: (item: T) => boolean): number =>
    items.reduce((total, item) => (predicate(item) ? total + length(item) : total), 0);

export function useFileQuota(fileType: string | undefined, uploadingFiles: Ref<UploadingFileData[]>, uploadedFiles: Ref<SourceFile[]>) {
    const { t } = useI18n();

    const usage = ref<FileUsage>({ pages: { limit: 0, used: 0, remaining: 0 } });
    const isLoadingUsage = ref(false);

    /** Audio and video pages count minutes; every other page counts pages. */
    const countsMinutes = fileType === 'audio' || fileType === 'video';

    async function refreshUsage(): Promise<void> {
        isLoadingUsage.value = true;
        try {
            usage.value = await fetchUsage();
        } catch {
            toast.error(t('agents.sources.files.usage_load_failed'));
        } finally {
            isLoadingUsage.value = false;
        }
    }

    const uploadingLength = (file: UploadingFileData): number => file.stats?.length || 0;
    const uploadedLength = (file: SourceFile): number => file.length || 0;

    /** Minutes / pages of the files still uploading (not counted by the server yet). */
    const uploadingMinutes = computed(() => sumLength(uploadingFiles.value, uploadingLength, (file) => usesMinutesQuota(file.stats)));
    const uploadingPages = computed(() => sumLength(uploadingFiles.value, uploadingLength, (file) => !usesMinutesQuota(file.stats)));

    /** Minutes / pages of everything waiting for training: uploaded plus still uploading. */
    const pendingMinutes = computed(() => sumLength(uploadedFiles.value, uploadedLength, (file) => usesMinutesQuota(file)) + uploadingMinutes.value);
    const pendingPages = computed(() => sumLength(uploadedFiles.value, uploadedLength, (file) => !usesMinutesQuota(file)) + uploadingPages.value);

    /** Whether the files still uploading would not fit in what is left of the quota. */
    const wouldExceedQuota = computed(() =>
        countsMinutes ? uploadingMinutes.value > (usage.value.minutes?.remaining ?? 0) : uploadingPages.value > usage.value.pages.remaining,
    );

    /** The figures for the uploader's quota bar. */
    const quotaUsed = computed(() =>
        countsMinutes ? (usage.value.minutes?.used || 0) + uploadingMinutes.value : usage.value.pages.used + uploadingPages.value,
    );
    const quotaLimit = computed(() => (countsMinutes ? usage.value.minutes?.limit || 0 : usage.value.pages.limit));
    const quotaLabel = computed(() => (countsMinutes ? t('agents.sources.files.quota_minutes') : t('agents.sources.files.quota_pages')));

    return { isLoadingUsage, refreshUsage, pendingMinutes, pendingPages, wouldExceedQuota, quotaUsed, quotaLimit, quotaLabel };
}
