import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Agents\Sources\Crawler\AgentKnowledgeCrawlerWebsiteLinkDeleteController::__invoke
* @see app/Http/Controllers/Agents/Sources/Crawler/AgentKnowledgeCrawlerWebsiteLinkDeleteController.php:12
* @route '/agents/{agent}/sources/websites/{website}/links/{link}'
*/
const AgentKnowledgeCrawlerWebsiteLinkDeleteController = (args: { agent: number | { id: number }, website: number | { id: number }, link: number | { id: number } } | [agent: number | { id: number }, website: number | { id: number }, link: number | { id: number } ], options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: AgentKnowledgeCrawlerWebsiteLinkDeleteController.url(args, options),
    method: 'delete',
})

AgentKnowledgeCrawlerWebsiteLinkDeleteController.definition = {
    methods: ["delete"],
    url: '/agents/{agent}/sources/websites/{website}/links/{link}',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\Agents\Sources\Crawler\AgentKnowledgeCrawlerWebsiteLinkDeleteController::__invoke
* @see app/Http/Controllers/Agents/Sources/Crawler/AgentKnowledgeCrawlerWebsiteLinkDeleteController.php:12
* @route '/agents/{agent}/sources/websites/{website}/links/{link}'
*/
AgentKnowledgeCrawlerWebsiteLinkDeleteController.url = (args: { agent: number | { id: number }, website: number | { id: number }, link: number | { id: number } } | [agent: number | { id: number }, website: number | { id: number }, link: number | { id: number } ], options?: RouteQueryOptions) => {
    if (Array.isArray(args)) {
        args = {
            agent: args[0],
            website: args[1],
            link: args[2],
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
        link: typeof args.link === 'object'
        ? args.link.id
        : args.link,
    }

    return AgentKnowledgeCrawlerWebsiteLinkDeleteController.definition.url
            .replace('{agent}', parsedArgs.agent.toString())
            .replace('{website}', parsedArgs.website.toString())
            .replace('{link}', parsedArgs.link.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Agents\Sources\Crawler\AgentKnowledgeCrawlerWebsiteLinkDeleteController::__invoke
* @see app/Http/Controllers/Agents/Sources/Crawler/AgentKnowledgeCrawlerWebsiteLinkDeleteController.php:12
* @route '/agents/{agent}/sources/websites/{website}/links/{link}'
*/
AgentKnowledgeCrawlerWebsiteLinkDeleteController.delete = (args: { agent: number | { id: number }, website: number | { id: number }, link: number | { id: number } } | [agent: number | { id: number }, website: number | { id: number }, link: number | { id: number } ], options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: AgentKnowledgeCrawlerWebsiteLinkDeleteController.url(args, options),
    method: 'delete',
})

export default AgentKnowledgeCrawlerWebsiteLinkDeleteController