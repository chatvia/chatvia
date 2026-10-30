/**
 * What the file uploader accepts on each files page (documents, PDF, Word,
 * Markdown, audio, video): file extensions, size limit, title and description.
 * The extensions are those of the file types this install accepts. Every
 * upload goes to the agent's upload endpoint; the Files page also takes a
 * zip archive, which goes to the archive endpoint and is unpacked there.
 */
import { isArchiveFile, sourceArchiveUploadUrl, sourceFileUploadUrl } from '@/dashboard/agents/api/fileSources';
import { type KnowledgeFileType, useKnowledgeFileTypes } from '@/dashboard/agents/composables/sources/files/useKnowledgeFileTypes';
import {
    type FileAnalyzedData,
    type FileUploaderConfig,
    isArchiveUploadResult,
    type UploadingFileData,
} from '@/dashboard/agents/composables/useFileUploader';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { toast } from 'vue-sonner';

const MAX_FILES = 20;

interface PageAccept {
    types: KnowledgeFileType[];
    /** Only these extensions of the types (the Markdown page takes no .txt). */
    only?: string[];
    /** Also a zip archive, unpacked on the server; only files of the accepted types inside it are kept. */
    archives?: boolean;
    maxSizeMb: number;
}

/** Documents, ebooks and images, loose or in a zip; 50 MB is the OCR limit per file. */
const DEFAULT_ACCEPT: PageAccept = {
    types: ['pdf', 'word', 'spreadsheet', 'presentation', 'text', 'ebook', 'image'],
    archives: true,
    maxSizeMb: 50,
};

const ACCEPT_BY_TYPE: Record<string, PageAccept> = {
    pdf: { types: ['pdf'], maxSizeMb: 50 },
    word: { types: ['word'], maxSizeMb: 50 },
    markdown: { types: ['text'], only: ['md', 'markdown'], maxSizeMb: 50 },
    audio: { types: ['audio'], maxSizeMb: 200 },
    video: { types: ['video'], maxSizeMb: 200 },
};

const TITLE_KEYS: Record<string, string> = {
    pdf: 'agents.sources.files.upload_title_pdf',
    word: 'agents.sources.files.upload_title_word',
    markdown: 'agents.sources.files.upload_title_markdown',
    audio: 'agents.sources.files.upload_title_audio',
    video: 'agents.sources.files.upload_title_video',
};

const DESCRIPTION_KEYS: Record<string, string> = {
    audio: 'agents.sources.files.uploader_description_audio',
    video: 'agents.sources.files.uploader_description_video',
};

/**
 * @param onUploaded runs after each file reached the server (it is then
 *   removed from the uploader and shows up as "ready to train").
 */
export function useFileUploadConfig(agentId: string, fileType: string | undefined, onUploaded: (file: UploadingFileData) => void) {
    const { t } = useI18n();
    const { extensionsOf } = useKnowledgeFileTypes();

    const accept = (fileType && ACCEPT_BY_TYPE[fileType]) || DEFAULT_ACCEPT;

    const extensions = computed(() => extensionsOf(accept.types).filter((extension) => !accept.only || accept.only.includes(extension)));

    /** False when the admin turned off every file type of this page. */
    const acceptsFiles = computed(() => extensions.value.length > 0);

    const acceptsArchives = computed(() => accept.archives === true && acceptsFiles.value);

    /** An archive row leaves the uploader like a file; what it held is told in a toast. */
    const onAnalyzed = (file: UploadingFileData, data: FileAnalyzedData) => {
        onUploaded(file);

        if (!isArchiveUploadResult(data)) return;

        const name = file.file.name;

        if (data.uploaded === 0) {
            toast.warning(t('agents.sources.files.archive_empty', { name }));
            return;
        }

        toast.success(t('agents.sources.files.archive_uploaded', { count: data.uploaded, name }, { plural: data.uploaded }), {
            description:
                data.skipped.length > 0
                    ? t(
                          'agents.sources.files.archive_skipped',
                          { count: data.skipped.length, names: data.skipped.join(', ') },
                          { plural: data.skipped.length },
                      )
                    : undefined,
            duration: data.skipped.length > 0 ? 10000 : undefined,
        });
    };

    const config = computed<FileUploaderConfig>(() => ({
        maxFiles: MAX_FILES,
        acceptedTypes: [...extensions.value, ...(acceptsArchives.value ? ['zip'] : [])].map((extension) => `.${extension}`).join(','),
        maxFileSize: accept.maxSizeMb,
        analyzeEndpoint: (file: File) =>
            acceptsArchives.value && isArchiveFile(file) ? sourceArchiveUploadUrl(agentId) : sourceFileUploadUrl(agentId),
        onFileAnalyzed: onAnalyzed,
    }));

    const title = computed(() => t((fileType && TITLE_KEYS[fileType]) || 'agents.sources.files.upload_title_default'));
    const description = computed(() =>
        acceptsFiles.value
            ? t((fileType && DESCRIPTION_KEYS[fileType]) || 'agents.sources.files.uploader_description_default', {
                  formats: [...extensions.value, ...(acceptsArchives.value ? ['zip'] : [])].map((extension) => extension.toUpperCase()).join(', '),
                  size: accept.maxSizeMb,
              })
            : t('agents.sources.files.uploads_off'),
    );

    /** A second line under the description: what happens to a ZIP, on the pages that take one. */
    const note = computed(() => (acceptsArchives.value ? t('agents.sources.files.uploader_description_archive') : undefined));

    return { config, title, description, note, acceptsFiles };
}
