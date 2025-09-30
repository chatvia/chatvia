import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../wayfinder'
import threadsCbddcd from './threads'
import agents from './agents'
import leads from './leads'
/**
* @see \App\Http\Controllers\Widget\WidgetSessionStoreController::__invoke
* @see app/Http/Controllers/Widget/WidgetSessionStoreController.php:11
* @route '/api/widget/sessions'
*/
export const sessions = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: sessions.url(options),
    method: 'post',
})

sessions.definition = {
    methods: ["post"],
    url: '/api/widget/sessions',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Widget\WidgetSessionStoreController::__invoke
* @see app/Http/Controllers/Widget/WidgetSessionStoreController.php:11
* @route '/api/widget/sessions'
*/
sessions.url = (options?: RouteQueryOptions) => {
    return sessions.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Widget\WidgetSessionStoreController::__invoke
* @see app/Http/Controllers/Widget/WidgetSessionStoreController.php:11
* @route '/api/widget/sessions'
*/
sessions.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: sessions.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Widget\WidgetThreadsController::__invoke
* @see app/Http/Controllers/Widget/WidgetThreadsController.php:12
* @route '/api/widget/threads'
*/
export const threads = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: threads.url(options),
    method: 'get',
})

threads.definition = {
    methods: ["get","head"],
    url: '/api/widget/threads',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Widget\WidgetThreadsController::__invoke
* @see app/Http/Controllers/Widget/WidgetThreadsController.php:12
* @route '/api/widget/threads'
*/
threads.url = (options?: RouteQueryOptions) => {
    return threads.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Widget\WidgetThreadsController::__invoke
* @see app/Http/Controllers/Widget/WidgetThreadsController.php:12
* @route '/api/widget/threads'
*/
threads.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: threads.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Widget\WidgetThreadsController::__invoke
* @see app/Http/Controllers/Widget/WidgetThreadsController.php:12
* @route '/api/widget/threads'
*/
threads.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: threads.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Widget\WidgetStreamController::__invoke
* @see app/Http/Controllers/Widget/WidgetStreamController.php:15
* @route '/api/widget/stream'
*/
export const stream = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: stream.url(options),
    method: 'post',
})

stream.definition = {
    methods: ["post"],
    url: '/api/widget/stream',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Widget\WidgetStreamController::__invoke
* @see app/Http/Controllers/Widget/WidgetStreamController.php:15
* @route '/api/widget/stream'
*/
stream.url = (options?: RouteQueryOptions) => {
    return stream.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Widget\WidgetStreamController::__invoke
* @see app/Http/Controllers/Widget/WidgetStreamController.php:15
* @route '/api/widget/stream'
*/
stream.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: stream.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Widget\WidgetSettingsController::__invoke
* @see app/Http/Controllers/Widget/WidgetSettingsController.php:10
* @route '/api/widget/agents/settings'
*/
export const settings = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: settings.url(options),
    method: 'get',
})

settings.definition = {
    methods: ["get","head"],
    url: '/api/widget/agents/settings',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Widget\WidgetSettingsController::__invoke
* @see app/Http/Controllers/Widget/WidgetSettingsController.php:10
* @route '/api/widget/agents/settings'
*/
settings.url = (options?: RouteQueryOptions) => {
    return settings.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Widget\WidgetSettingsController::__invoke
* @see app/Http/Controllers/Widget/WidgetSettingsController.php:10
* @route '/api/widget/agents/settings'
*/
settings.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: settings.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Widget\WidgetSettingsController::__invoke
* @see app/Http/Controllers/Widget/WidgetSettingsController.php:10
* @route '/api/widget/agents/settings'
*/
settings.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: settings.url(options),
    method: 'head',
})

const widget = {
    sessions: Object.assign(sessions, sessions),
    threads: Object.assign(threads, threadsCbddcd),
    agents: Object.assign(agents, agents),
    stream: Object.assign(stream, stream),
    settings: Object.assign(settings, settings),
    leads: Object.assign(leads, leads),
}

export default widget