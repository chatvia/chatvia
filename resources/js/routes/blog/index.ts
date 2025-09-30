import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../wayfinder'
/**
* @see \App\Http\Controllers\PostController::__invoke
* @see app/Http/Controllers/PostController.php:10
* @route '/post/{post}'
*/
export const post = (args: { post: string | { slug: string } } | [post: string | { slug: string } ] | string | { slug: string }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: post.url(args, options),
    method: 'get',
})

post.definition = {
    methods: ["get","head"],
    url: '/post/{post}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\PostController::__invoke
* @see app/Http/Controllers/PostController.php:10
* @route '/post/{post}'
*/
post.url = (args: { post: string | { slug: string } } | [post: string | { slug: string } ] | string | { slug: string }, options?: RouteQueryOptions) => {
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

    return post.definition.url
            .replace('{post}', parsedArgs.post.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\PostController::__invoke
* @see app/Http/Controllers/PostController.php:10
* @route '/post/{post}'
*/
post.get = (args: { post: string | { slug: string } } | [post: string | { slug: string } ] | string | { slug: string }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: post.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\PostController::__invoke
* @see app/Http/Controllers/PostController.php:10
* @route '/post/{post}'
*/
post.head = (args: { post: string | { slug: string } } | [post: string | { slug: string } ] | string | { slug: string }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: post.url(args, options),
    method: 'head',
})

const blog = {
    post: Object.assign(post, post),
}

export default blog