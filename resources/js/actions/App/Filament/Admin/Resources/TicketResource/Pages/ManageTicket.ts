import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../../../../wayfinder'
/**
* @see \App\Filament\Admin\Resources\TicketResource\Pages\ManageTicket::__invoke
* @see app/Filament/Admin/Resources/TicketResource/Pages/ManageTicket.php:7
* @route '/admin/tickets/{record}/manage'
*/
const ManageTicket = (args: { record: string | number } | [record: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: ManageTicket.url(args, options),
    method: 'get',
})

ManageTicket.definition = {
    methods: ["get","head"],
    url: '/admin/tickets/{record}/manage',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Filament\Admin\Resources\TicketResource\Pages\ManageTicket::__invoke
* @see app/Filament/Admin/Resources/TicketResource/Pages/ManageTicket.php:7
* @route '/admin/tickets/{record}/manage'
*/
ManageTicket.url = (args: { record: string | number } | [record: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return ManageTicket.definition.url
            .replace('{record}', parsedArgs.record.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Filament\Admin\Resources\TicketResource\Pages\ManageTicket::__invoke
* @see app/Filament/Admin/Resources/TicketResource/Pages/ManageTicket.php:7
* @route '/admin/tickets/{record}/manage'
*/
ManageTicket.get = (args: { record: string | number } | [record: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: ManageTicket.url(args, options),
    method: 'get',
})

/**
* @see \App\Filament\Admin\Resources\TicketResource\Pages\ManageTicket::__invoke
* @see app/Filament/Admin/Resources/TicketResource/Pages/ManageTicket.php:7
* @route '/admin/tickets/{record}/manage'
*/
ManageTicket.head = (args: { record: string | number } | [record: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: ManageTicket.url(args, options),
    method: 'head',
})

export default ManageTicket