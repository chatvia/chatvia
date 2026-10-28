/**
 * What the file uploader accepts on each files page (documents, PDF, Word,
 * Markdown, audio, video): file extensions, size limit, title and description.
 * The extensions are those of the file types this install accepts. Every
 * upload goes to the agent's upload endpoint.
 */
import { sourceFileUploadUrl } from '@/dashboard/agents/api/fileSources';
import { type KnowledgeFileType, useKnowledgeFileTypes } from '@/dashboard/agents/composables/sources/files/useKnowledgeFileTypes';
import type { FileAnalyzedData, FileUploaderConfig, UploadingFileData } from '@/dashboard/agents/composables/useFileUploader';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

const MAX_FILES = 20;

interface PageAccept {
    types: KnowledgeFileType[];
    /** Only these extensions of the types (the Markdown page takes no .txt). */
    only?: string[];
    maxSizeMb: number;
}

/** Documents, ebooks and images; 50 MB is the OCR limit per file. */
const DEFAULT_ACCEPT: PageAccept = {
    types: ['pdf', 'word', 'spreadsheet', 'presentation', 'text', 'ebook', 'image'],
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

    const config = computed<FileUploaderConfig>(() => ({
        maxFiles: MAX_FILES,
        acceptedTypes: extensions.value.map((extension) => `.${extension}`).join(','),
        maxFileSize: accept.maxSizeMb,
        analyzeEndpoint: () => sourceFileUploadUrl(agentId),
        onFileAnalyzed: (file: UploadingFileData, _data: FileAnalyzedData) => onUploaded(file),
    }));

    const title = computed(() => t((fileType && TITLE_KEYS[fileType]) || 'agents.sources.files.upload_title_default'));
    const description = computed(() =>
        acceptsFiles.value
            ? t((fileType && DESCRIPTION_KEYS[fileType]) || 'agents.sources.files.uploader_description_default', {
                  formats: extensions.value.map((extension) => extension.toUpperCase()).join(', '),
                  size: accept.maxSizeMb,
              })
            : t('agents.sources.files.uploads_off'),
    );

    return { config, title, description, acceptsFiles };
}
