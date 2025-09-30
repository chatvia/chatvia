import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Agents\Widget\WidgetController::__invoke
* @see app/Http/Controllers/Agents/Widget/WidgetController.php:11
* @route '/agents/{agent}/widget'
*/
const WidgetController = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: WidgetController.url(args, options),
    method: 'get',
})

WidgetController.definition = {
    methods: ["get","head"],
    url: '/agents/{agent}/widget',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Agents\Widget\WidgetController::__invoke
* @see app/Http/Controllers/Agents/Widget/WidgetController.php:11
* @route '/agents/{agent}/widget'
*/
WidgetController.url = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
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

    return WidgetController.definition.url
            .replace('{agent}', parsedArgs.agent.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Agents\Widget\WidgetController::__invoke
* @see app/Http/Controllers/Agents/Widget/WidgetController.php:11
* @route '/agents/{agent}/widget'
*/
WidgetController.get = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: WidgetController.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Agents\Widget\WidgetController::__invoke
* @see app/Http/Controllers/Agents/Widget/WidgetController.php:11
* @route '/agents/{agent}/widget'
*/
WidgetController.head = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: WidgetController.url(args, options),
    method: 'head',
})

export default WidgetController