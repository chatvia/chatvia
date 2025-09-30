import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../wayfinder'
import addons from './addons'
import subscription from './subscription'
import schedule from './schedule'
import paymentMethods7d3542 from './payment-methods'
import paymentMethod from './payment-method'
import information from './information'
/**
* @see \App\Http\Controllers\Billing\BillingController::__invoke
* @see app/Http/Controllers/Billing/BillingController.php:12
* @route '/billing'
*/
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/billing',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Billing\BillingController::__invoke
* @see app/Http/Controllers/Billing/BillingController.php:12
* @route '/billing'
*/
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Billing\BillingController::__invoke
* @see app/Http/Controllers/Billing/BillingController.php:12
* @route '/billing'
*/
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Billing\BillingController::__invoke
* @see app/Http/Controllers/Billing/BillingController.php:12
* @route '/billing'
*/
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\PlansController::__invoke
* @see app/Http/Controllers/PlansController.php:12
* @route '/billing/plans'
*/
export const packages = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: packages.url(options),
    method: 'get',
})

packages.definition = {
    methods: ["get","head"],
    url: '/billing/plans',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\PlansController::__invoke
* @see app/Http/Controllers/PlansController.php:12
* @route '/billing/plans'
*/
packages.url = (options?: RouteQueryOptions) => {
    return packages.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\PlansController::__invoke
* @see app/Http/Controllers/PlansController.php:12
* @route '/billing/plans'
*/
packages.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: packages.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\PlansController::__invoke
* @see app/Http/Controllers/PlansController.php:12
* @route '/billing/plans'
*/
packages.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: packages.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Billing\Subscriptions\BillingSubscriptionCancelController::__invoke
* @see app/Http/Controllers/Billing/Subscriptions/BillingSubscriptionCancelController.php:10
* @route '/billing/subscription/cancel'
*/
export const cancel = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: cancel.url(options),
    method: 'post',
})

cancel.definition = {
    methods: ["post"],
    url: '/billing/subscription/cancel',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Billing\Subscriptions\BillingSubscriptionCancelController::__invoke
* @see app/Http/Controllers/Billing/Subscriptions/BillingSubscriptionCancelController.php:10
* @route '/billing/subscription/cancel'
*/
cancel.url = (options?: RouteQueryOptions) => {
    return cancel.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Billing\Subscriptions\BillingSubscriptionCancelController::__invoke
* @see app/Http/Controllers/Billing/Subscriptions/BillingSubscriptionCancelController.php:10
* @route '/billing/subscription/cancel'
*/
cancel.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: cancel.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Billing\Subscriptions\BillingSubscriptionResumeController::__invoke
* @see app/Http/Controllers/Billing/Subscriptions/BillingSubscriptionResumeController.php:10
* @route '/billing/subscription/resume'
*/
export const resume = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: resume.url(options),
    method: 'post',
})

resume.definition = {
    methods: ["post"],
    url: '/billing/subscription/resume',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Billing\Subscriptions\BillingSubscriptionResumeController::__invoke
* @see app/Http/Controllers/Billing/Subscriptions/BillingSubscriptionResumeController.php:10
* @route '/billing/subscription/resume'
*/
resume.url = (options?: RouteQueryOptions) => {
    return resume.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Billing\Subscriptions\BillingSubscriptionResumeController::__invoke
* @see app/Http/Controllers/Billing/Subscriptions/BillingSubscriptionResumeController.php:10
* @route '/billing/subscription/resume'
*/
resume.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: resume.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Billing\Stripe\BillingStripePortalController::__invoke
* @see app/Http/Controllers/Billing/Stripe/BillingStripePortalController.php:9
* @route '/billing/portal'
*/
export const portal = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: portal.url(options),
    method: 'get',
})

portal.definition = {
    methods: ["get","head"],
    url: '/billing/portal',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Billing\Stripe\BillingStripePortalController::__invoke
* @see app/Http/Controllers/Billing/Stripe/BillingStripePortalController.php:9
* @route '/billing/portal'
*/
portal.url = (options?: RouteQueryOptions) => {
    return portal.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Billing\Stripe\BillingStripePortalController::__invoke
* @see app/Http/Controllers/Billing/Stripe/BillingStripePortalController.php:9
* @route '/billing/portal'
*/
portal.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: portal.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Billing\Stripe\BillingStripePortalController::__invoke
* @see app/Http/Controllers/Billing/Stripe/BillingStripePortalController.php:9
* @route '/billing/portal'
*/
portal.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: portal.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Billing\InvoicesController::__invoke
* @see app/Http/Controllers/Billing/InvoicesController.php:10
* @route '/billing/invoices'
*/
export const invoices = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: invoices.url(options),
    method: 'get',
})

invoices.definition = {
    methods: ["get","head"],
    url: '/billing/invoices',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Billing\InvoicesController::__invoke
* @see app/Http/Controllers/Billing/InvoicesController.php:10
* @route '/billing/invoices'
*/
invoices.url = (options?: RouteQueryOptions) => {
    return invoices.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Billing\InvoicesController::__invoke
* @see app/Http/Controllers/Billing/InvoicesController.php:10
* @route '/billing/invoices'
*/
invoices.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: invoices.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Billing\InvoicesController::__invoke
* @see app/Http/Controllers/Billing/InvoicesController.php:10
* @route '/billing/invoices'
*/
invoices.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: invoices.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Billing\Payments\BillingPaymentMethodsController::__invoke
* @see app/Http/Controllers/Billing/Payments/BillingPaymentMethodsController.php:11
* @route '/billing/payments'
*/
export const paymentMethods = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: paymentMethods.url(options),
    method: 'get',
})

paymentMethods.definition = {
    methods: ["get","head"],
    url: '/billing/payments',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Billing\Payments\BillingPaymentMethodsController::__invoke
* @see app/Http/Controllers/Billing/Payments/BillingPaymentMethodsController.php:11
* @route '/billing/payments'
*/
paymentMethods.url = (options?: RouteQueryOptions) => {
    return paymentMethods.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Billing\Payments\BillingPaymentMethodsController::__invoke
* @see app/Http/Controllers/Billing/Payments/BillingPaymentMethodsController.php:11
* @route '/billing/payments'
*/
paymentMethods.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: paymentMethods.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Billing\Payments\BillingPaymentMethodsController::__invoke
* @see app/Http/Controllers/Billing/Payments/BillingPaymentMethodsController.php:11
* @route '/billing/payments'
*/
paymentMethods.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: paymentMethods.url(options),
    method: 'head',
})

const billing = {
    addons: Object.assign(addons, addons),
    index: Object.assign(index, index),
    packages: Object.assign(packages, packages),
    subscription: Object.assign(subscription, subscription),
    schedule: Object.assign(schedule, schedule),
    cancel: Object.assign(cancel, cancel),
    resume: Object.assign(resume, resume),
    portal: Object.assign(portal, portal),
    invoices: Object.assign(invoices, invoices),
    paymentMethods: Object.assign(paymentMethods, paymentMethods7d3542),
    paymentMethod: Object.assign(paymentMethod, paymentMethod),
    information: Object.assign(information, information),
}

export default billing