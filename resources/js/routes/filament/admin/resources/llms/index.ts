import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../../wayfinder'
/**
* @see \App\Filament\Admin\Resources\LlmResource\Pages\ListLlms::__invoke
* @see app/Filament/Admin/Resources/LlmResource/Pages/ListLlms.php:7
* @route '/admin/llms'
*/
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/admin/llms',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Filament\Admin\Resources\LlmResource\Pages\ListLlms::__invoke
* @see app/Filament/Admin/Resources/LlmResource/Pages/ListLlms.php:7
* @route '/admin/llms'
*/
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Filament\Admin\Resources\LlmResource\Pages\ListLlms::__invoke
* @see app/Filament/Admin/Resources/LlmResource/Pages/ListLlms.php:7
* @route '/admin/llms'
*/
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

/**
* @see \App\Filament\Admin\Resources\LlmResource\Pages\ListLlms::__invoke
* @see app/Filament/Admin/Resources/LlmResource/Pages/ListLlms.php:7
* @route '/admin/llms'
*/
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

/**
* @see \App\Filament\Admin\Resources\LlmResource\Pages\CreateLlm::__invoke
* @see app/Filament/Admin/Resources/LlmResource/Pages/CreateLlm.php:7
* @route '/admin/llms/create'
*/
export const create = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: create.url(options),
    method: 'get',
})

create.definition = {
    methods: ["get","head"],
    url: '/admin/llms/create',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Filament\Admin\Resources\LlmResource\Pages\CreateLlm::__invoke
* @see app/Filament/Admin/Resources/LlmResource/Pages/CreateLlm.php:7
* @route '/admin/llms/create'
*/
create.url = (options?: RouteQueryOptions) => {
    return create.definition.url + queryParams(options)
}

/**
* @see \App\Filament\Admin\Resources\LlmResource\Pages\CreateLlm::__invoke
* @see app/Filament/Admin/Resources/LlmResource/Pages/CreateLlm.php:7
* @route '/admin/llms/create'
*/
create.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: create.url(options),
    method: 'get',
})

/**
* @see \App\Filament\Admin\Resources\LlmResource\Pages\CreateLlm::__invoke
* @see app/Filament/Admin/Resources/LlmResource/Pages/CreateLlm.php:7
* @route '/admin/llms/create'
*/
create.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: create.url(options),
    method: 'head',
})

/**
* @see \App\Filament\Admin\Resources\LlmResource\Pages\ViewLlm::__invoke
* @see app/Filament/Admin/Resources/LlmResource/Pages/ViewLlm.php:7
* @route '/admin/llms/{record}'
*/
export const view = (args: { record: string | number } | [record: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: view.url(args, options),
    method: 'get',
})

view.definition = {
    methods: ["get","head"],
    url: '/admin/llms/{record}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Filament\Admin\Resources\LlmResource\Pages\ViewLlm::__invoke
* @see app/Filament/Admin/Resources/LlmResource/Pages/ViewLlm.php:7
* @route '/admin/llms/{record}'
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
* @see \App\Filament\Admin\Resources\LlmResource\Pages\ViewLlm::__invoke
* @see app/Filament/Admin/Resources/LlmResource/Pages/ViewLlm.php:7
* @route '/admin/llms/{record}'
*/
view.get = (args: { record: string | number } | [record: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: view.url(args, options),
    method: 'get',
})

/**
* @see \App\Filament\Admin\Resources\LlmResource\Pages\ViewLlm::__invoke
* @see app/Filament/Admin/Resources/LlmResource/Pages/ViewLlm.php:7
* @route '/admin/llms/{record}'
*/
view.head = (args: { record: string | number } | [record: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: view.url(args, options),
    method: 'head',
})

/**
* @see \App\Filament\Admin\Resources\LlmResource\Pages\EditLlm::__invoke
* @see app/Filament/Admin/Resources/LlmResource/Pages/EditLlm.php:7
* @route '/admin/llms/{record}/edit'
*/
export const edit = (args: { record: string | number } | [record: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: edit.url(args, options),
    method: 'get',
})

edit.definition = {
    methods: ["get","head"],
    url: '/admin/llms/{record}/edit',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Filament\Admin\Resources\LlmResource\Pages\EditLlm::__invoke
* @see app/Filament/Admin/Resources/LlmResource/Pages/EditLlm.php:7
* @route '/admin/llms/{record}/edit'
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
* @see \App\Filament\Admin\Resources\LlmResource\Pages\EditLlm::__invoke
* @see app/Filament/Admin/Resources/LlmResource/Pages/EditLlm.php:7
* @route '/admin/llms/{record}/edit'
*/
edit.get = (args: { record: string | number } | [record: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: edit.url(args, options),
    method: 'get',
})

/**
* @see \App\Filament\Admin\Resources\LlmResource\Pages\EditLlm::__invoke
* @see app/Filament/Admin/Resources/LlmResource/Pages/EditLlm.php:7
* @route '/admin/llms/{record}/edit'
*/
edit.head = (args: { record: string | number } | [record: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: edit.url(args, options),
    method: 'head',
})

const llms = {
    index: Object.assign(index, index),
    create: Object.assign(create, create),
    view: Object.assign(view, view),
    edit: Object.assign(edit, edit),
}

export default llms