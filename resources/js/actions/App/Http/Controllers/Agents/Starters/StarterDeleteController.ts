import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Agents\Starters\StarterDeleteController::__invoke
* @see app/Http/Controllers/Agents/Starters/StarterDeleteController.php:10
* @route '/agents/{agent}/starters/{id}'
*/
const StarterDeleteController = (args: { agent: number | { id: number }, id: string | number } | [agent: number | { id: number }, id: string | number ], options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: StarterDeleteController.url(args, options),
    method: 'delete',
})

StarterDeleteController.definition = {
    methods: ["delete"],
    url: '/agents/{agent}/starters/{id}',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\Agents\Starters\StarterDeleteController::__invoke
* @see app/Http/Controllers/Agents/Starters/StarterDeleteController.php:10
* @route '/agents/{agent}/starters/{id}'
*/
StarterDeleteController.url = (args: { agent: number | { id: number }, id: string | number } | [agent: number | { id: number }, id: string | number ], options?: RouteQueryOptions) => {
    if (Array.isArray(args)) {
        args = {
            agent: args[0],
            id: args[1],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        agent: typeof args.agent === 'object'
        ? args.agent.id
        : args.agent,
        id: args.id,
    }

    return StarterDeleteController.definition.url
            .replace('{agent}', parsedArgs.agent.toString())
            .replace('{id}', parsedArgs.id.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Agents\Starters\StarterDeleteController::__invoke
* @see app/Http/Controllers/Agents/Starters/StarterDeleteController.php:10
* @route '/agents/{agent}/starters/{id}'
*/
StarterDeleteController.delete = (args: { agent: number | { id: number }, id: string | number } | [agent: number | { id: number }, id: string | number ], options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: StarterDeleteController.url(args, options),
    method: 'delete',
})

export default StarterDeleteController