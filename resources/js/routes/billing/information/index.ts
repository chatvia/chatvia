import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../wayfinder'
/**
* @see \App\Http\Controllers\Billing\BillingUpdateInformationController::__invoke
* @see app/Http/Controllers/Billing/BillingUpdateInformationController.php:10
* @route '/billing/info'
*/
export const update = (options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: update.url(options),
    method: 'patch',
})

update.definition = {
    methods: ["patch"],
    url: '/billing/info',
} satisfies RouteDefinition<["patch"]>

/**
* @see \App\Http\Controllers\Billing\BillingUpdateInformationController::__invoke
* @see app/Http/Controllers/Billing/BillingUpdateInformationController.php:10
* @route '/billing/info'
*/
update.url = (options?: RouteQueryOptions) => {
    return update.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Billing\BillingUpdateInformationController::__invoke
* @see app/Http/Controllers/Billing/BillingUpdateInformationController.php:10
* @route '/billing/info'
*/
update.patch = (options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: update.url(options),
    method: 'patch',
})

const information = {
    update: Object.assign(update, update),
}

export default information