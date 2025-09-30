import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\ConsentController::show
* @see app/Http/Controllers/ConsentController.php:14
* @route '/consent'
*/
export const show = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(options),
    method: 'get',
})

show.definition = {
    methods: ["get","head"],
    url: '/consent',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\ConsentController::show
* @see app/Http/Controllers/ConsentController.php:14
* @route '/consent'
*/
show.url = (options?: RouteQueryOptions) => {
    return show.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\ConsentController::show
* @see app/Http/Controllers/ConsentController.php:14
* @route '/consent'
*/
show.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\ConsentController::show
* @see app/Http/Controllers/ConsentController.php:14
* @route '/consent'
*/
show.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: show.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\ConsentController::store
* @see app/Http/Controllers/ConsentController.php:22
* @route '/consent'
*/
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/consent',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\ConsentController::store
* @see app/Http/Controllers/ConsentController.php:22
* @route '/consent'
*/
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\ConsentController::store
* @see app/Http/Controllers/ConsentController.php:22
* @route '/consent'
*/
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

const ConsentController = { show, store }

export default ConsentController