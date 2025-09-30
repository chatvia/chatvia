import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../../../../wayfinder'
/**
* @see \App\Filament\Admin\Resources\UserResource\Pages\EditUser::__invoke
* @see app/Filament/Admin/Resources/UserResource/Pages/EditUser.php:7
* @route '/admin/users/{record}/edit'
*/
const EditUser = (args: { record: string | number } | [record: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: EditUser.url(args, options),
    method: 'get',
})

EditUser.definition = {
    methods: ["get","head"],
    url: '/admin/users/{record}/edit',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Filament\Admin\Resources\UserResource\Pages\EditUser::__invoke
* @see app/Filament/Admin/Resources/UserResource/Pages/EditUser.php:7
* @route '/admin/users/{record}/edit'
*/
EditUser.url = (args: { record: string | number } | [record: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return EditUser.definition.url
            .replace('{record}', parsedArgs.record.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Filament\Admin\Resources\UserResource\Pages\EditUser::__invoke
* @see app/Filament/Admin/Resources/UserResource/Pages/EditUser.php:7
* @route '/admin/users/{record}/edit'
*/
EditUser.get = (args: { record: string | number } | [record: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: EditUser.url(args, options),
    method: 'get',
})

/**
* @see \App\Filament\Admin\Resources\UserResource\Pages\EditUser::__invoke
* @see app/Filament/Admin/Resources/UserResource/Pages/EditUser.php:7
* @route '/admin/users/{record}/edit'
*/
EditUser.head = (args: { record: string | number } | [record: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: EditUser.url(args, options),
    method: 'head',
})

export default EditUser