import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Agents\Sources\Crawler\AgentKnowledgeCrawlerWebsiteLinksBulkDeleteController::__invoke
* @see app/Http/Controllers/Agents/Sources/Crawler/AgentKnowledgeCrawlerWebsiteLinksBulkDeleteController.php:11
* @route '/agents/{agent}/sources/websites/{website}/links/bulk-delete'
*/
const AgentKnowledgeCrawlerWebsiteLinksBulkDeleteController = (args: { agent: number | { id: number }, website: number | { id: number } } | [agent: number | { id: number }, website: number | { id: number } ], options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: AgentKnowledgeCrawlerWebsiteLinksBulkDeleteController.url(args, options),
    method: 'post',
})

AgentKnowledgeCrawlerWebsiteLinksBulkDeleteController.definition = {
    methods: ["post"],
    url: '/agents/{agent}/sources/websites/{website}/links/bulk-delete',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Agents\Sources\Crawler\AgentKnowledgeCrawlerWebsiteLinksBulkDeleteController::__invoke
* @see app/Http/Controllers/Agents/Sources/Crawler/AgentKnowledgeCrawlerWebsiteLinksBulkDeleteController.php:11
* @route '/agents/{agent}/sources/websites/{website}/links/bulk-delete'
*/
AgentKnowledgeCrawlerWebsiteLinksBulkDeleteController.url = (args: { agent: number | { id: number }, website: number | { id: number } } | [agent: number | { id: number }, website: number | { id: number } ], options?: RouteQueryOptions) => {
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

    return AgentKnowledgeCrawlerWebsiteLinksBulkDeleteController.definition.url
            .replace('{agent}', parsedArgs.agent.toString())
            .replace('{website}', parsedArgs.website.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Agents\Sources\Crawler\AgentKnowledgeCrawlerWebsiteLinksBulkDeleteController::__invoke
* @see app/Http/Controllers/Agents/Sources/Crawler/AgentKnowledgeCrawlerWebsiteLinksBulkDeleteController.php:11
* @route '/agents/{agent}/sources/websites/{website}/links/bulk-delete'
*/
AgentKnowledgeCrawlerWebsiteLinksBulkDeleteController.post = (args: { agent: number | { id: number }, website: number | { id: number } } | [agent: number | { id: number }, website: number | { id: number } ], options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: AgentKnowledgeCrawlerWebsiteLinksBulkDeleteController.url(args, options),
    method: 'post',
})

export default AgentKnowledgeCrawlerWebsiteLinksBulkDeleteController