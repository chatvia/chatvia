import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../wayfinder'
/**
* @see \App\Http\Controllers\Chat\ChatEmbedHomeController::__invoke
* @see app/Http/Controllers/Chat/ChatEmbedHomeController.php:9
* @route '/chat/embed'
*/
export const home = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: home.url(options),
    method: 'get',
})

home.definition = {
    methods: ["get","head"],
    url: '/chat/embed',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Chat\ChatEmbedHomeController::__invoke
* @see app/Http/Controllers/Chat/ChatEmbedHomeController.php:9
* @route '/chat/embed'
*/
home.url = (options?: RouteQueryOptions) => {
    return home.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Chat\ChatEmbedHomeController::__invoke
* @see app/Http/Controllers/Chat/ChatEmbedHomeController.php:9
* @route '/chat/embed'
*/
home.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: home.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Chat\ChatEmbedHomeController::__invoke
* @see app/Http/Controllers/Chat/ChatEmbedHomeController.php:9
* @route '/chat/embed'
*/
home.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: home.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Chat\ChatEmbedController::__invoke
* @see app/Http/Controllers/Chat/ChatEmbedController.php:9
* @route '/chat/embed/c/{conversationId}'
*/
export const show = (args: { conversationId: string | number } | [conversationId: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})

show.definition = {
    methods: ["get","head"],
    url: '/chat/embed/c/{conversationId}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Chat\ChatEmbedController::__invoke
* @see app/Http/Controllers/Chat/ChatEmbedController.php:9
* @route '/chat/embed/c/{conversationId}'
*/
show.url = (args: { conversationId: string | number } | [conversationId: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { conversationId: args }
    }

    if (Array.isArray(args)) {
        args = {
            conversationId: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        conversationId: args.conversationId,
    }

    return show.definition.url
            .replace('{conversationId}', parsedArgs.conversationId.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Chat\ChatEmbedController::__invoke
* @see app/Http/Controllers/Chat/ChatEmbedController.php:9
* @route '/chat/embed/c/{conversationId}'
*/
show.get = (args: { conversationId: string | number } | [conversationId: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Chat\ChatEmbedController::__invoke
* @see app/Http/Controllers/Chat/ChatEmbedController.php:9
* @route '/chat/embed/c/{conversationId}'
*/
show.head = (args: { conversationId: string | number } | [conversationId: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: show.url(args, options),
    method: 'head',
})

const embed = {
    home: Object.assign(home, home),
    show: Object.assign(show, show),
}

export default embed