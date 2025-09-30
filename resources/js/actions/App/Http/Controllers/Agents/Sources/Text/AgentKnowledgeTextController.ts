import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Agents\Sources\Text\AgentKnowledgeTextController::__invoke
* @see app/Http/Controllers/Agents/Sources/Text/AgentKnowledgeTextController.php:12
* @route '/agents/{agent}/sources/text'
*/
const AgentKnowledgeTextController = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: AgentKnowledgeTextController.url(args, options),
    method: 'get',
})

AgentKnowledgeTextController.definition = {
    methods: ["get","head"],
    url: '/agents/{agent}/sources/text',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Agents\Sources\Text\AgentKnowledgeTextController::__invoke
* @see app/Http/Controllers/Agents/Sources/Text/AgentKnowledgeTextController.php:12
* @route '/agents/{agent}/sources/text'
*/
AgentKnowledgeTextController.url = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
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

    return AgentKnowledgeTextController.definition.url
            .replace('{agent}', parsedArgs.agent.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Agents\Sources\Text\AgentKnowledgeTextController::__invoke
* @see app/Http/Controllers/Agents/Sources/Text/AgentKnowledgeTextController.php:12
* @route '/agents/{agent}/sources/text'
*/
AgentKnowledgeTextController.get = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: AgentKnowledgeTextController.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Agents\Sources\Text\AgentKnowledgeTextController::__invoke
* @see app/Http/Controllers/Agents/Sources/Text/AgentKnowledgeTextController.php:12
* @route '/agents/{agent}/sources/text'
*/
AgentKnowledgeTextController.head = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: AgentKnowledgeTextController.url(args, options),
    method: 'head',
})

export default AgentKnowledgeTextController