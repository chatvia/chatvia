import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Landing\ChangelogController::__invoke
* @see app/Http/Controllers/Landing/ChangelogController.php:14
* @route '/changelog'
*/
const ChangelogController = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: ChangelogController.url(options),
    method: 'get',
})

ChangelogController.definition = {
    methods: ["get","head"],
    url: '/changelog',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Landing\ChangelogController::__invoke
* @see app/Http/Controllers/Landing/ChangelogController.php:14
* @route '/changelog'
*/
ChangelogController.url = (options?: RouteQueryOptions) => {
    return ChangelogController.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Landing\ChangelogController::__invoke
* @see app/Http/Controllers/Landing/ChangelogController.php:14
* @route '/changelog'
*/
ChangelogController.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: ChangelogController.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Landing\ChangelogController::__invoke
* @see app/Http/Controllers/Landing/ChangelogController.php:14
* @route '/changelog'
*/
ChangelogController.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: ChangelogController.url(options),
    method: 'head',
})

export default ChangelogController