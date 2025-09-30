import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\FeaturesController::__invoke
* @see app/Http/Controllers/FeaturesController.php:13
* @route '/dashboard/features'
*/
const FeaturesController = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: FeaturesController.url(options),
    method: 'get',
})

FeaturesController.definition = {
    methods: ["get","head"],
    url: '/dashboard/features',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\FeaturesController::__invoke
* @see app/Http/Controllers/FeaturesController.php:13
* @route '/dashboard/features'
*/
FeaturesController.url = (options?: RouteQueryOptions) => {
    return FeaturesController.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\FeaturesController::__invoke
* @see app/Http/Controllers/FeaturesController.php:13
* @route '/dashboard/features'
*/
FeaturesController.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: FeaturesController.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\FeaturesController::__invoke
* @see app/Http/Controllers/FeaturesController.php:13
* @route '/dashboard/features'
*/
FeaturesController.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: FeaturesController.url(options),
    method: 'head',
})

export default FeaturesController