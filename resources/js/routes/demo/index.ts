import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../wayfinder'
/**
* @see \App\Http\Controllers\RequestDemoController::__invoke
* @see app/Http/Controllers/RequestDemoController.php:12
* @route '/demo'
*/
export const submit = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: submit.url(options),
    method: 'post',
})

submit.definition = {
    methods: ["post"],
    url: '/demo',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\RequestDemoController::__invoke
* @see app/Http/Controllers/RequestDemoController.php:12
* @route '/demo'
*/
submit.url = (options?: RouteQueryOptions) => {
    return submit.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\RequestDemoController::__invoke
* @see app/Http/Controllers/RequestDemoController.php:12
* @route '/demo'
*/
submit.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: submit.url(options),
    method: 'post',
})

const demo = {
    submit: Object.assign(submit, submit),
}

export default demo