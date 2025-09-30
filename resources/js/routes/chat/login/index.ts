import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../wayfinder'
/**
* @see \App\Http\Controllers\Chat\Auth\ChatShowLoginController::__invoke
* @see app/Http/Controllers/Chat/Auth/ChatShowLoginController.php:15
* @route '/chat/login'
*/
export const show = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(options),
    method: 'get',
})

show.definition = {
    methods: ["get","head"],
    url: '/chat/login',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Chat\Auth\ChatShowLoginController::__invoke
* @see app/Http/Controllers/Chat/Auth/ChatShowLoginController.php:15
* @route '/chat/login'
*/
show.url = (options?: RouteQueryOptions) => {
    return show.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Chat\Auth\ChatShowLoginController::__invoke
* @see app/Http/Controllers/Chat/Auth/ChatShowLoginController.php:15
* @route '/chat/login'
*/
show.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Chat\Auth\ChatShowLoginController::__invoke
* @see app/Http/Controllers/Chat/Auth/ChatShowLoginController.php:15
* @route '/chat/login'
*/
show.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: show.url(options),
    method: 'head',
})

