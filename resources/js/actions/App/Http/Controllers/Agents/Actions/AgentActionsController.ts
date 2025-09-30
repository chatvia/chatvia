import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Agents\Actions\AgentActionsController::__invoke
* @see app/Http/Controllers/Agents/Actions/AgentActionsController.php:12
* @route '/agents/{agent}/actions'
*/
const AgentActionsController = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: AgentActionsController.url(args, options),
    method: 'get',
})

AgentActionsController.definition = {
    methods: ["get","head"],
    url: '/agents/{agent}/actions',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Agents\Actions\AgentActionsController::__invoke
* @see app/Http/Controllers/Agents/Actions/AgentActionsController.php:12
* @route '/agents/{agent}/actions'
*/
AgentActionsController.url = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
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

    return AgentActionsController.definition.url
            .replace('{agent}', parsedArgs.agent.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Agents\Actions\AgentActionsController::__invoke
* @see app/Http/Controllers/Agents/Actions/AgentActionsController.php:12
* @route '/agents/{agent}/actions'
*/
AgentActionsController.get = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: AgentActionsController.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Agents\Actions\AgentActionsController::__invoke
* @see app/Http/Controllers/Agents/Actions/AgentActionsController.php:12
* @route '/agents/{agent}/actions'
*/
AgentActionsController.head = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: AgentActionsController.url(args, options),
    method: 'head',
})

export default AgentActionsController