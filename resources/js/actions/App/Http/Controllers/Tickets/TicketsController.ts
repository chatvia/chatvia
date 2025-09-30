import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Tickets\TicketsController::__invoke
* @see app/Http/Controllers/Tickets/TicketsController.php:9
* @route '/dashboard/tickets'
*/
const TicketsController = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: TicketsController.url(options),
    method: 'get',
})

TicketsController.definition = {
    methods: ["get","head"],
    url: '/dashboard/tickets',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Tickets\TicketsController::__invoke
* @see app/Http/Controllers/Tickets/TicketsController.php:9
* @route '/dashboard/tickets'
*/
TicketsController.url = (options?: RouteQueryOptions) => {
    return TicketsController.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Tickets\TicketsController::__invoke
* @see app/Http/Controllers/Tickets/TicketsController.php:9
* @route '/dashboard/tickets'
*/
TicketsController.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: TicketsController.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Tickets\TicketsController::__invoke
* @see app/Http/Controllers/Tickets/TicketsController.php:9
* @route '/dashboard/tickets'
*/
TicketsController.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: TicketsController.url(options),
    method: 'head',
})

export default TicketsController