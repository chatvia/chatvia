import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../wayfinder'
/**
* @see \App\Http\Controllers\Widget\WidgetAgentVerifyController::__invoke
* @see app/Http/Controllers/Widget/WidgetAgentVerifyController.php:10
* @route '/api/widget/agents/verify'
*/
export const verify = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: verify.url(options),
    method: 'get',
})

verify.definition = {
    methods: ["get","head"],
    url: '/api/widget/agents/verify',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Widget\WidgetAgentVerifyController::__invoke
* @see app/Http/Controllers/Widget/WidgetAgentVerifyController.php:10
* @route '/api/widget/agents/verify'
*/
verify.url = (options?: RouteQueryOptions) => {
    return verify.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Widget\WidgetAgentVerifyController::__invoke
* @see app/Http/Controllers/Widget/WidgetAgentVerifyController.php:10
* @route '/api/widget/agents/verify'
*/
verify.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: verify.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Widget\WidgetAgentVerifyController::__invoke
* @see app/Http/Controllers/Widget/WidgetAgentVerifyController.php:10
* @route '/api/widget/agents/verify'
*/
verify.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: verify.url(options),
    method: 'head',
})

const agents = {
    verify: Object.assign(verify, verify),
}

export default agents