import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../wayfinder'
import embed from './embed'
import models from './models'
import member from './member'
import messages from './messages'
import profile from './profile'
/**
* @see \App\Http\Controllers\Chat\ChatAppearanceController::__invoke
* @see app/Http/Controllers/Chat/ChatAppearanceController.php:7
* @route '/chat/appearance'
*/
export const appearance = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: appearance.url(options),
    method: 'get',
})

appearance.definition = {
    methods: ["get","head"],
    url: '/chat/appearance',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Chat\ChatAppearanceController::__invoke
* @see app/Http/Controllers/Chat/ChatAppearanceController.php:7
* @route '/chat/appearance'
*/
appearance.url = (options?: RouteQueryOptions) => {
    return appearance.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Chat\ChatAppearanceController::__invoke
* @see app/Http/Controllers/Chat/ChatAppearanceController.php:7
* @route '/chat/appearance'
*/
appearance.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: appearance.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Chat\ChatAppearanceController::__invoke
* @see app/Http/Controllers/Chat/ChatAppearanceController.php:7
* @route '/chat/appearance'
*/
appearance.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: appearance.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Chat\Auth\ChatLoginController::__invoke
* @see app/Http/Controllers/Chat/Auth/ChatLoginController.php:10
* @route '/chat/login'
*/
export const login = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: login.url(options),
    method: 'post',
})

login.definition = {
    methods: ["post"],
    url: '/chat/login',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Chat\Auth\ChatLoginController::__invoke
* @see app/Http/Controllers/Chat/Auth/ChatLoginController.php:10
* @route '/chat/login'
*/
login.url = (options?: RouteQueryOptions) => {
    return login.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Chat\Auth\ChatLoginController::__invoke
* @see app/Http/Controllers/Chat/Auth/ChatLoginController.php:10
* @route '/chat/login'
*/
login.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: login.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Chat\ChatHomeController::__invoke
* @see app/Http/Controllers/Chat/ChatHomeController.php:11
* @route '/chat'
*/
export const home = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: home.url(options),
    method: 'get',
})

home.definition = {
    methods: ["get","head"],
    url: '/chat',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Chat\ChatHomeController::__invoke
* @see app/Http/Controllers/Chat/ChatHomeController.php:11
* @route '/chat'
*/
home.url = (options?: RouteQueryOptions) => {
    return home.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Chat\ChatHomeController::__invoke
* @see app/Http/Controllers/Chat/ChatHomeController.php:11
* @route '/chat'
*/
home.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: home.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Chat\ChatHomeController::__invoke
* @see app/Http/Controllers/Chat/ChatHomeController.php:11
* @route '/chat'
*/
home.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: home.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Chat\ChatStreamController::__invoke
* @see app/Http/Controllers/Chat/ChatStreamController.php:17
* @route '/chat/stream/{conversationId}'
*/
export const stream = (args: { conversationId: string | number } | [conversationId: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: stream.url(args, options),
    method: 'post',
})

stream.definition = {
    methods: ["post"],
    url: '/chat/stream/{conversationId}',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Chat\ChatStreamController::__invoke
* @see app/Http/Controllers/Chat/ChatStreamController.php:17
* @route '/chat/stream/{conversationId}'
*/
stream.url = (args: { conversationId: string | number } | [conversationId: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return stream.definition.url
            .replace('{conversationId}', parsedArgs.conversationId.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Chat\ChatStreamController::__invoke
* @see app/Http/Controllers/Chat/ChatStreamController.php:17
* @route '/chat/stream/{conversationId}'
*/
stream.post = (args: { conversationId: string | number } | [conversationId: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: stream.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Chat\Auth\ChatLogoutController::__invoke
* @see app/Http/Controllers/Chat/Auth/ChatLogoutController.php:11
* @route '/chat/logout'
*/
export const logout = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: logout.url(options),
    method: 'post',
})

logout.definition = {
    methods: ["post"],
    url: '/chat/logout',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Chat\Auth\ChatLogoutController::__invoke
* @see app/Http/Controllers/Chat/Auth/ChatLogoutController.php:11
* @route '/chat/logout'
*/
logout.url = (options?: RouteQueryOptions) => {
    return logout.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Chat\Auth\ChatLogoutController::__invoke
* @see app/Http/Controllers/Chat/Auth/ChatLogoutController.php:11
* @route '/chat/logout'
*/
logout.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: logout.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Chat\ChatStartersController::__invoke
* @see app/Http/Controllers/Chat/ChatStartersController.php:10
* @route '/chat/starters'
*/
export const starters = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: starters.url(options),
    method: 'get',
})

starters.definition = {
    methods: ["get","head"],
    url: '/chat/starters',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Chat\ChatStartersController::__invoke
* @see app/Http/Controllers/Chat/ChatStartersController.php:10
* @route '/chat/starters'
*/
starters.url = (options?: RouteQueryOptions) => {
    return starters.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Chat\ChatStartersController::__invoke
* @see app/Http/Controllers/Chat/ChatStartersController.php:10
* @route '/chat/starters'
*/
starters.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: starters.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Chat\ChatStartersController::__invoke
* @see app/Http/Controllers/Chat/ChatStartersController.php:10
* @route '/chat/starters'
*/
starters.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: starters.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Chat\ChatSourcesController::__invoke
* @see app/Http/Controllers/Chat/ChatSourcesController.php:11
* @route '/chat/conversations/{conversationId}/sources'
*/
export const sources = (args: { conversationId: string | number } | [conversationId: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: sources.url(args, options),
    method: 'get',
})

sources.definition = {
    methods: ["get","head"],
    url: '/chat/conversations/{conversationId}/sources',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Chat\ChatSourcesController::__invoke
* @see app/Http/Controllers/Chat/ChatSourcesController.php:11
* @route '/chat/conversations/{conversationId}/sources'
*/
sources.url = (args: { conversationId: string | number } | [conversationId: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return sources.definition.url
            .replace('{conversationId}', parsedArgs.conversationId.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Chat\ChatSourcesController::__invoke
* @see app/Http/Controllers/Chat/ChatSourcesController.php:11
* @route '/chat/conversations/{conversationId}/sources'
*/
sources.get = (args: { conversationId: string | number } | [conversationId: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: sources.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Chat\ChatSourcesController::__invoke
* @see app/Http/Controllers/Chat/ChatSourcesController.php:11
* @route '/chat/conversations/{conversationId}/sources'
*/
sources.head = (args: { conversationId: string | number } | [conversationId: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: sources.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Chat\ChatController::__invoke
* @see app/Http/Controllers/Chat/ChatController.php:10
* @route '/chat/c/{conversationId}'
*/
export const show = (args: { conversationId: string | number } | [conversationId: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})

show.definition = {
    methods: ["get","head"],
    url: '/chat/c/{conversationId}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Chat\ChatController::__invoke
* @see app/Http/Controllers/Chat/ChatController.php:10
* @route '/chat/c/{conversationId}'
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
* @see \App\Http\Controllers\Chat\ChatController::__invoke
* @see app/Http/Controllers/Chat/ChatController.php:10
* @route '/chat/c/{conversationId}'
*/
show.get = (args: { conversationId: string | number } | [conversationId: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Chat\ChatController::__invoke
* @see app/Http/Controllers/Chat/ChatController.php:10
* @route '/chat/c/{conversationId}'
*/
show.head = (args: { conversationId: string | number } | [conversationId: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: show.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Chat\ChatMessagesUsage::__invoke
* @see app/Http/Controllers/Chat/ChatMessagesUsage.php:10
* @route '/chat/usage'
*/
export const usage = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: usage.url(options),
    method: 'get',
})

usage.definition = {
    methods: ["get","head"],
    url: '/chat/usage',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Chat\ChatMessagesUsage::__invoke
* @see app/Http/Controllers/Chat/ChatMessagesUsage.php:10
* @route '/chat/usage'
*/
usage.url = (options?: RouteQueryOptions) => {
    return usage.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Chat\ChatMessagesUsage::__invoke
* @see app/Http/Controllers/Chat/ChatMessagesUsage.php:10
* @route '/chat/usage'
*/
usage.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: usage.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Chat\ChatMessagesUsage::__invoke
* @see app/Http/Controllers/Chat/ChatMessagesUsage.php:10
* @route '/chat/usage'
*/
usage.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: usage.url(options),
    method: 'head',
})

const chat = {
    appearance: Object.assign(appearance, appearance),
    login: Object.assign(login, login),
    home: Object.assign(home, home),
    embed: Object.assign(embed, embed),
    models: Object.assign(models, models),
    member: Object.assign(member, member),
    stream: Object.assign(stream, stream),
    logout: Object.assign(logout, logout),
    starters: Object.assign(starters, starters),
    messages: Object.assign(messages, messages),
    sources: Object.assign(sources, sources),
    show: Object.assign(show, show),
    profile: Object.assign(profile, profile),
    usage: Object.assign(usage, usage),
}

export default chat