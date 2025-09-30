import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Agents\Embed\EmbedController::__invoke
* @see app/Http/Controllers/Agents/Embed/EmbedController.php:12
* @route '/agents/{agent}/embed'
*/
const EmbedController = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: EmbedController.url(args, options),
    method: 'get',
})

EmbedController.definition = {
    methods: ["get","head"],
    url: '/agents/{agent}/embed',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Agents\Embed\EmbedController::__invoke
* @see app/Http/Controllers/Agents/Embed/EmbedController.php:12
* @route '/agents/{agent}/embed'
*/
EmbedController.url = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
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

    return EmbedController.definition.url
            .replace('{agent}', parsedArgs.agent.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Agents\Embed\EmbedController::__invoke
* @see app/Http/Controllers/Agents/Embed/EmbedController.php:12
* @route '/agents/{agent}/embed'
*/
EmbedController.get = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: EmbedController.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Agents\Embed\EmbedController::__invoke
* @see app/Http/Controllers/Agents/Embed/EmbedController.php:12
* @route '/agents/{agent}/embed'
*/
EmbedController.head = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: EmbedController.url(args, options),
    method: 'head',
})

export default EmbedController