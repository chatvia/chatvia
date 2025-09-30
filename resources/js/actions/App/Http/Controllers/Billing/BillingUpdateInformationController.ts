import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Billing\BillingUpdateInformationController::__invoke
* @see app/Http/Controllers/Billing/BillingUpdateInformationController.php:10
* @route '/billing/info'
*/
const BillingUpdateInformationController = (options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: BillingUpdateInformationController.url(options),
    method: 'patch',
})

BillingUpdateInformationController.definition = {
    methods: ["patch"],
    url: '/billing/info',
} satisfies RouteDefinition<["patch"]>

/**
* @see \App\Http\Controllers\Billing\BillingUpdateInformationController::__invoke
* @see app/Http/Controllers/Billing/BillingUpdateInformationController.php:10
* @route '/billing/info'
*/
BillingUpdateInformationController.url = (options?: RouteQueryOptions) => {
    return BillingUpdateInformationController.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Billing\BillingUpdateInformationController::__invoke
* @see app/Http/Controllers/Billing/BillingUpdateInformationController.php:10
* @route '/billing/info'
*/
BillingUpdateInformationController.patch = (options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: BillingUpdateInformationController.url(options),
    method: 'patch',
})

export default BillingUpdateInformationController