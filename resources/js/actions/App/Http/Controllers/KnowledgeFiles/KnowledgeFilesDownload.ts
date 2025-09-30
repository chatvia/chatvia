import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\KnowledgeFiles\KnowledgeFilesDownload::__invoke
* @see app/Http/Controllers/KnowledgeFiles/KnowledgeFilesDownload.php:10
* @route '/dashboard/knowledge-base/download/{agentFile}'
*/
const KnowledgeFilesDownload = (args: { agentFile: number | { id: number } } | [agentFile: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: KnowledgeFilesDownload.url(args, options),
    method: 'get',
})

KnowledgeFilesDownload.definition = {
    methods: ["get","head"],
    url: '/dashboard/knowledge-base/download/{agentFile}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\KnowledgeFiles\KnowledgeFilesDownload::__invoke
* @see app/Http/Controllers/KnowledgeFiles/KnowledgeFilesDownload.php:10
* @route '/dashboard/knowledge-base/download/{agentFile}'
*/
KnowledgeFilesDownload.url = (args: { agentFile: number | { id: number } } | [agentFile: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { agentFile: args }
    }

    if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
        args = { agentFile: args.id }
    }

    if (Array.isArray(args)) {
        args = {
            agentFile: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        agentFile: typeof args.agentFile === 'object'
        ? args.agentFile.id
        : args.agentFile,
    }

    return KnowledgeFilesDownload.definition.url
            .replace('{agentFile}', parsedArgs.agentFile.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\KnowledgeFiles\KnowledgeFilesDownload::__invoke
* @see app/Http/Controllers/KnowledgeFiles/KnowledgeFilesDownload.php:10
* @route '/dashboard/knowledge-base/download/{agentFile}'
*/
KnowledgeFilesDownload.get = (args: { agentFile: number | { id: number } } | [agentFile: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: KnowledgeFilesDownload.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\KnowledgeFiles\KnowledgeFilesDownload::__invoke
* @see app/Http/Controllers/KnowledgeFiles/KnowledgeFilesDownload.php:10
* @route '/dashboard/knowledge-base/download/{agentFile}'
*/
KnowledgeFilesDownload.head = (args: { agentFile: number | { id: number } } | [agentFile: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: KnowledgeFilesDownload.url(args, options),
    method: 'head',
})

export default KnowledgeFilesDownload