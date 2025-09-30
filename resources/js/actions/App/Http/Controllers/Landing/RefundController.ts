import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Landing\RefundController::__invoke
* @see app/Http/Controllers/Landing/RefundController.php:9
* @route '/refund'
*/
const RefundController = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: RefundController.url(options),
    method: 'get',
})

RefundController.definition = {
    methods: ["get","head"],
    url: '/refund',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Landing\RefundController::__invoke
* @see app/Http/Controllers/Landing/RefundController.php:9
* @route '/refund'
*/
RefundController.url = (options?: RouteQueryOptions) => {
    return RefundController.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Landing\RefundController::__invoke
* @see app/Http/Controllers/Landing/RefundController.php:9
* @route '/refund'
*/
RefundController.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: RefundController.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Landing\RefundController::__invoke
* @see app/Http/Controllers/Landing/RefundController.php:9
* @route '/refund'
*/
RefundController.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: RefundController.url(options),
    method: 'head',
})

export default RefundController