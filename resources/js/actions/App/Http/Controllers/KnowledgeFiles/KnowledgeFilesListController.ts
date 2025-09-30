import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\KnowledgeFiles\KnowledgeFilesListController::__invoke
* @see app/Http/Controllers/KnowledgeFiles/KnowledgeFilesListController.php:10
* @route '/dashboard/knowledge-base/list'
*/
const KnowledgeFilesListController = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: KnowledgeFilesListController.url(options),
    method: 'get',
})

KnowledgeFilesListController.definition = {
    methods: ["get","head"],
    url: '/dashboard/knowledge-base/list',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\KnowledgeFiles\KnowledgeFilesListController::__invoke
* @see app/Http/Controllers/KnowledgeFiles/KnowledgeFilesListController.php:10
* @route '/dashboard/knowledge-base/list'
*/
KnowledgeFilesListController.url = (options?: RouteQueryOptions) => {
    return KnowledgeFilesListController.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\KnowledgeFiles\KnowledgeFilesListController::__invoke
* @see app/Http/Controllers/KnowledgeFiles/KnowledgeFilesListController.php:10
* @route '/dashboard/knowledge-base/list'
*/
KnowledgeFilesListController.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: KnowledgeFilesListController.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\KnowledgeFiles\KnowledgeFilesListController::__invoke
* @see app/Http/Controllers/KnowledgeFiles/KnowledgeFilesListController.php:10
* @route '/dashboard/knowledge-base/list'
*/
KnowledgeFilesListController.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: KnowledgeFilesListController.url(options),
    method: 'head',
})

export default KnowledgeFilesListController