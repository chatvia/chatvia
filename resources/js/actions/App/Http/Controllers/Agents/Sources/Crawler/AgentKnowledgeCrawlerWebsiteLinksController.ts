import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Agents\Sources\Crawler\AgentKnowledgeCrawlerWebsiteLinksController::__invoke
* @see app/Http/Controllers/Agents/Sources/Crawler/AgentKnowledgeCrawlerWebsiteLinksController.php:11
* @route '/agents/{agent}/sources/websites/{website}/links'
*/
const AgentKnowledgeCrawlerWebsiteLinksController = (args: { agent: number | { id: number }, website: number | { id: number } } | [agent: number | { id: number }, website: number | { id: number } ], options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: AgentKnowledgeCrawlerWebsiteLinksController.url(args, options),
    method: 'get',
})

AgentKnowledgeCrawlerWebsiteLinksController.definition = {
    methods: ["get","head"],
    url: '/agents/{agent}/sources/websites/{website}/links',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Agents\Sources\Crawler\AgentKnowledgeCrawlerWebsiteLinksController::__invoke
* @see app/Http/Controllers/Agents/Sources/Crawler/AgentKnowledgeCrawlerWebsiteLinksController.php:11
* @route '/agents/{agent}/sources/websites/{website}/links'
*/
AgentKnowledgeCrawlerWebsiteLinksController.url = (args: { agent: number | { id: number }, website: number | { id: number } } | [agent: number | { id: number }, website: number | { id: number } ], options?: RouteQueryOptions) => {
    if (Array.isArray(args)) {
        args = {
            agent: args[0],
            website: args[1],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        agent: typeof args.agent === 'object'
        ? args.agent.id
        : args.agent,
        website: typeof args.website === 'object'
        ? args.website.id
        : args.website,
    }

    return AgentKnowledgeCrawlerWebsiteLinksController.definition.url
            .replace('{agent}', parsedArgs.agent.toString())
            .replace('{website}', parsedArgs.website.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Agents\Sources\Crawler\AgentKnowledgeCrawlerWebsiteLinksController::__invoke
* @see app/Http/Controllers/Agents/Sources/Crawler/AgentKnowledgeCrawlerWebsiteLinksController.php:11
* @route '/agents/{agent}/sources/websites/{website}/links'
*/
AgentKnowledgeCrawlerWebsiteLinksController.get = (args: { agent: number | { id: number }, website: number | { id: number } } | [agent: number | { id: number }, website: number | { id: number } ], options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: AgentKnowledgeCrawlerWebsiteLinksController.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Agents\Sources\Crawler\AgentKnowledgeCrawlerWebsiteLinksController::__invoke
* @see app/Http/Controllers/Agents/Sources/Crawler/AgentKnowledgeCrawlerWebsiteLinksController.php:11
* @route '/agents/{agent}/sources/websites/{website}/links'
*/
AgentKnowledgeCrawlerWebsiteLinksController.head = (args: { agent: number | { id: number }, website: number | { id: number } } | [agent: number | { id: number }, website: number | { id: number } ], options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: AgentKnowledgeCrawlerWebsiteLinksController.url(args, options),
    method: 'head',
})

export default AgentKnowledgeCrawlerWebsiteLinksController