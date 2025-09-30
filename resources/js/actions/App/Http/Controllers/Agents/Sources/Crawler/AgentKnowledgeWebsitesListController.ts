import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Agents\Sources\Crawler\AgentKnowledgeWebsitesListController::__invoke
* @see app/Http/Controllers/Agents/Sources/Crawler/AgentKnowledgeWebsitesListController.php:10
* @route '/agents/{agent}/sources/websites/list'
*/
const AgentKnowledgeWebsitesListController = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: AgentKnowledgeWebsitesListController.url(args, options),
    method: 'get',
})

AgentKnowledgeWebsitesListController.definition = {
    methods: ["get","head"],
    url: '/agents/{agent}/sources/websites/list',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Agents\Sources\Crawler\AgentKnowledgeWebsitesListController::__invoke
* @see app/Http/Controllers/Agents/Sources/Crawler/AgentKnowledgeWebsitesListController.php:10
* @route '/agents/{agent}/sources/websites/list'
*/
AgentKnowledgeWebsitesListController.url = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
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

    return AgentKnowledgeWebsitesListController.definition.url
            .replace('{agent}', parsedArgs.agent.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Agents\Sources\Crawler\AgentKnowledgeWebsitesListController::__invoke
* @see app/Http/Controllers/Agents/Sources/Crawler/AgentKnowledgeWebsitesListController.php:10
* @route '/agents/{agent}/sources/websites/list'
*/
AgentKnowledgeWebsitesListController.get = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: AgentKnowledgeWebsitesListController.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Agents\Sources\Crawler\AgentKnowledgeWebsitesListController::__invoke
* @see app/Http/Controllers/Agents/Sources/Crawler/AgentKnowledgeWebsitesListController.php:10
* @route '/agents/{agent}/sources/websites/list'
*/
AgentKnowledgeWebsitesListController.head = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: AgentKnowledgeWebsitesListController.url(args, options),
    method: 'head',
})

export default AgentKnowledgeWebsitesListController