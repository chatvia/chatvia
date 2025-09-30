import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\BlogController::__invoke
* @see app/Http/Controllers/BlogController.php:10
* @route '/blog'
*/
const BlogController = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: BlogController.url(options),
    method: 'get',
})

BlogController.definition = {
    methods: ["get","head"],
    url: '/blog',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\BlogController::__invoke
* @see app/Http/Controllers/BlogController.php:10
* @route '/blog'
*/
BlogController.url = (options?: RouteQueryOptions) => {
    return BlogController.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\BlogController::__invoke
* @see app/Http/Controllers/BlogController.php:10
* @route '/blog'
*/
BlogController.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: BlogController.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\BlogController::__invoke
* @see app/Http/Controllers/BlogController.php:10
* @route '/blog'
*/
BlogController.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: BlogController.url(options),
    method: 'head',
})

export default BlogController