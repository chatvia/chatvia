import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../../../../wayfinder'
/**
* @see \App\Filament\Admin\Resources\MessageResource\Pages\ViewMessage::__invoke
* @see app/Filament/Admin/Resources/MessageResource/Pages/ViewMessage.php:7
* @route '/admin/messages/{record}'
*/
const ViewMessage = (args: { record: string | number } | [record: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: ViewMessage.url(args, options),
    method: 'get',
})

ViewMessage.definition = {
    methods: ["get","head"],
    url: '/admin/messages/{record}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Filament\Admin\Resources\MessageResource\Pages\ViewMessage::__invoke
* @see app/Filament/Admin/Resources/MessageResource/Pages/ViewMessage.php:7
* @route '/admin/messages/{record}'
*/
ViewMessage.url = (args: { record: string | number } | [record: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return ViewMessage.definition.url
            .replace('{record}', parsedArgs.record.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Filament\Admin\Resources\MessageResource\Pages\ViewMessage::__invoke
* @see app/Filament/Admin/Resources/MessageResource/Pages/ViewMessage.php:7
* @route '/admin/messages/{record}'
*/
ViewMessage.get = (args: { record: string | number } | [record: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: ViewMessage.url(args, options),
    method: 'get',
})

/**
* @see \App\Filament\Admin\Resources\MessageResource\Pages\ViewMessage::__invoke
* @see app/Filament/Admin/Resources/MessageResource/Pages/ViewMessage.php:7
* @route '/admin/messages/{record}'
*/
ViewMessage.head = (args: { record: string | number } | [record: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: ViewMessage.url(args, options),
    method: 'head',
})

export default ViewMessage