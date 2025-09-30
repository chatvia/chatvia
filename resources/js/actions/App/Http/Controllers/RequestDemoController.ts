import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\RequestDemoController::__invoke
* @see app/Http/Controllers/RequestDemoController.php:12
* @route '/demo'
*/
const RequestDemoController0efea5765db02e3af45380ee625e3ea3 = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: RequestDemoController0efea5765db02e3af45380ee625e3ea3.url(options),
    method: 'get',
})

RequestDemoController0efea5765db02e3af45380ee625e3ea3.definition = {
    methods: ["get","head"],
    url: '/demo',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\RequestDemoController::__invoke
* @see app/Http/Controllers/RequestDemoController.php:12
* @route '/demo'
*/
RequestDemoController0efea5765db02e3af45380ee625e3ea3.url = (options?: RouteQueryOptions) => {
    return RequestDemoController0efea5765db02e3af45380ee625e3ea3.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\RequestDemoController::__invoke
* @see app/Http/Controllers/RequestDemoController.php:12
* @route '/demo'
*/
RequestDemoController0efea5765db02e3af45380ee625e3ea3.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: RequestDemoController0efea5765db02e3af45380ee625e3ea3.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\RequestDemoController::__invoke
* @see app/Http/Controllers/RequestDemoController.php:12
* @route '/demo'
*/
RequestDemoController0efea5765db02e3af45380ee625e3ea3.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: RequestDemoController0efea5765db02e3af45380ee625e3ea3.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\RequestDemoController::__invoke
* @see app/Http/Controllers/RequestDemoController.php:12
* @route '/demo'
*/
const RequestDemoController0efea5765db02e3af45380ee625e3ea3 = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: RequestDemoController0efea5765db02e3af45380ee625e3ea3.url(options),
    method: 'post',
})

RequestDemoController0efea5765db02e3af45380ee625e3ea3.definition = {
    methods: ["post"],
    url: '/demo',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\RequestDemoController::__invoke
* @see app/Http/Controllers/RequestDemoController.php:12
* @route '/demo'
*/
RequestDemoController0efea5765db02e3af45380ee625e3ea3.url = (options?: RouteQueryOptions) => {
    return RequestDemoController0efea5765db02e3af45380ee625e3ea3.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\RequestDemoController::__invoke
* @see app/Http/Controllers/RequestDemoController.php:12
* @route '/demo'
*/
RequestDemoController0efea5765db02e3af45380ee625e3ea3.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: RequestDemoController0efea5765db02e3af45380ee625e3ea3.url(options),
    method: 'post',
})

const RequestDemoController = {
    '/demo': RequestDemoController0efea5765db02e3af45380ee625e3ea3,
    '/demo': RequestDemoController0efea5765db02e3af45380ee625e3ea3,
}

export default RequestDemoController