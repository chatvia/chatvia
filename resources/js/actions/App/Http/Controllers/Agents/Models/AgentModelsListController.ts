import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Agents\Models\AgentModelsListController::__invoke
* @see app/Http/Controllers/Agents/Models/AgentModelsListController.php:11
* @route '/agents/{agent}/models/list'
*/
const AgentModelsListController = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: AgentModelsListController.url(args, options),
    method: 'get',
})

AgentModelsListController.definition = {
    methods: ["get","head"],
    url: '/agents/{agent}/models/list',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Agents\Models\AgentModelsListController::__invoke
* @see app/Http/Controllers/Agents/Models/AgentModelsListController.php:11
* @route '/agents/{agent}/models/list'
*/
AgentModelsListController.url = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
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

    return AgentModelsListController.definition.url
            .replace('{agent}', parsedArgs.agent.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Agents\Models\AgentModelsListController::__invoke
* @see app/Http/Controllers/Agents/Models/AgentModelsListController.php:11
* @route '/agents/{agent}/models/list'
*/
AgentModelsListController.get = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: AgentModelsListController.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Agents\Models\AgentModelsListController::__invoke
* @see app/Http/Controllers/Agents/Models/AgentModelsListController.php:11
* @route '/agents/{agent}/models/list'
*/
AgentModelsListController.head = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: AgentModelsListController.url(args, options),
    method: 'head',
})

export default AgentModelsListController