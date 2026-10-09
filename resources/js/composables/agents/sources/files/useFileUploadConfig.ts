/**
 * What the file uploader accepts on each files page (documents, PDF, Word,
 * Markdown, audio, video): file extensions, size limit, title and description.
 * Every upload goes to the agent's upload endpoint.
 */
import { sourceFileUploadUrl } from '@/api/fileSources';
import type { FileAnalyzedData, FileUploaderConfig, UploadingFileData } from '@/composables/useFileUploader';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

const MAX_FILES = 20;

/** Documents, ebooks and images; 50 MB is the OCR limit per file. */
const DEFAULT_ACCEPT = {
    types: '.pdf,.doc,.docx,.ppt,.pptx,.xlsx,.csv,.txt,.md,.markdown,.rtf,.epub,.fb2,.jpg,.jpeg,.png,.avif,.tiff,.tif,.gif,.heic,.heif,.bmp,.webp',
    maxSizeMb: 50,
};

const ACCEPT_BY_TYPE: Record<string, { types: string; maxSizeMb: number }> = {
    pdf: { types: '.pdf', maxSizeMb: 50 },
    word: { types: '.doc,.docx', maxSizeMb: 50 },
    markdown: { types: '.md,.markdown', maxSizeMb: 50 },
    audio: { types: '.mp3,.m4a,.wav,.ogg,.webm,.flac,.aac,.wma,.amr,.3gp', maxSizeMb: 200 },
    video: { types: '.mp4,.avi,.mov,.wmv,.flv,.webm,.mkv,.qt,.3gp', maxSizeMb: 200 },
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

    const accept = (fileType && ACCEPT_BY_TYPE[fileType]) || DEFAULT_ACCEPT;

    const config = computed<FileUploaderConfig>(() => ({
        maxFiles: MAX_FILES,
        acceptedTypes: accept.types,
        maxFileSize: accept.maxSizeMb,
        analyzeEndpoint: () => sourceFileUploadUrl(agentId),
        onFileAnalyzed: (file: UploadingFileData, _data: FileAnalyzedData) => onUploaded(file),
    }));

    const title = computed(() => t((fileType && TITLE_KEYS[fileType]) || 'agents.sources.files.upload_title_default'));
    const description = computed(() => t((fileType && DESCRIPTION_KEYS[fileType]) || 'agents.sources.files.uploader_description_default'));

    return { config, title, description };
}
