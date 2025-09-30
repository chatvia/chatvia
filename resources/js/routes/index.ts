import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults, validateParameters } from './../wayfinder'
/**
* @see vendor/laravel/pulse/src/PulseServiceProvider.php:106
* @route '/pulse'
*/
export const pulse = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: pulse.url(options),
    method: 'get',
})

pulse.definition = {
    methods: ["get","head"],
    url: '/pulse',
} satisfies RouteDefinition<["get","head"]>

/**
* @see vendor/laravel/pulse/src/PulseServiceProvider.php:106
* @route '/pulse'
*/
pulse.url = (options?: RouteQueryOptions) => {
    return pulse.definition.url + queryParams(options)
}

/**
* @see vendor/laravel/pulse/src/PulseServiceProvider.php:106
* @route '/pulse'
*/
pulse.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: pulse.url(options),
    method: 'get',
})

/**
* @see vendor/laravel/pulse/src/PulseServiceProvider.php:106
* @route '/pulse'
*/
pulse.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: pulse.url(options),
    method: 'head',
})

/**
* @see \Laravel\Telescope\Http\Controllers\HomeController::telescope
* @see vendor/laravel/telescope/src/Http/Controllers/HomeController.php:15
* @route '/telescope/{view?}'
*/
export const telescope = (args?: { view?: string | number } | [view: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: telescope.url(args, options),
    method: 'get',
})

telescope.definition = {
    methods: ["get","head"],
    url: '/telescope/{view?}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \Laravel\Telescope\Http\Controllers\HomeController::telescope
* @see vendor/laravel/telescope/src/Http/Controllers/HomeController.php:15
* @route '/telescope/{view?}'
*/
telescope.url = (args?: { view?: string | number } | [view: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { view: args }
    }

    if (Array.isArray(args)) {
        args = {
            view: args[0],
        }
    }

    args = applyUrlDefaults(args)

    validateParameters(args, [
        "view",
    ])

    const parsedArgs = {
        view: args?.view,
    }

    return telescope.definition.url
            .replace('{view?}', parsedArgs.view?.toString() ?? '')
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \Laravel\Telescope\Http\Controllers\HomeController::telescope
* @see vendor/laravel/telescope/src/Http/Controllers/HomeController.php:15
* @route '/telescope/{view?}'
*/
telescope.get = (args?: { view?: string | number } | [view: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: telescope.url(args, options),
    method: 'get',
})

/**
* @see \Laravel\Telescope\Http\Controllers\HomeController::telescope
* @see vendor/laravel/telescope/src/Http/Controllers/HomeController.php:15
* @route '/telescope/{view?}'
*/
telescope.head = (args?: { view?: string | number } | [view: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: telescope.url(args, options),
    method: 'head',
})

/**
* @see \Spatie\LoginLink\Http\Controllers\LoginLinkController::__invoke
* @see vendor/spatie/laravel-login-link/src/Http/Controllers/LoginLinkController.php:15
* @route '/laravel-login-link-login'
*/
export const loginLinkLogin = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: loginLinkLogin.url(options),
    method: 'post',
})

loginLinkLogin.definition = {
    methods: ["post"],
    url: '/laravel-login-link-login',
} satisfies RouteDefinition<["post"]>

/**
* @see \Spatie\LoginLink\Http\Controllers\LoginLinkController::__invoke
* @see vendor/spatie/laravel-login-link/src/Http/Controllers/LoginLinkController.php:15
* @route '/laravel-login-link-login'
*/
loginLinkLogin.url = (options?: RouteQueryOptions) => {
    return loginLinkLogin.definition.url + queryParams(options)
}

/**
* @see \Spatie\LoginLink\Http\Controllers\LoginLinkController::__invoke
* @see vendor/spatie/laravel-login-link/src/Http/Controllers/LoginLinkController.php:15
* @route '/laravel-login-link-login'
*/
loginLinkLogin.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: loginLinkLogin.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\HomeController::__invoke
* @see app/Http/Controllers/HomeController.php:11
* @route '/'
*/
export const home = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: home.url(options),
    method: 'get',
})

home.definition = {
    methods: ["get","head"],
    url: '/',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\HomeController::__invoke
* @see app/Http/Controllers/HomeController.php:11
* @route '/'
*/
home.url = (options?: RouteQueryOptions) => {
    return home.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\HomeController::__invoke
* @see app/Http/Controllers/HomeController.php:11
* @route '/'
*/
home.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: home.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\HomeController::__invoke
* @see app/Http/Controllers/HomeController.php:11
* @route '/'
*/
home.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: home.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Landing\PrivacyController::__invoke
* @see app/Http/Controllers/Landing/PrivacyController.php:9
* @route '/privacy-policy'
*/
export const privacy = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: privacy.url(options),
    method: 'get',
})

privacy.definition = {
    methods: ["get","head"],
    url: '/privacy-policy',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Landing\PrivacyController::__invoke
* @see app/Http/Controllers/Landing/PrivacyController.php:9
* @route '/privacy-policy'
*/
privacy.url = (options?: RouteQueryOptions) => {
    return privacy.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Landing\PrivacyController::__invoke
* @see app/Http/Controllers/Landing/PrivacyController.php:9
* @route '/privacy-policy'
*/
privacy.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: privacy.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Landing\PrivacyController::__invoke
* @see app/Http/Controllers/Landing/PrivacyController.php:9
* @route '/privacy-policy'
*/
privacy.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: privacy.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Landing\TermsController::__invoke
* @see app/Http/Controllers/Landing/TermsController.php:9
* @route '/terms-of-service'
*/
export const terms = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: terms.url(options),
    method: 'get',
})

terms.definition = {
    methods: ["get","head"],
    url: '/terms-of-service',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Landing\TermsController::__invoke
* @see app/Http/Controllers/Landing/TermsController.php:9
* @route '/terms-of-service'
*/
terms.url = (options?: RouteQueryOptions) => {
    return terms.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Landing\TermsController::__invoke
* @see app/Http/Controllers/Landing/TermsController.php:9
* @route '/terms-of-service'
*/
terms.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: terms.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Landing\TermsController::__invoke
* @see app/Http/Controllers/Landing/TermsController.php:9
* @route '/terms-of-service'
*/
terms.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: terms.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Landing\RefundController::__invoke
* @see app/Http/Controllers/Landing/RefundController.php:9
* @route '/refund'
*/
export const refund = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: refund.url(options),
    method: 'get',
})

refund.definition = {
    methods: ["get","head"],
    url: '/refund',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Landing\RefundController::__invoke
* @see app/Http/Controllers/Landing/RefundController.php:9
* @route '/refund'
*/
refund.url = (options?: RouteQueryOptions) => {
    return refund.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Landing\RefundController::__invoke
* @see app/Http/Controllers/Landing/RefundController.php:9
* @route '/refund'
*/
refund.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: refund.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Landing\RefundController::__invoke
* @see app/Http/Controllers/Landing/RefundController.php:9
* @route '/refund'
*/
refund.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: refund.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Landing\ChangelogController::__invoke
* @see app/Http/Controllers/Landing/ChangelogController.php:13
* @route '/changelog'
*/
export const changelog = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: changelog.url(options),
    method: 'get',
})

changelog.definition = {
    methods: ["get","head"],
    url: '/changelog',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Landing\ChangelogController::__invoke
* @see app/Http/Controllers/Landing/ChangelogController.php:13
* @route '/changelog'
*/
changelog.url = (options?: RouteQueryOptions) => {
    return changelog.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Landing\ChangelogController::__invoke
* @see app/Http/Controllers/Landing/ChangelogController.php:13
* @route '/changelog'
*/
changelog.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: changelog.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Landing\ChangelogController::__invoke
* @see app/Http/Controllers/Landing/ChangelogController.php:13
* @route '/changelog'
*/
changelog.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: changelog.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\BlogController::__invoke
* @see app/Http/Controllers/BlogController.php:10
* @route '/blog'
*/
export const blog = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: blog.url(options),
    method: 'get',
})

blog.definition = {
    methods: ["get","head"],
    url: '/blog',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\BlogController::__invoke
* @see app/Http/Controllers/BlogController.php:10
* @route '/blog'
*/
blog.url = (options?: RouteQueryOptions) => {
    return blog.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\BlogController::__invoke
* @see app/Http/Controllers/BlogController.php:10
* @route '/blog'
*/
blog.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: blog.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\BlogController::__invoke
* @see app/Http/Controllers/BlogController.php:10
* @route '/blog'
*/
blog.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: blog.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\RequestDemoController::__invoke
* @see app/Http/Controllers/RequestDemoController.php:12
* @route '/demo'
*/
export const demo = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: demo.url(options),
    method: 'get',
})

demo.definition = {
    methods: ["get","head"],
    url: '/demo',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\RequestDemoController::__invoke
* @see app/Http/Controllers/RequestDemoController.php:12
* @route '/demo'
*/
demo.url = (options?: RouteQueryOptions) => {
    return demo.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\RequestDemoController::__invoke
* @see app/Http/Controllers/RequestDemoController.php:12
* @route '/demo'
*/
demo.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: demo.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\RequestDemoController::__invoke
* @see app/Http/Controllers/RequestDemoController.php:12
* @route '/demo'
*/
demo.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: demo.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\DocsController::__invoke
* @see app/Http/Controllers/DocsController.php:12
* @route '/docs'
*/
export const docs = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: docs.url(options),
    method: 'get',
})

docs.definition = {
    methods: ["get","head"],
    url: '/docs',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\DocsController::__invoke
* @see app/Http/Controllers/DocsController.php:12
* @route '/docs'
*/
docs.url = (options?: RouteQueryOptions) => {
    return docs.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\DocsController::__invoke
* @see app/Http/Controllers/DocsController.php:12
* @route '/docs'
*/
docs.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: docs.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\DocsController::__invoke
* @see app/Http/Controllers/DocsController.php:12
* @route '/docs'
*/
docs.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: docs.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\UseCasesController::__invoke
* @see app/Http/Controllers/UseCasesController.php:10
* @route '/use-cases/{case}'
*/
export const useCases = (args: { case: string | number } | [caseParam: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: useCases.url(args, options),
    method: 'get',
})

useCases.definition = {
    methods: ["get","head"],
    url: '/use-cases/{case}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\UseCasesController::__invoke
* @see app/Http/Controllers/UseCasesController.php:10
* @route '/use-cases/{case}'
*/
useCases.url = (args: { case: string | number } | [caseParam: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { case: args }
    }

    if (Array.isArray(args)) {
        args = {
            case: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        case: args.case,
    }

    return useCases.definition.url
            .replace('{case}', parsedArgs.case.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\UseCasesController::__invoke
* @see app/Http/Controllers/UseCasesController.php:10
* @route '/use-cases/{case}'
*/
useCases.get = (args: { case: string | number } | [caseParam: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: useCases.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\UseCasesController::__invoke
* @see app/Http/Controllers/UseCasesController.php:10
* @route '/use-cases/{case}'
*/
useCases.head = (args: { case: string | number } | [caseParam: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: useCases.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Dashboard\DashboardController::__invoke
* @see app/Http/Controllers/Dashboard/DashboardController.php:9
* @route '/dashboard'
*/
export const dashboard = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: dashboard.url(options),
    method: 'get',
})

dashboard.definition = {
    methods: ["get","head"],
    url: '/dashboard',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Dashboard\DashboardController::__invoke
* @see app/Http/Controllers/Dashboard/DashboardController.php:9
* @route '/dashboard'
*/
dashboard.url = (options?: RouteQueryOptions) => {
    return dashboard.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Dashboard\DashboardController::__invoke
* @see app/Http/Controllers/Dashboard/DashboardController.php:9
* @route '/dashboard'
*/
dashboard.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: dashboard.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Dashboard\DashboardController::__invoke
* @see app/Http/Controllers/Dashboard/DashboardController.php:9
* @route '/dashboard'
*/
dashboard.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: dashboard.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\UsageController::__invoke
* @see app/Http/Controllers/UsageController.php:12
* @route '/dashboard/usage'
*/
export const usage = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: usage.url(options),
    method: 'get',
})

usage.definition = {
    methods: ["get","head"],
    url: '/dashboard/usage',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\UsageController::__invoke
* @see app/Http/Controllers/UsageController.php:12
* @route '/dashboard/usage'
*/
usage.url = (options?: RouteQueryOptions) => {
    return usage.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\UsageController::__invoke
* @see app/Http/Controllers/UsageController.php:12
* @route '/dashboard/usage'
*/
usage.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: usage.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\UsageController::__invoke
* @see app/Http/Controllers/UsageController.php:12
* @route '/dashboard/usage'
*/
usage.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: usage.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\SupportController::__invoke
* @see app/Http/Controllers/SupportController.php:10
* @route '/dashboard/support'
*/
export const support = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: support.url(options),
    method: 'get',
})

support.definition = {
    methods: ["get","head"],
    url: '/dashboard/support',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\SupportController::__invoke
* @see app/Http/Controllers/SupportController.php:10
* @route '/dashboard/support'
*/
support.url = (options?: RouteQueryOptions) => {
    return support.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\SupportController::__invoke
* @see app/Http/Controllers/SupportController.php:10
* @route '/dashboard/support'
*/
support.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: support.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\SupportController::__invoke
* @see app/Http/Controllers/SupportController.php:10
* @route '/dashboard/support'
*/
support.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: support.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Tickets\TicketsController::__invoke
* @see app/Http/Controllers/Tickets/TicketsController.php:9
* @route '/dashboard/tickets'
*/
export const tickets = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: tickets.url(options),
    method: 'get',
})

tickets.definition = {
    methods: ["get","head"],
    url: '/dashboard/tickets',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Tickets\TicketsController::__invoke
* @see app/Http/Controllers/Tickets/TicketsController.php:9
* @route '/dashboard/tickets'
*/
tickets.url = (options?: RouteQueryOptions) => {
    return tickets.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Tickets\TicketsController::__invoke
* @see app/Http/Controllers/Tickets/TicketsController.php:9
* @route '/dashboard/tickets'
*/
tickets.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: tickets.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Tickets\TicketsController::__invoke
* @see app/Http/Controllers/Tickets/TicketsController.php:9
* @route '/dashboard/tickets'
*/
tickets.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: tickets.url(options),
    method: 'head',
})

/**
* @see routes/settings.php:21
* @route '/settings/appearance'
*/
export const appearance = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: appearance.url(options),
    method: 'get',
})

appearance.definition = {
    methods: ["get","head"],
    url: '/settings/appearance',
} satisfies RouteDefinition<["get","head"]>

/**
* @see routes/settings.php:21
* @route '/settings/appearance'
*/
appearance.url = (options?: RouteQueryOptions) => {
    return appearance.definition.url + queryParams(options)
}

/**
* @see routes/settings.php:21
* @route '/settings/appearance'
*/
appearance.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: appearance.url(options),
    method: 'get',
})

/**
* @see routes/settings.php:21
* @route '/settings/appearance'
*/
appearance.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: appearance.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Auth\RegisteredUserController::register
* @see app/Http/Controllers/Auth/RegisteredUserController.php:19
* @route '/register'
*/
export const register = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: register.url(options),
    method: 'get',
})

register.definition = {
    methods: ["get","head"],
    url: '/register',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Auth\RegisteredUserController::register
* @see app/Http/Controllers/Auth/RegisteredUserController.php:19
* @route '/register'
*/
register.url = (options?: RouteQueryOptions) => {
    return register.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Auth\RegisteredUserController::register
* @see app/Http/Controllers/Auth/RegisteredUserController.php:19
* @route '/register'
*/
register.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: register.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Auth\RegisteredUserController::register
* @see app/Http/Controllers/Auth/RegisteredUserController.php:19
* @route '/register'
*/
register.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: register.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Auth\AuthenticatedSessionController::login
* @see app/Http/Controllers/Auth/AuthenticatedSessionController.php:19
* @route '/login'
*/
export const login = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: login.url(options),
    method: 'get',
})

login.definition = {
    methods: ["get","head"],
    url: '/login',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Auth\AuthenticatedSessionController::login
* @see app/Http/Controllers/Auth/AuthenticatedSessionController.php:19
* @route '/login'
*/
login.url = (options?: RouteQueryOptions) => {
    return login.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Auth\AuthenticatedSessionController::login
* @see app/Http/Controllers/Auth/AuthenticatedSessionController.php:19
* @route '/login'
*/
login.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: login.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Auth\AuthenticatedSessionController::login
* @see app/Http/Controllers/Auth/AuthenticatedSessionController.php:19
* @route '/login'
*/
login.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: login.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Auth\AuthenticatedSessionController::logout
* @see app/Http/Controllers/Auth/AuthenticatedSessionController.php:42
* @route '/logout'
*/
export const logout = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: logout.url(options),
    method: 'post',
})

logout.definition = {
    methods: ["post"],
    url: '/logout',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Auth\AuthenticatedSessionController::logout
* @see app/Http/Controllers/Auth/AuthenticatedSessionController.php:42
* @route '/logout'
*/
logout.url = (options?: RouteQueryOptions) => {
    return logout.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Auth\AuthenticatedSessionController::logout
* @see app/Http/Controllers/Auth/AuthenticatedSessionController.php:42
* @route '/logout'
*/
logout.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: logout.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Agents\Agents\AgentsGenerateRandomSubDomainController::__invoke
* @see app/Http/Controllers/Agents/Agents/AgentsGenerateRandomSubDomainController.php:10
* @route '/generate-random-subdomain'
*/
export const generateRandomSubdomain = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: generateRandomSubdomain.url(options),
    method: 'get',
})

generateRandomSubdomain.definition = {
    methods: ["get","head"],
    url: '/generate-random-subdomain',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Agents\Agents\AgentsGenerateRandomSubDomainController::__invoke
* @see app/Http/Controllers/Agents/Agents/AgentsGenerateRandomSubDomainController.php:10
* @route '/generate-random-subdomain'
*/
generateRandomSubdomain.url = (options?: RouteQueryOptions) => {
    return generateRandomSubdomain.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Agents\Agents\AgentsGenerateRandomSubDomainController::__invoke
* @see app/Http/Controllers/Agents/Agents/AgentsGenerateRandomSubDomainController.php:10
* @route '/generate-random-subdomain'
*/
generateRandomSubdomain.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: generateRandomSubdomain.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Agents\Agents\AgentsGenerateRandomSubDomainController::__invoke
* @see app/Http/Controllers/Agents/Agents/AgentsGenerateRandomSubDomainController.php:10
* @route '/generate-random-subdomain'
*/
generateRandomSubdomain.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: generateRandomSubdomain.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Billing\Subscriptions\BillingSubscribeController::__invoke
* @see app/Http/Controllers/Billing/Subscriptions/BillingSubscribeController.php:11
* @route '/subscribe'
*/
export const subscribe = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: subscribe.url(options),
    method: 'get',
})

subscribe.definition = {
    methods: ["get","head"],
    url: '/subscribe',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Billing\Subscriptions\BillingSubscribeController::__invoke
* @see app/Http/Controllers/Billing/Subscriptions/BillingSubscribeController.php:11
* @route '/subscribe'
*/
subscribe.url = (options?: RouteQueryOptions) => {
    return subscribe.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Billing\Subscriptions\BillingSubscribeController::__invoke
* @see app/Http/Controllers/Billing/Subscriptions/BillingSubscribeController.php:11
* @route '/subscribe'
*/
subscribe.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: subscribe.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Billing\Subscriptions\BillingSubscribeController::__invoke
* @see app/Http/Controllers/Billing/Subscriptions/BillingSubscribeController.php:11
* @route '/subscribe'
*/
subscribe.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: subscribe.url(options),
    method: 'head',
})

