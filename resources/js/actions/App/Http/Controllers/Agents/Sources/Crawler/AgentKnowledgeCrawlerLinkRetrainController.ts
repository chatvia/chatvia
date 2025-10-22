import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Agents\Sources\Crawler\AgentKnowledgeCrawlerLinkRetrainController::__invoke
* @see app/Http/Controllers/Agents/Sources/Crawler/AgentKnowledgeCrawlerLinkRetrainController.php:12
* @route '/agents/{agent}/sources/websites/{website}/links/{link}/retrain'
*/
const AgentKnowledgeCrawlerLinkRetrainController = (args: { agent: number | { id: number }, website: number | { id: number }, link: number | { id: number } } | [agent: number | { id: number }, website: number | { id: number }, link: number | { id: number } ], options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: AgentKnowledgeCrawlerLinkRetrainController.url(args, options),
    method: 'post',
})

AgentKnowledgeCrawlerLinkRetrainController.definition = {
    methods: ["post"],
    url: '/agents/{agent}/sources/websites/{website}/links/{link}/retrain',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Agents\Sources\Crawler\AgentKnowledgeCrawlerLinkRetrainController::__invoke
* @see app/Http/Controllers/Agents/Sources/Crawler/AgentKnowledgeCrawlerLinkRetrainController.php:12
* @route '/agents/{agent}/sources/websites/{website}/links/{link}/retrain'
*/
AgentKnowledgeCrawlerLinkRetrainController.url = (args: { agent: number | { id: number }, website: number | { id: number }, link: number | { id: number } } | [agent: number | { id: number }, website: number | { id: number }, link: number | { id: number } ], options?: RouteQueryOptions) => {
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

    return AgentKnowledgeCrawlerLinkRetrainController.definition.url
            .replace('{agent}', parsedArgs.agent.toString())
            .replace('{website}', parsedArgs.website.toString())
            .replace('{link}', parsedArgs.link.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Agents\Sources\Crawler\AgentKnowledgeCrawlerLinkRetrainController::__invoke
* @see app/Http/Controllers/Agents/Sources/Crawler/AgentKnowledgeCrawlerLinkRetrainController.php:12
* @route '/agents/{agent}/sources/websites/{website}/links/{link}/retrain'
*/
AgentKnowledgeCrawlerLinkRetrainController.post = (args: { agent: number | { id: number }, website: number | { id: number }, link: number | { id: number } } | [agent: number | { id: number }, website: number | { id: number }, link: number | { id: number } ], options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: AgentKnowledgeCrawlerLinkRetrainController.url(args, options),
    method: 'post',
})

export default AgentKnowledgeCrawlerLinkRetrainController