import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../../../wayfinder'
/**
* @see \Filament\Http\Controllers\Auth\LogoutController::__invoke
* @see vendor/filament/filament/src/Http/Controllers/Auth/LogoutController.php:10
* @route '/admin/logout'
*/
const LogoutController = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: LogoutController.url(options),
    method: 'post',
})

LogoutController.definition = {
    methods: ["post"],
    url: '/admin/logout',
} satisfies RouteDefinition<["post"]>

/**
* @see \Filament\Http\Controllers\Auth\LogoutController::__invoke
* @see vendor/filament/filament/src/Http/Controllers/Auth/LogoutController.php:10
* @route '/admin/logout'
*/
LogoutController.url = (options?: RouteQueryOptions) => {
    return LogoutController.definition.url + queryParams(options)
}

/**
* @see \Filament\Http\Controllers\Auth\LogoutController::__invoke
* @see vendor/filament/filament/src/Http/Controllers/Auth/LogoutController.php:10
* @route '/admin/logout'
*/
LogoutController.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: LogoutController.url(options),
    method: 'post',
})

export default LogoutController