import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Widget\WidgetLeadFormController::__invoke
* @see app/Http/Controllers/Widget/WidgetLeadFormController.php:10
* @route '/api/widget/leads/form'
*/
const WidgetLeadFormController = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: WidgetLeadFormController.url(options),
    method: 'get',
})

WidgetLeadFormController.definition = {
    methods: ["get","head"],
    url: '/api/widget/leads/form',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Widget\WidgetLeadFormController::__invoke
* @see app/Http/Controllers/Widget/WidgetLeadFormController.php:10
* @route '/api/widget/leads/form'
*/
WidgetLeadFormController.url = (options?: RouteQueryOptions) => {
    return WidgetLeadFormController.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Widget\WidgetLeadFormController::__invoke
* @see app/Http/Controllers/Widget/WidgetLeadFormController.php:10
* @route '/api/widget/leads/form'
*/
WidgetLeadFormController.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: WidgetLeadFormController.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Widget\WidgetLeadFormController::__invoke
* @see app/Http/Controllers/Widget/WidgetLeadFormController.php:10
* @route '/api/widget/leads/form'
*/
WidgetLeadFormController.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: WidgetLeadFormController.url(options),
    method: 'head',
})

export default WidgetLeadFormController