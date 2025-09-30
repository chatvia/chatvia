import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../../wayfinder'
/**
* @see \Filament\Pages\Auth\Login::__invoke
* @see vendor/filament/filament/src/Pages/Auth/Login.php:7
* @route '/admin/login'
*/
export const login = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: login.url(options),
    method: 'get',
})

login.definition = {
    methods: ["get","head"],
    url: '/admin/login',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \Filament\Pages\Auth\Login::__invoke
* @see vendor/filament/filament/src/Pages/Auth/Login.php:7
* @route '/admin/login'
*/
login.url = (options?: RouteQueryOptions) => {
    return login.definition.url + queryParams(options)
}

/**
* @see \Filament\Pages\Auth\Login::__invoke
* @see vendor/filament/filament/src/Pages/Auth/Login.php:7
* @route '/admin/login'
*/
login.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: login.url(options),
    method: 'get',
})

/**
* @see \Filament\Pages\Auth\Login::__invoke
* @see vendor/filament/filament/src/Pages/Auth/Login.php:7
* @route '/admin/login'
*/
login.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: login.url(options),
    method: 'head',
})

/**
* @see \Filament\Http\Controllers\Auth\LogoutController::__invoke
* @see vendor/filament/filament/src/Http/Controllers/Auth/LogoutController.php:10
* @route '/admin/logout'
*/
export const logout = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: logout.url(options),
    method: 'post',
})

logout.definition = {
    methods: ["post"],
    url: '/admin/logout',
} satisfies RouteDefinition<["post"]>

/**
* @see \Filament\Http\Controllers\Auth\LogoutController::__invoke
* @see vendor/filament/filament/src/Http/Controllers/Auth/LogoutController.php:10
* @route '/admin/logout'
*/
logout.url = (options?: RouteQueryOptions) => {
    return logout.definition.url + queryParams(options)
}

/**
* @see \Filament\Http\Controllers\Auth\LogoutController::__invoke
* @see vendor/filament/filament/src/Http/Controllers/Auth/LogoutController.php:10
* @route '/admin/logout'
*/
logout.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: logout.url(options),
    method: 'post',
})

const auth = {
    login: Object.assign(login, login),
    logout: Object.assign(logout, logout),
}

export default auth