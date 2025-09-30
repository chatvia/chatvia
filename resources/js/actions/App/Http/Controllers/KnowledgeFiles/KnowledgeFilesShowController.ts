import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\KnowledgeFiles\KnowledgeFilesShowController::__invoke
* @see app/Http/Controllers/KnowledgeFiles/KnowledgeFilesShowController.php:10
* @route '/dashboard/knowledge-base'
*/
const KnowledgeFilesShowController = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: KnowledgeFilesShowController.url(options),
    method: 'get',
})

KnowledgeFilesShowController.definition = {
    methods: ["get","head"],
    url: '/dashboard/knowledge-base',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\KnowledgeFiles\KnowledgeFilesShowController::__invoke
* @see app/Http/Controllers/KnowledgeFiles/KnowledgeFilesShowController.php:10
* @route '/dashboard/knowledge-base'
*/
KnowledgeFilesShowController.url = (options?: RouteQueryOptions) => {
    return KnowledgeFilesShowController.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\KnowledgeFiles\KnowledgeFilesShowController::__invoke
* @see app/Http/Controllers/KnowledgeFiles/KnowledgeFilesShowController.php:10
* @route '/dashboard/knowledge-base'
*/
KnowledgeFilesShowController.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: KnowledgeFilesShowController.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\KnowledgeFiles\KnowledgeFilesShowController::__invoke
* @see app/Http/Controllers/KnowledgeFiles/KnowledgeFilesShowController.php:10
* @route '/dashboard/knowledge-base'
*/
KnowledgeFilesShowController.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: KnowledgeFilesShowController.url(options),
    method: 'head',
})

export default KnowledgeFilesShowController