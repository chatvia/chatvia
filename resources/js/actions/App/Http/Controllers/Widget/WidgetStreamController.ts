import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Widget\WidgetStreamController::__invoke
* @see app/Http/Controllers/Widget/WidgetStreamController.php:15
* @route '/api/widget/stream'
*/
const WidgetStreamController = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: WidgetStreamController.url(options),
    method: 'post',
})

WidgetStreamController.definition = {
    methods: ["post"],
    url: '/api/widget/stream',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Widget\WidgetStreamController::__invoke
* @see app/Http/Controllers/Widget/WidgetStreamController.php:15
* @route '/api/widget/stream'
*/
WidgetStreamController.url = (options?: RouteQueryOptions) => {
    return WidgetStreamController.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Widget\WidgetStreamController::__invoke
* @see app/Http/Controllers/Widget/WidgetStreamController.php:15
* @route '/api/widget/stream'
*/
WidgetStreamController.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: WidgetStreamController.url(options),
    method: 'post',
})

export default WidgetStreamController