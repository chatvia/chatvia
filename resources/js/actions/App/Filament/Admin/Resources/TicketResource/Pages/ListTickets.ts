import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../../../../../wayfinder'
/**
* @see \App\Filament\Admin\Resources\TicketResource\Pages\ListTickets::__invoke
* @see app/Filament/Admin/Resources/TicketResource/Pages/ListTickets.php:7
* @route '/admin/tickets'
*/
const ListTickets = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: ListTickets.url(options),
    method: 'get',
})

ListTickets.definition = {
    methods: ["get","head"],
    url: '/admin/tickets',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Filament\Admin\Resources\TicketResource\Pages\ListTickets::__invoke
* @see app/Filament/Admin/Resources/TicketResource/Pages/ListTickets.php:7
* @route '/admin/tickets'
*/
ListTickets.url = (options?: RouteQueryOptions) => {
    return ListTickets.definition.url + queryParams(options)
}

/**
* @see \App\Filament\Admin\Resources\TicketResource\Pages\ListTickets::__invoke
* @see app/Filament/Admin/Resources/TicketResource/Pages/ListTickets.php:7
* @route '/admin/tickets'
*/
ListTickets.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: ListTickets.url(options),
    method: 'get',
})

/**
* @see \App\Filament\Admin\Resources\TicketResource\Pages\ListTickets::__invoke
* @see app/Filament/Admin/Resources/TicketResource/Pages/ListTickets.php:7
* @route '/admin/tickets'
*/
ListTickets.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: ListTickets.url(options),
    method: 'head',
})

export default ListTickets