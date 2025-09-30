import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Agents\Sources\Text\AgentKnowledgeTextListController::__invoke
* @see app/Http/Controllers/Agents/Sources/Text/AgentKnowledgeTextListController.php:9
* @route '/agents/{agent}/sources/text/list'
*/
const AgentKnowledgeTextListController = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: AgentKnowledgeTextListController.url(args, options),
    method: 'get',
})

AgentKnowledgeTextListController.definition = {
    methods: ["get","head"],
    url: '/agents/{agent}/sources/text/list',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Agents\Sources\Text\AgentKnowledgeTextListController::__invoke
* @see app/Http/Controllers/Agents/Sources/Text/AgentKnowledgeTextListController.php:9
* @route '/agents/{agent}/sources/text/list'
*/
AgentKnowledgeTextListController.url = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
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

    return AgentKnowledgeTextListController.definition.url
            .replace('{agent}', parsedArgs.agent.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Agents\Sources\Text\AgentKnowledgeTextListController::__invoke
* @see app/Http/Controllers/Agents/Sources/Text/AgentKnowledgeTextListController.php:9
* @route '/agents/{agent}/sources/text/list'
*/
AgentKnowledgeTextListController.get = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: AgentKnowledgeTextListController.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Agents\Sources\Text\AgentKnowledgeTextListController::__invoke
* @see app/Http/Controllers/Agents/Sources/Text/AgentKnowledgeTextListController.php:9
* @route '/agents/{agent}/sources/text/list'
*/
AgentKnowledgeTextListController.head = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: AgentKnowledgeTextListController.url(args, options),
    method: 'head',
})

export default AgentKnowledgeTextListController