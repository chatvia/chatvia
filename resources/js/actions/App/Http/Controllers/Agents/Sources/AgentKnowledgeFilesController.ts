import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Agents\Sources\AgentKnowledgeFilesController::__invoke
* @see app/Http/Controllers/Agents/Sources/AgentKnowledgeFilesController.php:12
* @route '/agents/{agent}/sources/files'
*/
const AgentKnowledgeFilesController = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: AgentKnowledgeFilesController.url(args, options),
    method: 'get',
})

AgentKnowledgeFilesController.definition = {
    methods: ["get","head"],
    url: '/agents/{agent}/sources/files',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Agents\Sources\AgentKnowledgeFilesController::__invoke
* @see app/Http/Controllers/Agents/Sources/AgentKnowledgeFilesController.php:12
* @route '/agents/{agent}/sources/files'
*/
AgentKnowledgeFilesController.url = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
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

    return AgentKnowledgeFilesController.definition.url
            .replace('{agent}', parsedArgs.agent.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Agents\Sources\AgentKnowledgeFilesController::__invoke
* @see app/Http/Controllers/Agents/Sources/AgentKnowledgeFilesController.php:12
* @route '/agents/{agent}/sources/files'
*/
AgentKnowledgeFilesController.get = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: AgentKnowledgeFilesController.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Agents\Sources\AgentKnowledgeFilesController::__invoke
* @see app/Http/Controllers/Agents/Sources/AgentKnowledgeFilesController.php:12
* @route '/agents/{agent}/sources/files'
*/
AgentKnowledgeFilesController.head = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: AgentKnowledgeFilesController.url(args, options),
    method: 'head',
})

export default AgentKnowledgeFilesController