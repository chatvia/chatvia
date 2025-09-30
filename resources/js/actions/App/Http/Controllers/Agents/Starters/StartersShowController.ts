import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Agents\Starters\StartersShowController::__invoke
* @see app/Http/Controllers/Agents/Starters/StartersShowController.php:11
* @route '/agents/{agent}/starters'
*/
const StartersShowController = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: StartersShowController.url(args, options),
    method: 'get',
})

StartersShowController.definition = {
    methods: ["get","head"],
    url: '/agents/{agent}/starters',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Agents\Starters\StartersShowController::__invoke
* @see app/Http/Controllers/Agents/Starters/StartersShowController.php:11
* @route '/agents/{agent}/starters'
*/
StartersShowController.url = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
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

    return StartersShowController.definition.url
            .replace('{agent}', parsedArgs.agent.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Agents\Starters\StartersShowController::__invoke
* @see app/Http/Controllers/Agents/Starters/StartersShowController.php:11
* @route '/agents/{agent}/starters'
*/
StartersShowController.get = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: StartersShowController.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Agents\Starters\StartersShowController::__invoke
* @see app/Http/Controllers/Agents/Starters/StartersShowController.php:11
* @route '/agents/{agent}/starters'
*/
StartersShowController.head = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: StartersShowController.url(args, options),
    method: 'head',
})

export default StartersShowController