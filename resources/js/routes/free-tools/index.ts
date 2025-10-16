import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../wayfinder'
/**
* @see \App\Http\Controllers\ChatPdfController::__invoke
* @see app/Http/Controllers/ChatPdfController.php:9
* @route '/free-tools/chat-with-pdf'
*/
export const chatWithPdf = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: chatWithPdf.url(options),
    method: 'get',
})

chatWithPdf.definition = {
    methods: ["get","head"],
    url: '/free-tools/chat-with-pdf',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\ChatPdfController::__invoke
* @see app/Http/Controllers/ChatPdfController.php:9
* @route '/free-tools/chat-with-pdf'
*/
chatWithPdf.url = (options?: RouteQueryOptions) => {
    return chatWithPdf.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\ChatPdfController::__invoke
* @see app/Http/Controllers/ChatPdfController.php:9
* @route '/free-tools/chat-with-pdf'
*/
chatWithPdf.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: chatWithPdf.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\ChatPdfController::__invoke
* @see app/Http/Controllers/ChatPdfController.php:9
* @route '/free-tools/chat-with-pdf'
*/
chatWithPdf.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: chatWithPdf.url(options),
    method: 'head',
})

const freeTools = {
    chatWithPdf: Object.assign(chatWithPdf, chatWithPdf),
}

export default freeTools