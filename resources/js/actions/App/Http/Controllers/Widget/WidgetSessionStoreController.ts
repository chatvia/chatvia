import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Widget\WidgetSessionStoreController::__invoke
* @see app/Http/Controllers/Widget/WidgetSessionStoreController.php:11
* @route '/api/widget/sessions'
*/
const WidgetSessionStoreController = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: WidgetSessionStoreController.url(options),
    method: 'post',
})

WidgetSessionStoreController.definition = {
    methods: ["post"],
    url: '/api/widget/sessions',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Widget\WidgetSessionStoreController::__invoke
* @see app/Http/Controllers/Widget/WidgetSessionStoreController.php:11
* @route '/api/widget/sessions'
*/
WidgetSessionStoreController.url = (options?: RouteQueryOptions) => {
    return WidgetSessionStoreController.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Widget\WidgetSessionStoreController::__invoke
* @see app/Http/Controllers/Widget/WidgetSessionStoreController.php:11
* @route '/api/widget/sessions'
*/
WidgetSessionStoreController.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: WidgetSessionStoreController.url(options),
    method: 'post',
})

export default WidgetSessionStoreController