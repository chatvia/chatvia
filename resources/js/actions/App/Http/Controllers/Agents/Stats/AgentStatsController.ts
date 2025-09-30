import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Agents\Stats\AgentStatsController::__invoke
* @see app/Http/Controllers/Agents/Stats/AgentStatsController.php:11
* @route '/agents/{agent}/stats'
*/
const AgentStatsController = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: AgentStatsController.url(args, options),
    method: 'get',
})

AgentStatsController.definition = {
    methods: ["get","head"],
    url: '/agents/{agent}/stats',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Agents\Stats\AgentStatsController::__invoke
* @see app/Http/Controllers/Agents/Stats/AgentStatsController.php:11
* @route '/agents/{agent}/stats'
*/
AgentStatsController.url = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
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

    return AgentStatsController.definition.url
            .replace('{agent}', parsedArgs.agent.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Agents\Stats\AgentStatsController::__invoke
* @see app/Http/Controllers/Agents/Stats/AgentStatsController.php:11
* @route '/agents/{agent}/stats'
*/
AgentStatsController.get = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: AgentStatsController.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Agents\Stats\AgentStatsController::__invoke
* @see app/Http/Controllers/Agents/Stats/AgentStatsController.php:11
* @route '/agents/{agent}/stats'
*/
AgentStatsController.head = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: AgentStatsController.url(args, options),
    method: 'head',
})

export default AgentStatsController