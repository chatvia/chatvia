import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Agents\Sources\AgentKnowledgeSourcesController::__invoke
* @see app/Http/Controllers/Agents/Sources/AgentKnowledgeSourcesController.php:11
* @route '/agents/{agent}/sources'
*/
const AgentKnowledgeSourcesController = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: AgentKnowledgeSourcesController.url(args, options),
    method: 'get',
})

AgentKnowledgeSourcesController.definition = {
    methods: ["get","head"],
    url: '/agents/{agent}/sources',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Agents\Sources\AgentKnowledgeSourcesController::__invoke
* @see app/Http/Controllers/Agents/Sources/AgentKnowledgeSourcesController.php:11
* @route '/agents/{agent}/sources'
*/
AgentKnowledgeSourcesController.url = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
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

    return AgentKnowledgeSourcesController.definition.url
            .replace('{agent}', parsedArgs.agent.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Agents\Sources\AgentKnowledgeSourcesController::__invoke
* @see app/Http/Controllers/Agents/Sources/AgentKnowledgeSourcesController.php:11
* @route '/agents/{agent}/sources'
*/
AgentKnowledgeSourcesController.get = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: AgentKnowledgeSourcesController.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Agents\Sources\AgentKnowledgeSourcesController::__invoke
* @see app/Http/Controllers/Agents/Sources/AgentKnowledgeSourcesController.php:11
* @route '/agents/{agent}/sources'
*/
AgentKnowledgeSourcesController.head = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: AgentKnowledgeSourcesController.url(args, options),
    method: 'head',
})

export default AgentKnowledgeSourcesController