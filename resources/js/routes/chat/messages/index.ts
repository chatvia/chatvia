import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../wayfinder'
/**
* @see \App\Http\Controllers\Chat\ChatMessageFeedbackController::__invoke
* @see app/Http/Controllers/Chat/ChatMessageFeedbackController.php:11
* @route '/chat/messages/feedback'
*/
export const feedback = (options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: feedback.url(options),
    method: 'patch',
})

feedback.definition = {
    methods: ["patch"],
    url: '/chat/messages/feedback',
} satisfies RouteDefinition<["patch"]>

/**
* @see \App\Http\Controllers\Chat\ChatMessageFeedbackController::__invoke
* @see app/Http/Controllers/Chat/ChatMessageFeedbackController.php:11
* @route '/chat/messages/feedback'
*/
feedback.url = (options?: RouteQueryOptions) => {
    return feedback.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Chat\ChatMessageFeedbackController::__invoke
* @see app/Http/Controllers/Chat/ChatMessageFeedbackController.php:11
* @route '/chat/messages/feedback'
*/
feedback.patch = (options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: feedback.url(options),
    method: 'patch',
})

/**
* @see \App\Http\Controllers\Chat\ChatMessageDeleteController::__invoke
* @see app/Http/Controllers/Chat/ChatMessageDeleteController.php:11
* @route '/chat/messages'
*/
export const deleteMethod = (options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: deleteMethod.url(options),
    method: 'delete',
})

deleteMethod.definition = {
    methods: ["delete"],
    url: '/chat/messages',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\Chat\ChatMessageDeleteController::__invoke
* @see app/Http/Controllers/Chat/ChatMessageDeleteController.php:11
* @route '/chat/messages'
*/
deleteMethod.url = (options?: RouteQueryOptions) => {
    return deleteMethod.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Chat\ChatMessageDeleteController::__invoke
* @see app/Http/Controllers/Chat/ChatMessageDeleteController.php:11
* @route '/chat/messages'
*/
deleteMethod.delete = (options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: deleteMethod.url(options),
    method: 'delete',
})

const messages = {
    feedback: Object.assign(feedback, feedback),
    delete: Object.assign(deleteMethod, deleteMethod),
}

export default messages