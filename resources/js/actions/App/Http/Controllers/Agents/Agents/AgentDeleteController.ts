import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Agents\Agents\AgentDeleteController::__invoke
* @see app/Http/Controllers/Agents/Agents/AgentDeleteController.php:10
* @route '/agents/{agent}'
*/
const AgentDeleteController = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: AgentDeleteController.url(args, options),
    method: 'delete',
})

AgentDeleteController.definition = {
    methods: ["delete"],
    url: '/agents/{agent}',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\Agents\Agents\AgentDeleteController::__invoke
* @see app/Http/Controllers/Agents/Agents/AgentDeleteController.php:10
* @route '/agents/{agent}'
*/
AgentDeleteController.url = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
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

    return AgentDeleteController.definition.url
            .replace('{agent}', parsedArgs.agent.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Agents\Agents\AgentDeleteController::__invoke
* @see app/Http/Controllers/Agents/Agents/AgentDeleteController.php:10
* @route '/agents/{agent}'
*/
AgentDeleteController.delete = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: AgentDeleteController.url(args, options),
    method: 'delete',
})

export default AgentDeleteController