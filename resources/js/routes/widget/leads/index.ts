import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../wayfinder'
/**
* @see \App\Http\Controllers\Widget\WidgetLeadFormController::__invoke
* @see app/Http/Controllers/Widget/WidgetLeadFormController.php:10
* @route '/api/widget/leads/form'
*/
export const form = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: form.url(options),
    method: 'get',
})

form.definition = {
    methods: ["get","head"],
    url: '/api/widget/leads/form',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Widget\WidgetLeadFormController::__invoke
* @see app/Http/Controllers/Widget/WidgetLeadFormController.php:10
* @route '/api/widget/leads/form'
*/
form.url = (options?: RouteQueryOptions) => {
    return form.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Widget\WidgetLeadFormController::__invoke
* @see app/Http/Controllers/Widget/WidgetLeadFormController.php:10
* @route '/api/widget/leads/form'
*/
form.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: form.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Widget\WidgetLeadFormController::__invoke
* @see app/Http/Controllers/Widget/WidgetLeadFormController.php:10
* @route '/api/widget/leads/form'
*/
form.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: form.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Widget\WidgetLeadCollectController::__invoke
* @see app/Http/Controllers/Widget/WidgetLeadCollectController.php:14
* @route '/api/widget/leads/form'
*/
export const collect = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: collect.url(options),
    method: 'post',
})

collect.definition = {
    methods: ["post"],
    url: '/api/widget/leads/form',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Widget\WidgetLeadCollectController::__invoke
* @see app/Http/Controllers/Widget/WidgetLeadCollectController.php:14
* @route '/api/widget/leads/form'
*/
collect.url = (options?: RouteQueryOptions) => {
    return collect.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Widget\WidgetLeadCollectController::__invoke
* @see app/Http/Controllers/Widget/WidgetLeadCollectController.php:14
* @route '/api/widget/leads/form'
*/
collect.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: collect.url(options),
    method: 'post',
})

const leads = {
    form: Object.assign(form, form),
    collect: Object.assign(collect, collect),
}

export default leads