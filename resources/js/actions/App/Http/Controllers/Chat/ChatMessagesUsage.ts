import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Chat\ChatMessagesUsage::__invoke
* @see app/Http/Controllers/Chat/ChatMessagesUsage.php:10
* @route '/chat/usage'
*/
const ChatMessagesUsage = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: ChatMessagesUsage.url(options),
    method: 'get',
})

ChatMessagesUsage.definition = {
    methods: ["get","head"],
    url: '/chat/usage',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Chat\ChatMessagesUsage::__invoke
* @see app/Http/Controllers/Chat/ChatMessagesUsage.php:10
* @route '/chat/usage'
*/
ChatMessagesUsage.url = (options?: RouteQueryOptions) => {
    return ChatMessagesUsage.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Chat\ChatMessagesUsage::__invoke
* @see app/Http/Controllers/Chat/ChatMessagesUsage.php:10
* @route '/chat/usage'
*/
ChatMessagesUsage.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: ChatMessagesUsage.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Chat\ChatMessagesUsage::__invoke
* @see app/Http/Controllers/Chat/ChatMessagesUsage.php:10
* @route '/chat/usage'
*/
ChatMessagesUsage.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: ChatMessagesUsage.url(options),
    method: 'head',
})

export default ChatMessagesUsage