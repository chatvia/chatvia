import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Widget\WidgetLeadCollectController::__invoke
* @see app/Http/Controllers/Widget/WidgetLeadCollectController.php:14
* @route '/api/widget/leads/form'
*/
const WidgetLeadCollectController = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: WidgetLeadCollectController.url(options),
    method: 'post',
})

WidgetLeadCollectController.definition = {
    methods: ["post"],
    url: '/api/widget/leads/form',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Widget\WidgetLeadCollectController::__invoke
* @see app/Http/Controllers/Widget/WidgetLeadCollectController.php:14
* @route '/api/widget/leads/form'
*/
WidgetLeadCollectController.url = (options?: RouteQueryOptions) => {
    return WidgetLeadCollectController.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Widget\WidgetLeadCollectController::__invoke
* @see app/Http/Controllers/Widget/WidgetLeadCollectController.php:14
* @route '/api/widget/leads/form'
*/
WidgetLeadCollectController.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: WidgetLeadCollectController.url(options),
    method: 'post',
})

export default WidgetLeadCollectController