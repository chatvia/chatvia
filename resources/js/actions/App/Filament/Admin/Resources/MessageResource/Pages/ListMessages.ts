import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../../../../../wayfinder'
/**
* @see \App\Filament\Admin\Resources\MessageResource\Pages\ListMessages::__invoke
* @see app/Filament/Admin/Resources/MessageResource/Pages/ListMessages.php:7
* @route '/admin/messages'
*/
const ListMessages = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: ListMessages.url(options),
    method: 'get',
})

ListMessages.definition = {
    methods: ["get","head"],
    url: '/admin/messages',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Filament\Admin\Resources\MessageResource\Pages\ListMessages::__invoke
* @see app/Filament/Admin/Resources/MessageResource/Pages/ListMessages.php:7
* @route '/admin/messages'
*/
ListMessages.url = (options?: RouteQueryOptions) => {
    return ListMessages.definition.url + queryParams(options)
}

/**
* @see \App\Filament\Admin\Resources\MessageResource\Pages\ListMessages::__invoke
* @see app/Filament/Admin/Resources/MessageResource/Pages/ListMessages.php:7
* @route '/admin/messages'
*/
ListMessages.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: ListMessages.url(options),
    method: 'get',
})

/**
* @see \App\Filament\Admin\Resources\MessageResource\Pages\ListMessages::__invoke
* @see app/Filament/Admin/Resources/MessageResource/Pages/ListMessages.php:7
* @route '/admin/messages'
*/
ListMessages.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: ListMessages.url(options),
    method: 'head',
})

export default ListMessages