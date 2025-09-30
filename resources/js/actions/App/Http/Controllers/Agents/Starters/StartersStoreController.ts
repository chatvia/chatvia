import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Agents\Starters\StartersStoreController::__invoke
* @see app/Http/Controllers/Agents/Starters/StartersStoreController.php:10
* @route '/agents/{agent}/starters'
*/
const StartersStoreController = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: StartersStoreController.url(args, options),
    method: 'post',
})

StartersStoreController.definition = {
    methods: ["post"],
    url: '/agents/{agent}/starters',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Agents\Starters\StartersStoreController::__invoke
* @see app/Http/Controllers/Agents/Starters/StartersStoreController.php:10
* @route '/agents/{agent}/starters'
*/
StartersStoreController.url = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { agent: args }
    }

    if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
        args = { agent: args.id }
    }

    if (Array.isArray(args)) {
        args = {
            agent: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        agent: typeof args.agent === 'object'
        ? args.agent.id
        : args.agent,
    }

    return StartersStoreController.definition.url
            .replace('{agent}', parsedArgs.agent.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Agents\Starters\StartersStoreController::__invoke
* @see app/Http/Controllers/Agents/Starters/StartersStoreController.php:10
* @route '/agents/{agent}/starters'
*/
StartersStoreController.post = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: StartersStoreController.url(args, options),
    method: 'post',
})

export default StartersStoreController