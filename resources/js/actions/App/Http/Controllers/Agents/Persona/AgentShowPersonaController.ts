import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Agents\Persona\AgentShowPersonaController::__invoke
* @see app/Http/Controllers/Agents/Persona/AgentShowPersonaController.php:12
* @route '/agents/{agent}/persona'
*/
const AgentShowPersonaController = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: AgentShowPersonaController.url(args, options),
    method: 'get',
})

AgentShowPersonaController.definition = {
    methods: ["get","head"],
    url: '/agents/{agent}/persona',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Agents\Persona\AgentShowPersonaController::__invoke
* @see app/Http/Controllers/Agents/Persona/AgentShowPersonaController.php:12
* @route '/agents/{agent}/persona'
*/
AgentShowPersonaController.url = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
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

    return AgentShowPersonaController.definition.url
            .replace('{agent}', parsedArgs.agent.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Agents\Persona\AgentShowPersonaController::__invoke
* @see app/Http/Controllers/Agents/Persona/AgentShowPersonaController.php:12
* @route '/agents/{agent}/persona'
*/
AgentShowPersonaController.get = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: AgentShowPersonaController.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Agents\Persona\AgentShowPersonaController::__invoke
* @see app/Http/Controllers/Agents/Persona/AgentShowPersonaController.php:12
* @route '/agents/{agent}/persona'
*/
AgentShowPersonaController.head = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: AgentShowPersonaController.url(args, options),
    method: 'head',
})

export default AgentShowPersonaController