import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Tickets\TicketStoreController::__invoke
* @see app/Http/Controllers/Tickets/TicketStoreController.php:9
* @route '/dashboard/tickets'
*/
const TicketStoreController = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: TicketStoreController.url(options),
    method: 'post',
})

TicketStoreController.definition = {
    methods: ["post"],
    url: '/dashboard/tickets',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Tickets\TicketStoreController::__invoke
* @see app/Http/Controllers/Tickets/TicketStoreController.php:9
* @route '/dashboard/tickets'
*/
TicketStoreController.url = (options?: RouteQueryOptions) => {
    return TicketStoreController.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Tickets\TicketStoreController::__invoke
* @see app/Http/Controllers/Tickets/TicketStoreController.php:9
* @route '/dashboard/tickets'
*/
TicketStoreController.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: TicketStoreController.url(options),
    method: 'post',
})

export default TicketStoreController