/**
 * The knowledge file types this install accepts, with their extensions. An
 * admin turns them on or off (Settings → Knowledge); the server shares them
 * on every page. The server checks every upload again.
 */
import type { SharedData } from '@/types';
import { usePage } from '@inertiajs/vue3';

export type KnowledgeFileType = Domain.Knowledge.Source.File.Enums.KnowledgeFileType;

export function useKnowledgeFileTypes() {
    const page = usePage<SharedData>();

    const isAccepted = (type: KnowledgeFileType): boolean => (page.props.knowledgeFileTypes?.[type]?.length ?? 0) > 0;

    /** The accepted extensions of the given types, without the dot. */
    const extensionsOf = (types: KnowledgeFileType[]): string[] => [...new Set(types.flatMap((type) => page.props.knowledgeFileTypes?.[type] ?? []))];

    return { isAccepted, extensionsOf };
}
