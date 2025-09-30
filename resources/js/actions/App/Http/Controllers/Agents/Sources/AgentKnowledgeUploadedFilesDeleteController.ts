import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Agents\Sources\AgentKnowledgeUploadedFilesDeleteController::__invoke
* @see app/Http/Controllers/Agents/Sources/AgentKnowledgeUploadedFilesDeleteController.php:10
* @route '/agents/{agent}/sources/files/uploaded/all'
*/
const AgentKnowledgeUploadedFilesDeleteController = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: AgentKnowledgeUploadedFilesDeleteController.url(args, options),
    method: 'delete',
})

AgentKnowledgeUploadedFilesDeleteController.definition = {
    methods: ["delete"],
    url: '/agents/{agent}/sources/files/uploaded/all',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\Agents\Sources\AgentKnowledgeUploadedFilesDeleteController::__invoke
* @see app/Http/Controllers/Agents/Sources/AgentKnowledgeUploadedFilesDeleteController.php:10
* @route '/agents/{agent}/sources/files/uploaded/all'
*/
AgentKnowledgeUploadedFilesDeleteController.url = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { agent: args }
    }

    if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
        args = { agent: args.id }
    }

    if (Array.isArray(args)) {
        args = {
            agent: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        agent: typeof args.agent === 'object'
        ? args.agent.id
        : args.agent,
    }

    return AgentKnowledgeUploadedFilesDeleteController.definition.url
            .replace('{agent}', parsedArgs.agent.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Agents\Sources\AgentKnowledgeUploadedFilesDeleteController::__invoke
* @see app/Http/Controllers/Agents/Sources/AgentKnowledgeUploadedFilesDeleteController.php:10
* @route '/agents/{agent}/sources/files/uploaded/all'
*/
AgentKnowledgeUploadedFilesDeleteController.delete = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: AgentKnowledgeUploadedFilesDeleteController.url(args, options),
    method: 'delete',
})

export default AgentKnowledgeUploadedFilesDeleteController