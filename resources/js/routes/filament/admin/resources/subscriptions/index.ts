import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../../wayfinder'
/**
* @see \App\Filament\Admin\Resources\SubscriptionResource\Pages\ListSubscriptions::__invoke
* @see app/Filament/Admin/Resources/SubscriptionResource/Pages/ListSubscriptions.php:7
* @route '/admin/subscriptions'
*/
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/admin/subscriptions',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Filament\Admin\Resources\SubscriptionResource\Pages\ListSubscriptions::__invoke
* @see app/Filament/Admin/Resources/SubscriptionResource/Pages/ListSubscriptions.php:7
* @route '/admin/subscriptions'
*/
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Filament\Admin\Resources\SubscriptionResource\Pages\ListSubscriptions::__invoke
* @see app/Filament/Admin/Resources/SubscriptionResource/Pages/ListSubscriptions.php:7
* @route '/admin/subscriptions'
*/
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

/**
* @see \App\Filament\Admin\Resources\SubscriptionResource\Pages\ListSubscriptions::__invoke
* @see app/Filament/Admin/Resources/SubscriptionResource/Pages/ListSubscriptions.php:7
* @route '/admin/subscriptions'
*/
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

/**
* @see \App\Filament\Admin\Resources\SubscriptionResource\Pages\ViewSubscription::__invoke
* @see app/Filament/Admin/Resources/SubscriptionResource/Pages/ViewSubscription.php:7
* @route '/admin/subscriptions/{record}'
*/
export const view = (args: { record: string | number } | [record: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: view.url(args, options),
    method: 'get',
})

view.definition = {
    methods: ["get","head"],
    url: '/admin/subscriptions/{record}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Filament\Admin\Resources\SubscriptionResource\Pages\ViewSubscription::__invoke
* @see app/Filament/Admin/Resources/SubscriptionResource/Pages/ViewSubscription.php:7
* @route '/admin/subscriptions/{record}'
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
* @see \App\Filament\Admin\Resources\SubscriptionResource\Pages\ViewSubscription::__invoke
* @see app/Filament/Admin/Resources/SubscriptionResource/Pages/ViewSubscription.php:7
* @route '/admin/subscriptions/{record}'
*/
view.get = (args: { record: string | number } | [record: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: view.url(args, options),
    method: 'get',
})

/**
* @see \App\Filament\Admin\Resources\SubscriptionResource\Pages\ViewSubscription::__invoke
* @see app/Filament/Admin/Resources/SubscriptionResource/Pages/ViewSubscription.php:7
* @route '/admin/subscriptions/{record}'
*/
view.head = (args: { record: string | number } | [record: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: view.url(args, options),
    method: 'head',
})

/**
* @see \App\Filament\Admin\Resources\SubscriptionResource\Pages\EditSubscription::__invoke
* @see app/Filament/Admin/Resources/SubscriptionResource/Pages/EditSubscription.php:7
* @route '/admin/subscriptions/{record}/edit'
*/
export const edit = (args: { record: string | number } | [record: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: edit.url(args, options),
    method: 'get',
})

edit.definition = {
    methods: ["get","head"],
    url: '/admin/subscriptions/{record}/edit',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Filament\Admin\Resources\SubscriptionResource\Pages\EditSubscription::__invoke
* @see app/Filament/Admin/Resources/SubscriptionResource/Pages/EditSubscription.php:7
* @route '/admin/subscriptions/{record}/edit'
*/
edit.url = (args: { record: string | number } | [record: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return edit.definition.url
            .replace('{record}', parsedArgs.record.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Filament\Admin\Resources\SubscriptionResource\Pages\EditSubscription::__invoke
* @see app/Filament/Admin/Resources/SubscriptionResource/Pages/EditSubscription.php:7
* @route '/admin/subscriptions/{record}/edit'
*/
edit.get = (args: { record: string | number } | [record: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: edit.url(args, options),
    method: 'get',
})

/**
* @see \App\Filament\Admin\Resources\SubscriptionResource\Pages\EditSubscription::__invoke
* @see app/Filament/Admin/Resources/SubscriptionResource/Pages/EditSubscription.php:7
* @route '/admin/subscriptions/{record}/edit'
*/
edit.head = (args: { record: string | number } | [record: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: edit.url(args, options),
    method: 'head',
})

const subscriptions = {
    index: Object.assign(index, index),
    view: Object.assign(view, view),
    edit: Object.assign(edit, edit),
}

export default subscriptions