import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Tickets\TicketShowController::__invoke
* @see app/Http/Controllers/Tickets/TicketShowController.php:11
* @route '/dashboard/tickets/{ticket}'
*/
const TicketShowController = (args: { ticket: number | { id: number } } | [ticket: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: TicketShowController.url(args, options),
    method: 'get',
})

TicketShowController.definition = {
    methods: ["get","head"],
    url: '/dashboard/tickets/{ticket}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Tickets\TicketShowController::__invoke
* @see app/Http/Controllers/Tickets/TicketShowController.php:11
* @route '/dashboard/tickets/{ticket}'
*/
TicketShowController.url = (args: { ticket: number | { id: number } } | [ticket: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { ticket: args }
    }

    if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
        args = { ticket: args.id }
    }

    if (Array.isArray(args)) {
        args = {
            ticket: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        ticket: typeof args.ticket === 'object'
        ? args.ticket.id
        : args.ticket,
    }

    return TicketShowController.definition.url
            .replace('{ticket}', parsedArgs.ticket.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Tickets\TicketShowController::__invoke
* @see app/Http/Controllers/Tickets/TicketShowController.php:11
* @route '/dashboard/tickets/{ticket}'
*/
TicketShowController.get = (args: { ticket: number | { id: number } } | [ticket: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: TicketShowController.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Tickets\TicketShowController::__invoke
* @see app/Http/Controllers/Tickets/TicketShowController.php:11
* @route '/dashboard/tickets/{ticket}'
*/
TicketShowController.head = (args: { ticket: number | { id: number } } | [ticket: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: TicketShowController.url(args, options),
    method: 'head',
})

export default TicketShowController