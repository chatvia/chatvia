import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Agents\Appearance\AgentAppearanceController::__invoke
* @see app/Http/Controllers/Agents/Appearance/AgentAppearanceController.php:11
* @route '/agents/{agent}/appearance'
*/
const AgentAppearanceController = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: AgentAppearanceController.url(args, options),
    method: 'get',
})

AgentAppearanceController.definition = {
    methods: ["get","head"],
    url: '/agents/{agent}/appearance',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Agents\Appearance\AgentAppearanceController::__invoke
* @see app/Http/Controllers/Agents/Appearance/AgentAppearanceController.php:11
* @route '/agents/{agent}/appearance'
*/
AgentAppearanceController.url = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
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

    return AgentAppearanceController.definition.url
            .replace('{agent}', parsedArgs.agent.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Agents\Appearance\AgentAppearanceController::__invoke
* @see app/Http/Controllers/Agents/Appearance/AgentAppearanceController.php:11
* @route '/agents/{agent}/appearance'
*/
AgentAppearanceController.get = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: AgentAppearanceController.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Agents\Appearance\AgentAppearanceController::__invoke
* @see app/Http/Controllers/Agents/Appearance/AgentAppearanceController.php:11
* @route '/agents/{agent}/appearance'
*/
AgentAppearanceController.head = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: AgentAppearanceController.url(args, options),
    method: 'head',
})

export default AgentAppearanceController