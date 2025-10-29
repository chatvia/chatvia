import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Agents\Sources\AgentKnowledgeFileUploadController::__invoke
* @see app/Http/Controllers/Agents/Sources/AgentKnowledgeFileUploadController.php:11
* @route '/agents/{agent}/sources/files/upload'
*/
const AgentKnowledgeFileUploadController = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: AgentKnowledgeFileUploadController.url(args, options),
    method: 'post',
})

AgentKnowledgeFileUploadController.definition = {
    methods: ["post"],
    url: '/agents/{agent}/sources/files/upload',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Agents\Sources\AgentKnowledgeFileUploadController::__invoke
* @see app/Http/Controllers/Agents/Sources/AgentKnowledgeFileUploadController.php:11
* @route '/agents/{agent}/sources/files/upload'
*/
AgentKnowledgeFileUploadController.url = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
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

    return AgentKnowledgeFileUploadController.definition.url
            .replace('{agent}', parsedArgs.agent.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Agents\Sources\AgentKnowledgeFileUploadController::__invoke
* @see app/Http/Controllers/Agents/Sources/AgentKnowledgeFileUploadController.php:11
* @route '/agents/{agent}/sources/files/upload'
*/
AgentKnowledgeFileUploadController.post = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: AgentKnowledgeFileUploadController.url(args, options),
    method: 'post',
})

export default AgentKnowledgeFileUploadController