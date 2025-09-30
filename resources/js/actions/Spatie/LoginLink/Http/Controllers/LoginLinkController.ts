import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../../../wayfinder'
/**
* @see \Spatie\LoginLink\Http\Controllers\LoginLinkController::__invoke
* @see vendor/spatie/laravel-login-link/src/Http/Controllers/LoginLinkController.php:15
* @route '/laravel-login-link-login'
*/
const LoginLinkController = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: LoginLinkController.url(options),
    method: 'post',
})

LoginLinkController.definition = {
    methods: ["post"],
    url: '/laravel-login-link-login',
} satisfies RouteDefinition<["post"]>

/**
* @see \Spatie\LoginLink\Http\Controllers\LoginLinkController::__invoke
* @see vendor/spatie/laravel-login-link/src/Http/Controllers/LoginLinkController.php:15
* @route '/laravel-login-link-login'
*/
LoginLinkController.url = (options?: RouteQueryOptions) => {
    return LoginLinkController.definition.url + queryParams(options)
}

/**
* @see \Spatie\LoginLink\Http\Controllers\LoginLinkController::__invoke
* @see vendor/spatie/laravel-login-link/src/Http/Controllers/LoginLinkController.php:15
* @route '/laravel-login-link-login'
*/
LoginLinkController.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: LoginLinkController.url(options),
    method: 'post',
})

export default LoginLinkController