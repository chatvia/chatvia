import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../wayfinder'
import reply from './reply'
/**
* @see \App\Http\Controllers\Tickets\TicketStoreController::__invoke
* @see app/Http/Controllers/Tickets/TicketStoreController.php:9
* @route '/dashboard/tickets'
*/
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/dashboard/tickets',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Tickets\TicketStoreController::__invoke
* @see app/Http/Controllers/Tickets/TicketStoreController.php:9
* @route '/dashboard/tickets'
*/
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Tickets\TicketStoreController::__invoke
* @see app/Http/Controllers/Tickets/TicketStoreController.php:9
* @route '/dashboard/tickets'
*/
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Tickets\TicketShowController::__invoke
* @see app/Http/Controllers/Tickets/TicketShowController.php:11
* @route '/dashboard/tickets/{ticket}'
*/
export const show = (args: { ticket: number | { id: number } } | [ticket: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})

show.definition = {
    methods: ["get","head"],
    url: '/dashboard/tickets/{ticket}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Tickets\TicketShowController::__invoke
* @see app/Http/Controllers/Tickets/TicketShowController.php:11
* @route '/dashboard/tickets/{ticket}'
*/
show.url = (args: { ticket: number | { id: number } } | [ticket: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
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

    return show.definition.url
            .replace('{ticket}', parsedArgs.ticket.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Tickets\TicketShowController::__invoke
* @see app/Http/Controllers/Tickets/TicketShowController.php:11
* @route '/dashboard/tickets/{ticket}'
*/
show.get = (args: { ticket: number | { id: number } } | [ticket: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Tickets\TicketShowController::__invoke
* @see app/Http/Controllers/Tickets/TicketShowController.php:11
* @route '/dashboard/tickets/{ticket}'
*/
show.head = (args: { ticket: number | { id: number } } | [ticket: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: show.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Tickets\TicketChangePriorityController::__invoke
* @see app/Http/Controllers/Tickets/TicketChangePriorityController.php:10
* @route '/dashboard/tickets/{ticket}/priority'
*/
export const priority = (args: { ticket: number | { id: number } } | [ticket: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: priority.url(args, options),
    method: 'post',
})

priority.definition = {
    methods: ["post"],
    url: '/dashboard/tickets/{ticket}/priority',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Tickets\TicketChangePriorityController::__invoke
* @see app/Http/Controllers/Tickets/TicketChangePriorityController.php:10
* @route '/dashboard/tickets/{ticket}/priority'
*/
priority.url = (args: { ticket: number | { id: number } } | [ticket: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
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

    return priority.definition.url
            .replace('{ticket}', parsedArgs.ticket.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Tickets\TicketChangePriorityController::__invoke
* @see app/Http/Controllers/Tickets/TicketChangePriorityController.php:10
* @route '/dashboard/tickets/{ticket}/priority'
*/
priority.post = (args: { ticket: number | { id: number } } | [ticket: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: priority.url(args, options),
    method: 'post',
})

const tickets = {
    store: Object.assign(store, store),
    show: Object.assign(show, show),
    reply: Object.assign(reply, reply),
    priority: Object.assign(priority, priority),
}

export default tickets