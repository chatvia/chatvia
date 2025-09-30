import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Billing\Subscriptions\BillingSubscriptionResumeController::__invoke
* @see app/Http/Controllers/Billing/Subscriptions/BillingSubscriptionResumeController.php:10
* @route '/billing/subscription/resume'
*/
const BillingSubscriptionResumeController = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: BillingSubscriptionResumeController.url(options),
    method: 'post',
})

BillingSubscriptionResumeController.definition = {
    methods: ["post"],
    url: '/billing/subscription/resume',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Billing\Subscriptions\BillingSubscriptionResumeController::__invoke
* @see app/Http/Controllers/Billing/Subscriptions/BillingSubscriptionResumeController.php:10
* @route '/billing/subscription/resume'
*/
BillingSubscriptionResumeController.url = (options?: RouteQueryOptions) => {
    return BillingSubscriptionResumeController.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Billing\Subscriptions\BillingSubscriptionResumeController::__invoke
* @see app/Http/Controllers/Billing/Subscriptions/BillingSubscriptionResumeController.php:10
* @route '/billing/subscription/resume'
*/
BillingSubscriptionResumeController.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: BillingSubscriptionResumeController.url(options),
    method: 'post',
})

export default BillingSubscriptionResumeController