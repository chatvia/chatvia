import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../../../../../wayfinder'
/**
* @see \App\Filament\Admin\Resources\SubscriptionResource\Pages\ListSubscriptions::__invoke
* @see app/Filament/Admin/Resources/SubscriptionResource/Pages/ListSubscriptions.php:7
* @route '/admin/subscriptions'
*/
const ListSubscriptions = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: ListSubscriptions.url(options),
    method: 'get',
})

ListSubscriptions.definition = {
    methods: ["get","head"],
    url: '/admin/subscriptions',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Filament\Admin\Resources\SubscriptionResource\Pages\ListSubscriptions::__invoke
* @see app/Filament/Admin/Resources/SubscriptionResource/Pages/ListSubscriptions.php:7
* @route '/admin/subscriptions'
*/
ListSubscriptions.url = (options?: RouteQueryOptions) => {
    return ListSubscriptions.definition.url + queryParams(options)
}

/**
* @see \App\Filament\Admin\Resources\SubscriptionResource\Pages\ListSubscriptions::__invoke
* @see app/Filament/Admin/Resources/SubscriptionResource/Pages/ListSubscriptions.php:7
* @route '/admin/subscriptions'
*/
ListSubscriptions.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: ListSubscriptions.url(options),
    method: 'get',
})

/**
* @see \App\Filament\Admin\Resources\SubscriptionResource\Pages\ListSubscriptions::__invoke
* @see app/Filament/Admin/Resources/SubscriptionResource/Pages/ListSubscriptions.php:7
* @route '/admin/subscriptions'
*/
ListSubscriptions.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: ListSubscriptions.url(options),
    method: 'head',
})

export default ListSubscriptions