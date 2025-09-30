import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Chat\ChatMessageFeedbackController::__invoke
* @see app/Http/Controllers/Chat/ChatMessageFeedbackController.php:11
* @route '/chat/messages/feedback'
*/
const ChatMessageFeedbackController = (options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: ChatMessageFeedbackController.url(options),
    method: 'patch',
})

ChatMessageFeedbackController.definition = {
    methods: ["patch"],
    url: '/chat/messages/feedback',
} satisfies RouteDefinition<["patch"]>

/**
* @see \App\Http\Controllers\Chat\ChatMessageFeedbackController::__invoke
* @see app/Http/Controllers/Chat/ChatMessageFeedbackController.php:11
* @route '/chat/messages/feedback'
*/
ChatMessageFeedbackController.url = (options?: RouteQueryOptions) => {
    return ChatMessageFeedbackController.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Chat\ChatMessageFeedbackController::__invoke
* @see app/Http/Controllers/Chat/ChatMessageFeedbackController.php:11
* @route '/chat/messages/feedback'
*/
ChatMessageFeedbackController.patch = (options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: ChatMessageFeedbackController.url(options),
    method: 'patch',
})

export default ChatMessageFeedbackController