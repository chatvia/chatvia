import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Billing\InvoicesController::__invoke
* @see app/Http/Controllers/Billing/InvoicesController.php:10
* @route '/billing/invoices'
*/
const InvoicesController = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: InvoicesController.url(options),
    method: 'get',
})

InvoicesController.definition = {
    methods: ["get","head"],
    url: '/billing/invoices',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Billing\InvoicesController::__invoke
* @see app/Http/Controllers/Billing/InvoicesController.php:10
* @route '/billing/invoices'
*/
InvoicesController.url = (options?: RouteQueryOptions) => {
    return InvoicesController.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Billing\InvoicesController::__invoke
* @see app/Http/Controllers/Billing/InvoicesController.php:10
* @route '/billing/invoices'
*/
InvoicesController.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: InvoicesController.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Billing\InvoicesController::__invoke
* @see app/Http/Controllers/Billing/InvoicesController.php:10
* @route '/billing/invoices'
*/
InvoicesController.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: InvoicesController.url(options),
    method: 'head',
})

export default InvoicesController