import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../../../../wayfinder'
/**
* @see \App\Filament\Admin\Resources\SubscriptionResource\Pages\ViewSubscription::__invoke
* @see app/Filament/Admin/Resources/SubscriptionResource/Pages/ViewSubscription.php:7
* @route '/admin/subscriptions/{record}'
*/
const ViewSubscription = (args: { record: string | number } | [record: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: ViewSubscription.url(args, options),
    method: 'get',
})

ViewSubscription.definition = {
    methods: ["get","head"],
    url: '/admin/subscriptions/{record}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Filament\Admin\Resources\SubscriptionResource\Pages\ViewSubscription::__invoke
* @see app/Filament/Admin/Resources/SubscriptionResource/Pages/ViewSubscription.php:7
* @route '/admin/subscriptions/{record}'
*/
ViewSubscription.url = (args: { record: string | number } | [record: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return ViewSubscription.definition.url
            .replace('{record}', parsedArgs.record.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Filament\Admin\Resources\SubscriptionResource\Pages\ViewSubscription::__invoke
* @see app/Filament/Admin/Resources/SubscriptionResource/Pages/ViewSubscription.php:7
* @route '/admin/subscriptions/{record}'
*/
ViewSubscription.get = (args: { record: string | number } | [record: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: ViewSubscription.url(args, options),
    method: 'get',
})

/**
* @see \App\Filament\Admin\Resources\SubscriptionResource\Pages\ViewSubscription::__invoke
* @see app/Filament/Admin/Resources/SubscriptionResource/Pages/ViewSubscription.php:7
* @route '/admin/subscriptions/{record}'
*/
ViewSubscription.head = (args: { record: string | number } | [record: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: ViewSubscription.url(args, options),
    method: 'head',
})

export default ViewSubscription