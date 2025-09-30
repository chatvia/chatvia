import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Agents\Models\AgentModelsShowController::__invoke
* @see app/Http/Controllers/Agents/Models/AgentModelsShowController.php:12
* @route '/agents/{agent}/models'
*/
const AgentModelsShowController = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: AgentModelsShowController.url(args, options),
    method: 'get',
})

AgentModelsShowController.definition = {
    methods: ["get","head"],
    url: '/agents/{agent}/models',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Agents\Models\AgentModelsShowController::__invoke
* @see app/Http/Controllers/Agents/Models/AgentModelsShowController.php:12
* @route '/agents/{agent}/models'
*/
AgentModelsShowController.url = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
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

    return AgentModelsShowController.definition.url
            .replace('{agent}', parsedArgs.agent.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Agents\Models\AgentModelsShowController::__invoke
* @see app/Http/Controllers/Agents/Models/AgentModelsShowController.php:12
* @route '/agents/{agent}/models'
*/
AgentModelsShowController.get = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: AgentModelsShowController.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Agents\Models\AgentModelsShowController::__invoke
* @see app/Http/Controllers/Agents/Models/AgentModelsShowController.php:12
* @route '/agents/{agent}/models'
*/
AgentModelsShowController.head = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: AgentModelsShowController.url(args, options),
    method: 'head',
})

export default AgentModelsShowController