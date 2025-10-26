import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Agents\Sources\AgentKnowledgeSourcesStatsController::__invoke
* @see app/Http/Controllers/Agents/Sources/AgentKnowledgeSourcesStatsController.php:10
* @route '/agents/{agent}/sources/stats'
*/
const AgentKnowledgeSourcesStatsController = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: AgentKnowledgeSourcesStatsController.url(args, options),
    method: 'get',
})

AgentKnowledgeSourcesStatsController.definition = {
    methods: ["get","head"],
    url: '/agents/{agent}/sources/stats',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Agents\Sources\AgentKnowledgeSourcesStatsController::__invoke
* @see app/Http/Controllers/Agents/Sources/AgentKnowledgeSourcesStatsController.php:10
* @route '/agents/{agent}/sources/stats'
*/
AgentKnowledgeSourcesStatsController.url = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
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

    return AgentKnowledgeSourcesStatsController.definition.url
            .replace('{agent}', parsedArgs.agent.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Agents\Sources\AgentKnowledgeSourcesStatsController::__invoke
* @see app/Http/Controllers/Agents/Sources/AgentKnowledgeSourcesStatsController.php:10
* @route '/agents/{agent}/sources/stats'
*/
AgentKnowledgeSourcesStatsController.get = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: AgentKnowledgeSourcesStatsController.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Agents\Sources\AgentKnowledgeSourcesStatsController::__invoke
* @see app/Http/Controllers/Agents/Sources/AgentKnowledgeSourcesStatsController.php:10
* @route '/agents/{agent}/sources/stats'
*/
AgentKnowledgeSourcesStatsController.head = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: AgentKnowledgeSourcesStatsController.url(args, options),
    method: 'head',
})

export default AgentKnowledgeSourcesStatsController