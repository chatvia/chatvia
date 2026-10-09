/**
 * Live training progress of the agent's files over Reverb
 * (`agents.{id}.files.progress` / `.FileIngestionProgressed`). Broadcasts can
 * arrive out of order, so a finished file never goes back and progress never
 * runs backwards. At 100 % the list reloads to pick up the final data.
 */
import { useEcho } from '@laravel/echo-vue';
import type { SourceFilesState } from './useSourceFiles';

/** Payload of `FileIngestionProgressed`. */
interface FileProgressBroadcast {
    file_id?: number;
    progress?: number;
}

/** Wait a moment after 100 % so the server has stored the final state. */
const RELOAD_DELAY_MS = 1000;

export function useFileIngestionProgress(agentId: string, sourceFiles: SourceFilesState) {
    function onProgress({ file_id: fileId, progress }: FileProgressBroadcast): void {
        if (!fileId || progress === undefined) return;

        const file = sourceFiles.findFile(fileId);
        if (!file || file.status === 'ingested') return;
        if (typeof file.progress === 'number' && progress < file.progress) return;

        sourceFiles.setProgress(fileId, progress);

        if (progress >= 100) {
            setTimeout(() => sourceFiles.fetchFiles(), RELOAD_DELAY_MS);
        }
    }

    useEcho<FileProgressBroadcast>(`agents.${agentId}.files.progress`, '.FileIngestionProgressed', onProgress);
}
