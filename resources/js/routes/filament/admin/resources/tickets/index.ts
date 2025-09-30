import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../../wayfinder'
/**
* @see \App\Filament\Admin\Resources\TicketResource\Pages\ListTickets::__invoke
* @see app/Filament/Admin/Resources/TicketResource/Pages/ListTickets.php:7
* @route '/admin/tickets'
*/
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/admin/tickets',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Filament\Admin\Resources\TicketResource\Pages\ListTickets::__invoke
* @see app/Filament/Admin/Resources/TicketResource/Pages/ListTickets.php:7
* @route '/admin/tickets'
*/
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Filament\Admin\Resources\TicketResource\Pages\ListTickets::__invoke
* @see app/Filament/Admin/Resources/TicketResource/Pages/ListTickets.php:7
* @route '/admin/tickets'
*/
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

/**
* @see \App\Filament\Admin\Resources\TicketResource\Pages\ListTickets::__invoke
* @see app/Filament/Admin/Resources/TicketResource/Pages/ListTickets.php:7
* @route '/admin/tickets'
*/
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

/**
* @see \App\Filament\Admin\Resources\TicketResource\Pages\ManageTicket::__invoke
* @see app/Filament/Admin/Resources/TicketResource/Pages/ManageTicket.php:7
* @route '/admin/tickets/{record}/manage'
*/
export const manage = (args: { record: string | number } | [record: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: manage.url(args, options),
    method: 'get',
})

manage.definition = {
    methods: ["get","head"],
    url: '/admin/tickets/{record}/manage',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Filament\Admin\Resources\TicketResource\Pages\ManageTicket::__invoke
* @see app/Filament/Admin/Resources/TicketResource/Pages/ManageTicket.php:7
* @route '/admin/tickets/{record}/manage'
*/
manage.url = (args: { record: string | number } | [record: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { record: args }
    }

    if (Array.isArray(args)) {
        args = {
            record: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        record: args.record,
    }

    return manage.definition.url
            .replace('{record}', parsedArgs.record.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Filament\Admin\Resources\TicketResource\Pages\ManageTicket::__invoke
* @see app/Filament/Admin/Resources/TicketResource/Pages/ManageTicket.php:7
* @route '/admin/tickets/{record}/manage'
*/
manage.get = (args: { record: string | number } | [record: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: manage.url(args, options),
    method: 'get',
})

/**
* @see \App\Filament\Admin\Resources\TicketResource\Pages\ManageTicket::__invoke
* @see app/Filament/Admin/Resources/TicketResource/Pages/ManageTicket.php:7
* @route '/admin/tickets/{record}/manage'
*/
manage.head = (args: { record: string | number } | [record: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: manage.url(args, options),
    method: 'head',
})

const tickets = {
    index: Object.assign(index, index),
    manage: Object.assign(manage, manage),
}

export default tickets