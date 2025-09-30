import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Tickets\TicketChangePriorityController::__invoke
* @see app/Http/Controllers/Tickets/TicketChangePriorityController.php:10
* @route '/dashboard/tickets/{ticket}/priority'
*/
const TicketChangePriorityController = (args: { ticket: number | { id: number } } | [ticket: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: TicketChangePriorityController.url(args, options),
    method: 'post',
})

TicketChangePriorityController.definition = {
    methods: ["post"],
    url: '/dashboard/tickets/{ticket}/priority',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Tickets\TicketChangePriorityController::__invoke
* @see app/Http/Controllers/Tickets/TicketChangePriorityController.php:10
* @route '/dashboard/tickets/{ticket}/priority'
*/
TicketChangePriorityController.url = (args: { ticket: number | { id: number } } | [ticket: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
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

    return TicketChangePriorityController.definition.url
            .replace('{ticket}', parsedArgs.ticket.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Tickets\TicketChangePriorityController::__invoke
* @see app/Http/Controllers/Tickets/TicketChangePriorityController.php:10
* @route '/dashboard/tickets/{ticket}/priority'
*/
TicketChangePriorityController.post = (args: { ticket: number | { id: number } } | [ticket: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: TicketChangePriorityController.url(args, options),
    method: 'post',
})

export default TicketChangePriorityController