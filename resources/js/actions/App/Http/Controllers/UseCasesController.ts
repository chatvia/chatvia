import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\UseCasesController::__invoke
* @see app/Http/Controllers/UseCasesController.php:10
* @route '/use-cases/{case}'
*/
const UseCasesController = (args: { case: string | number } | [caseParam: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: UseCasesController.url(args, options),
    method: 'get',
})

UseCasesController.definition = {
    methods: ["get","head"],
    url: '/use-cases/{case}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\UseCasesController::__invoke
* @see app/Http/Controllers/UseCasesController.php:10
* @route '/use-cases/{case}'
*/
UseCasesController.url = (args: { case: string | number } | [caseParam: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { case: args }
    }

    if (Array.isArray(args)) {
        args = {
            case: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        case: args.case,
    }

    return UseCasesController.definition.url
            .replace('{case}', parsedArgs.case.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\UseCasesController::__invoke
* @see app/Http/Controllers/UseCasesController.php:10
* @route '/use-cases/{case}'
*/
UseCasesController.get = (args: { case: string | number } | [caseParam: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: UseCasesController.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\UseCasesController::__invoke
* @see app/Http/Controllers/UseCasesController.php:10
* @route '/use-cases/{case}'
*/
UseCasesController.head = (args: { case: string | number } | [caseParam: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: UseCasesController.url(args, options),
    method: 'head',
})

export default UseCasesController