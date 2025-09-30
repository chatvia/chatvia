import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../../../../wayfinder'
/**
* @see \App\Filament\Admin\Resources\SubscriptionResource\Pages\EditSubscription::__invoke
* @see app/Filament/Admin/Resources/SubscriptionResource/Pages/EditSubscription.php:7
* @route '/admin/subscriptions/{record}/edit'
*/
const EditSubscription = (args: { record: string | number } | [record: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: EditSubscription.url(args, options),
    method: 'get',
})

EditSubscription.definition = {
    methods: ["get","head"],
    url: '/admin/subscriptions/{record}/edit',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Filament\Admin\Resources\SubscriptionResource\Pages\EditSubscription::__invoke
* @see app/Filament/Admin/Resources/SubscriptionResource/Pages/EditSubscription.php:7
* @route '/admin/subscriptions/{record}/edit'
*/
EditSubscription.url = (args: { record: string | number } | [record: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return EditSubscription.definition.url
            .replace('{record}', parsedArgs.record.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Filament\Admin\Resources\SubscriptionResource\Pages\EditSubscription::__invoke
* @see app/Filament/Admin/Resources/SubscriptionResource/Pages/EditSubscription.php:7
* @route '/admin/subscriptions/{record}/edit'
*/
EditSubscription.get = (args: { record: string | number } | [record: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: EditSubscription.url(args, options),
    method: 'get',
})

/**
* @see \App\Filament\Admin\Resources\SubscriptionResource\Pages\EditSubscription::__invoke
* @see app/Filament/Admin/Resources/SubscriptionResource/Pages/EditSubscription.php:7
* @route '/admin/subscriptions/{record}/edit'
*/
EditSubscription.head = (args: { record: string | number } | [record: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: EditSubscription.url(args, options),
    method: 'head',
})

export default EditSubscription