import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Widget\WidgetThreadMessagesController::__invoke
* @see app/Http/Controllers/Widget/WidgetThreadMessagesController.php:10
* @route '/api/widget/threads/messages'
*/
const WidgetThreadMessagesController49a0caab64edc0a2b43768e5a3d3eda6 = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: WidgetThreadMessagesController49a0caab64edc0a2b43768e5a3d3eda6.url(options),
    method: 'post',
})

WidgetThreadMessagesController49a0caab64edc0a2b43768e5a3d3eda6.definition = {
    methods: ["post"],
    url: '/api/widget/threads/messages',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Widget\WidgetThreadMessagesController::__invoke
* @see app/Http/Controllers/Widget/WidgetThreadMessagesController.php:10
* @route '/api/widget/threads/messages'
*/
WidgetThreadMessagesController49a0caab64edc0a2b43768e5a3d3eda6.url = (options?: RouteQueryOptions) => {
    return WidgetThreadMessagesController49a0caab64edc0a2b43768e5a3d3eda6.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Widget\WidgetThreadMessagesController::__invoke
* @see app/Http/Controllers/Widget/WidgetThreadMessagesController.php:10
* @route '/api/widget/threads/messages'
*/
WidgetThreadMessagesController49a0caab64edc0a2b43768e5a3d3eda6.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: WidgetThreadMessagesController49a0caab64edc0a2b43768e5a3d3eda6.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Widget\WidgetThreadMessagesController::__invoke
* @see app/Http/Controllers/Widget/WidgetThreadMessagesController.php:10
* @route '/api/widget/threads/messages'
*/
const WidgetThreadMessagesController49a0caab64edc0a2b43768e5a3d3eda6 = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: WidgetThreadMessagesController49a0caab64edc0a2b43768e5a3d3eda6.url(options),
    method: 'get',
})

WidgetThreadMessagesController49a0caab64edc0a2b43768e5a3d3eda6.definition = {
    methods: ["get","head"],
    url: '/api/widget/threads/messages',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Widget\WidgetThreadMessagesController::__invoke
* @see app/Http/Controllers/Widget/WidgetThreadMessagesController.php:10
* @route '/api/widget/threads/messages'
*/
WidgetThreadMessagesController49a0caab64edc0a2b43768e5a3d3eda6.url = (options?: RouteQueryOptions) => {
    return WidgetThreadMessagesController49a0caab64edc0a2b43768e5a3d3eda6.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Widget\WidgetThreadMessagesController::__invoke
* @see app/Http/Controllers/Widget/WidgetThreadMessagesController.php:10
* @route '/api/widget/threads/messages'
*/
WidgetThreadMessagesController49a0caab64edc0a2b43768e5a3d3eda6.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: WidgetThreadMessagesController49a0caab64edc0a2b43768e5a3d3eda6.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Widget\WidgetThreadMessagesController::__invoke
* @see app/Http/Controllers/Widget/WidgetThreadMessagesController.php:10
* @route '/api/widget/threads/messages'
*/
WidgetThreadMessagesController49a0caab64edc0a2b43768e5a3d3eda6.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: WidgetThreadMessagesController49a0caab64edc0a2b43768e5a3d3eda6.url(options),
    method: 'head',
})

const WidgetThreadMessagesController = {
    '/api/widget/threads/messages': WidgetThreadMessagesController49a0caab64edc0a2b43768e5a3d3eda6,
    '/api/widget/threads/messages': WidgetThreadMessagesController49a0caab64edc0a2b43768e5a3d3eda6,
}

export default WidgetThreadMessagesController