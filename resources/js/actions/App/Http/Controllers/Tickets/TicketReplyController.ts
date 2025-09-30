import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Tickets\TicketReplyController::__invoke
* @see app/Http/Controllers/Tickets/TicketReplyController.php:10
* @route '/dashboard/tickets/{ticket}/reply'
*/
const TicketReplyController = (args: { ticket: number | { id: number } } | [ticket: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: TicketReplyController.url(args, options),
    method: 'post',
})

TicketReplyController.definition = {
    methods: ["post"],
    url: '/dashboard/tickets/{ticket}/reply',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Tickets\TicketReplyController::__invoke
* @see app/Http/Controllers/Tickets/TicketReplyController.php:10
* @route '/dashboard/tickets/{ticket}/reply'
*/
TicketReplyController.url = (args: { ticket: number | { id: number } } | [ticket: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
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

    return TicketReplyController.definition.url
            .replace('{ticket}', parsedArgs.ticket.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Tickets\TicketReplyController::__invoke
* @see app/Http/Controllers/Tickets/TicketReplyController.php:10
* @route '/dashboard/tickets/{ticket}/reply'
*/
TicketReplyController.post = (args: { ticket: number | { id: number } } | [ticket: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: TicketReplyController.url(args, options),
    method: 'post',
})

export default TicketReplyController