import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Agents\Widget\WidgetPreviewController::__invoke
* @see app/Http/Controllers/Agents/Widget/WidgetPreviewController.php:10
* @route '/agents/{agent}/widget/preview'
*/
const WidgetPreviewController = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: WidgetPreviewController.url(args, options),
    method: 'get',
})

WidgetPreviewController.definition = {
    methods: ["get","head"],
    url: '/agents/{agent}/widget/preview',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Agents\Widget\WidgetPreviewController::__invoke
* @see app/Http/Controllers/Agents/Widget/WidgetPreviewController.php:10
* @route '/agents/{agent}/widget/preview'
*/
WidgetPreviewController.url = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
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

    return WidgetPreviewController.definition.url
            .replace('{agent}', parsedArgs.agent.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Agents\Widget\WidgetPreviewController::__invoke
* @see app/Http/Controllers/Agents/Widget/WidgetPreviewController.php:10
* @route '/agents/{agent}/widget/preview'
*/
WidgetPreviewController.get = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: WidgetPreviewController.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Agents\Widget\WidgetPreviewController::__invoke
* @see app/Http/Controllers/Agents/Widget/WidgetPreviewController.php:10
* @route '/agents/{agent}/widget/preview'
*/
WidgetPreviewController.head = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: WidgetPreviewController.url(args, options),
    method: 'head',
})

export default WidgetPreviewController