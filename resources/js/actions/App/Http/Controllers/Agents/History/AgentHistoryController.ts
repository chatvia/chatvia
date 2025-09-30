import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Agents\History\AgentHistoryController::__invoke
* @see app/Http/Controllers/Agents/History/AgentHistoryController.php:11
* @route '/agents/{agent}/history'
*/
const AgentHistoryController = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: AgentHistoryController.url(args, options),
    method: 'get',
})

AgentHistoryController.definition = {
    methods: ["get","head"],
    url: '/agents/{agent}/history',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Agents\History\AgentHistoryController::__invoke
* @see app/Http/Controllers/Agents/History/AgentHistoryController.php:11
* @route '/agents/{agent}/history'
*/
AgentHistoryController.url = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
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

    return AgentHistoryController.definition.url
            .replace('{agent}', parsedArgs.agent.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Agents\History\AgentHistoryController::__invoke
* @see app/Http/Controllers/Agents/History/AgentHistoryController.php:11
* @route '/agents/{agent}/history'
*/
AgentHistoryController.get = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: AgentHistoryController.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Agents\History\AgentHistoryController::__invoke
* @see app/Http/Controllers/Agents/History/AgentHistoryController.php:11
* @route '/agents/{agent}/history'
*/
AgentHistoryController.head = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: AgentHistoryController.url(args, options),
    method: 'head',
})

export default AgentHistoryController