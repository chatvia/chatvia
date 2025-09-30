import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../wayfinder'
/**
* @see \App\Http\Controllers\Agents\Starters\StartersStoreController::__invoke
* @see app/Http/Controllers/Agents/Starters/StartersStoreController.php:10
* @route '/agents/{agent}/starters'
*/
export const store = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(args, options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/agents/{agent}/starters',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Agents\Starters\StartersStoreController::__invoke
* @see app/Http/Controllers/Agents/Starters/StartersStoreController.php:10
* @route '/agents/{agent}/starters'
*/
store.url = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
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

    return store.definition.url
            .replace('{agent}', parsedArgs.agent.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Agents\Starters\StartersStoreController::__invoke
* @see app/Http/Controllers/Agents/Starters/StartersStoreController.php:10
* @route '/agents/{agent}/starters'
*/
store.post = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Agents\Starters\StarterDeleteController::__invoke
* @see app/Http/Controllers/Agents/Starters/StarterDeleteController.php:10
* @route '/agents/{agent}/starters/{id}'
*/
export const deleteMethod = (args: { agent: number | { id: number }, id: string | number } | [agent: number | { id: number }, id: string | number ], options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: deleteMethod.url(args, options),
    method: 'delete',
})

deleteMethod.definition = {
    methods: ["delete"],
    url: '/agents/{agent}/starters/{id}',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\Agents\Starters\StarterDeleteController::__invoke
* @see app/Http/Controllers/Agents/Starters/StarterDeleteController.php:10
* @route '/agents/{agent}/starters/{id}'
*/
deleteMethod.url = (args: { agent: number | { id: number }, id: string | number } | [agent: number | { id: number }, id: string | number ], options?: RouteQueryOptions) => {
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

    return deleteMethod.definition.url
            .replace('{agent}', parsedArgs.agent.toString())
            .replace('{id}', parsedArgs.id.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Agents\Starters\StarterDeleteController::__invoke
* @see app/Http/Controllers/Agents/Starters/StarterDeleteController.php:10
* @route '/agents/{agent}/starters/{id}'
*/
deleteMethod.delete = (args: { agent: number | { id: number }, id: string | number } | [agent: number | { id: number }, id: string | number ], options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: deleteMethod.url(args, options),
    method: 'delete',
})

const starters = {
    store: Object.assign(store, store),
    delete: Object.assign(deleteMethod, deleteMethod),
}

export default starters