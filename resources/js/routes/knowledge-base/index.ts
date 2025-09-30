import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../wayfinder'
/**
* @see \App\Http\Controllers\KnowledgeFiles\KnowledgeFilesShowController::__invoke
* @see app/Http/Controllers/KnowledgeFiles/KnowledgeFilesShowController.php:10
* @route '/dashboard/knowledge-base'
*/
export const show = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(options),
    method: 'get',
})

show.definition = {
    methods: ["get","head"],
    url: '/dashboard/knowledge-base',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\KnowledgeFiles\KnowledgeFilesShowController::__invoke
* @see app/Http/Controllers/KnowledgeFiles/KnowledgeFilesShowController.php:10
* @route '/dashboard/knowledge-base'
*/
show.url = (options?: RouteQueryOptions) => {
    return show.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\KnowledgeFiles\KnowledgeFilesShowController::__invoke
* @see app/Http/Controllers/KnowledgeFiles/KnowledgeFilesShowController.php:10
* @route '/dashboard/knowledge-base'
*/
show.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\KnowledgeFiles\KnowledgeFilesShowController::__invoke
* @see app/Http/Controllers/KnowledgeFiles/KnowledgeFilesShowController.php:10
* @route '/dashboard/knowledge-base'
*/
show.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: show.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\KnowledgeFiles\KnowledgeFilesListController::__invoke
* @see app/Http/Controllers/KnowledgeFiles/KnowledgeFilesListController.php:10
* @route '/dashboard/knowledge-base/list'
*/
export const list = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: list.url(options),
    method: 'get',
})

list.definition = {
    methods: ["get","head"],
    url: '/dashboard/knowledge-base/list',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\KnowledgeFiles\KnowledgeFilesListController::__invoke
* @see app/Http/Controllers/KnowledgeFiles/KnowledgeFilesListController.php:10
* @route '/dashboard/knowledge-base/list'
*/
list.url = (options?: RouteQueryOptions) => {
    return list.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\KnowledgeFiles\KnowledgeFilesListController::__invoke
* @see app/Http/Controllers/KnowledgeFiles/KnowledgeFilesListController.php:10
* @route '/dashboard/knowledge-base/list'
*/
list.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: list.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\KnowledgeFiles\KnowledgeFilesListController::__invoke
* @see app/Http/Controllers/KnowledgeFiles/KnowledgeFilesListController.php:10
* @route '/dashboard/knowledge-base/list'
*/
list.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: list.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\KnowledgeFiles\KnowledgeFilesDownload::__invoke
* @see app/Http/Controllers/KnowledgeFiles/KnowledgeFilesDownload.php:10
* @route '/dashboard/knowledge-base/download/{agentFile}'
*/
export const download = (args: { agentFile: number | { id: number } } | [agentFile: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: download.url(args, options),
    method: 'get',
})

download.definition = {
    methods: ["get","head"],
    url: '/dashboard/knowledge-base/download/{agentFile}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\KnowledgeFiles\KnowledgeFilesDownload::__invoke
* @see app/Http/Controllers/KnowledgeFiles/KnowledgeFilesDownload.php:10
* @route '/dashboard/knowledge-base/download/{agentFile}'
*/
download.url = (args: { agentFile: number | { id: number } } | [agentFile: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
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

    return download.definition.url
            .replace('{agentFile}', parsedArgs.agentFile.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\KnowledgeFiles\KnowledgeFilesDownload::__invoke
* @see app/Http/Controllers/KnowledgeFiles/KnowledgeFilesDownload.php:10
* @route '/dashboard/knowledge-base/download/{agentFile}'
*/
download.get = (args: { agentFile: number | { id: number } } | [agentFile: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: download.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\KnowledgeFiles\KnowledgeFilesDownload::__invoke
* @see app/Http/Controllers/KnowledgeFiles/KnowledgeFilesDownload.php:10
* @route '/dashboard/knowledge-base/download/{agentFile}'
*/
download.head = (args: { agentFile: number | { id: number } } | [agentFile: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: download.url(args, options),
    method: 'head',
})

const knowledgeBase = {
    show: Object.assign(show, show),
    list: Object.assign(list, list),
    download: Object.assign(download, download),
}

export default knowledgeBase