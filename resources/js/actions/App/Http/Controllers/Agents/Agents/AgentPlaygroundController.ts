import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Agents\Agents\AgentPlaygroundController::__invoke
* @see app/Http/Controllers/Agents/Agents/AgentPlaygroundController.php:12
* @route '/agents/{agent}/playground'
*/
const AgentPlaygroundController = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: AgentPlaygroundController.url(args, options),
    method: 'get',
})

AgentPlaygroundController.definition = {
    methods: ["get","head"],
    url: '/agents/{agent}/playground',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Agents\Agents\AgentPlaygroundController::__invoke
* @see app/Http/Controllers/Agents/Agents/AgentPlaygroundController.php:12
* @route '/agents/{agent}/playground'
*/
AgentPlaygroundController.url = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
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

    return AgentPlaygroundController.definition.url
            .replace('{agent}', parsedArgs.agent.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Agents\Agents\AgentPlaygroundController::__invoke
* @see app/Http/Controllers/Agents/Agents/AgentPlaygroundController.php:12
* @route '/agents/{agent}/playground'
*/
AgentPlaygroundController.get = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: AgentPlaygroundController.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Agents\Agents\AgentPlaygroundController::__invoke
* @see app/Http/Controllers/Agents/Agents/AgentPlaygroundController.php:12
* @route '/agents/{agent}/playground'
*/
AgentPlaygroundController.head = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: AgentPlaygroundController.url(args, options),
    method: 'head',
})

export default AgentPlaygroundController