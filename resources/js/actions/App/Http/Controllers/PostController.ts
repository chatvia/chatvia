import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\PostController::__invoke
* @see app/Http/Controllers/PostController.php:10
* @route '/post/{post}'
*/
const PostController = (args: { post: string | { slug: string } } | [post: string | { slug: string } ] | string | { slug: string }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: PostController.url(args, options),
    method: 'get',
})

PostController.definition = {
    methods: ["get","head"],
    url: '/post/{post}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\PostController::__invoke
* @see app/Http/Controllers/PostController.php:10
* @route '/post/{post}'
*/
PostController.url = (args: { post: string | { slug: string } } | [post: string | { slug: string } ] | string | { slug: string }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { post: args }
    }

    if (typeof args === 'object' && !Array.isArray(args) && 'slug' in args) {
        args = { post: args.slug }
    }

    if (Array.isArray(args)) {
        args = {
            post: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        post: typeof args.post === 'object'
        ? args.post.slug
        : args.post,
    }

    return PostController.definition.url
            .replace('{post}', parsedArgs.post.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\PostController::__invoke
* @see app/Http/Controllers/PostController.php:10
* @route '/post/{post}'
*/
PostController.get = (args: { post: string | { slug: string } } | [post: string | { slug: string } ] | string | { slug: string }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: PostController.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\PostController::__invoke
* @see app/Http/Controllers/PostController.php:10
* @route '/post/{post}'
*/
PostController.head = (args: { post: string | { slug: string } } | [post: string | { slug: string } ] | string | { slug: string }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: PostController.url(args, options),
    method: 'head',
})

export default PostController