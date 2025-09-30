import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Agents\Files\AgentKnowledgeController::__invoke
* @see app/Http/Controllers/Agents/Files/AgentKnowledgeController.php:11
* @route '/agents/{agent}/sources'
*/
const AgentKnowledgeController = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: AgentKnowledgeController.url(args, options),
    method: 'get',
})

AgentKnowledgeController.definition = {
    methods: ["get","head"],
    url: '/agents/{agent}/sources',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Agents\Files\AgentKnowledgeController::__invoke
* @see app/Http/Controllers/Agents/Files/AgentKnowledgeController.php:11
* @route '/agents/{agent}/sources'
*/
AgentKnowledgeController.url = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
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

    return AgentKnowledgeController.definition.url
            .replace('{agent}', parsedArgs.agent.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Agents\Files\AgentKnowledgeController::__invoke
* @see app/Http/Controllers/Agents/Files/AgentKnowledgeController.php:11
* @route '/agents/{agent}/sources'
*/
AgentKnowledgeController.get = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: AgentKnowledgeController.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Agents\Files\AgentKnowledgeController::__invoke
* @see app/Http/Controllers/Agents/Files/AgentKnowledgeController.php:11
* @route '/agents/{agent}/sources'
*/
AgentKnowledgeController.head = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: AgentKnowledgeController.url(args, options),
    method: 'head',
})

export default AgentKnowledgeController