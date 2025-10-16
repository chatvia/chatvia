import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\ChatPdfController::__invoke
* @see app/Http/Controllers/ChatPdfController.php:9
* @route '/free-tools/chat-with-pdf'
*/
const ChatPdfController = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: ChatPdfController.url(options),
    method: 'get',
})

ChatPdfController.definition = {
    methods: ["get","head"],
    url: '/free-tools/chat-with-pdf',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\ChatPdfController::__invoke
* @see app/Http/Controllers/ChatPdfController.php:9
* @route '/free-tools/chat-with-pdf'
*/
ChatPdfController.url = (options?: RouteQueryOptions) => {
    return ChatPdfController.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\ChatPdfController::__invoke
* @see app/Http/Controllers/ChatPdfController.php:9
* @route '/free-tools/chat-with-pdf'
*/
ChatPdfController.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: ChatPdfController.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\ChatPdfController::__invoke
* @see app/Http/Controllers/ChatPdfController.php:9
* @route '/free-tools/chat-with-pdf'
*/
ChatPdfController.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: ChatPdfController.url(options),
    method: 'head',
})

export default ChatPdfController