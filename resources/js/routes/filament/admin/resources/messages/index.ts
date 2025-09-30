import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../../wayfinder'
/**
* @see \App\Filament\Admin\Resources\MessageResource\Pages\ListMessages::__invoke
* @see app/Filament/Admin/Resources/MessageResource/Pages/ListMessages.php:7
* @route '/admin/messages'
*/
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/admin/messages',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Filament\Admin\Resources\MessageResource\Pages\ListMessages::__invoke
* @see app/Filament/Admin/Resources/MessageResource/Pages/ListMessages.php:7
* @route '/admin/messages'
*/
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Filament\Admin\Resources\MessageResource\Pages\ListMessages::__invoke
* @see app/Filament/Admin/Resources/MessageResource/Pages/ListMessages.php:7
* @route '/admin/messages'
*/
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

/**
* @see \App\Filament\Admin\Resources\MessageResource\Pages\ListMessages::__invoke
* @see app/Filament/Admin/Resources/MessageResource/Pages/ListMessages.php:7
* @route '/admin/messages'
*/
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

/**
* @see \App\Filament\Admin\Resources\MessageResource\Pages\ViewMessage::__invoke
* @see app/Filament/Admin/Resources/MessageResource/Pages/ViewMessage.php:7
* @route '/admin/messages/{record}'
*/
export const view = (args: { record: string | number } | [record: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: view.url(args, options),
    method: 'get',
})

view.definition = {
    methods: ["get","head"],
    url: '/admin/messages/{record}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Filament\Admin\Resources\MessageResource\Pages\ViewMessage::__invoke
* @see app/Filament/Admin/Resources/MessageResource/Pages/ViewMessage.php:7
* @route '/admin/messages/{record}'
*/
view.url = (args: { record: string | number } | [record: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return view.definition.url
            .replace('{record}', parsedArgs.record.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Filament\Admin\Resources\MessageResource\Pages\ViewMessage::__invoke
* @see app/Filament/Admin/Resources/MessageResource/Pages/ViewMessage.php:7
* @route '/admin/messages/{record}'
*/
view.get = (args: { record: string | number } | [record: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: view.url(args, options),
    method: 'get',
})

/**
* @see \App\Filament\Admin\Resources\MessageResource\Pages\ViewMessage::__invoke
* @see app/Filament/Admin/Resources/MessageResource/Pages/ViewMessage.php:7
* @route '/admin/messages/{record}'
*/
view.head = (args: { record: string | number } | [record: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: view.url(args, options),
    method: 'head',
})

const messages = {
    index: Object.assign(index, index),
    view: Object.assign(view, view),
}

export default messages