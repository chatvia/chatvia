import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Agents\Agents\AgentPreviewController::__invoke
* @see app/Http/Controllers/Agents/Agents/AgentPreviewController.php:12
* @route '/agents/{agent}/preview'
*/
const AgentPreviewController = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: AgentPreviewController.url(args, options),
    method: 'get',
})

AgentPreviewController.definition = {
    methods: ["get","head"],
    url: '/agents/{agent}/preview',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Agents\Agents\AgentPreviewController::__invoke
* @see app/Http/Controllers/Agents/Agents/AgentPreviewController.php:12
* @route '/agents/{agent}/preview'
*/
AgentPreviewController.url = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
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

    return AgentPreviewController.definition.url
            .replace('{agent}', parsedArgs.agent.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Agents\Agents\AgentPreviewController::__invoke
* @see app/Http/Controllers/Agents/Agents/AgentPreviewController.php:12
* @route '/agents/{agent}/preview'
*/
AgentPreviewController.get = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: AgentPreviewController.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Agents\Agents\AgentPreviewController::__invoke
* @see app/Http/Controllers/Agents/Agents/AgentPreviewController.php:12
* @route '/agents/{agent}/preview'
*/
AgentPreviewController.head = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: AgentPreviewController.url(args, options),
    method: 'head',
})

export default AgentPreviewController