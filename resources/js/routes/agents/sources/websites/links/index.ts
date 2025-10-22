import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Agents\Sources\Crawler\AgentKnowledgeCrawlerWebsiteLinkDeleteController::__invoke
* @see app/Http/Controllers/Agents/Sources/Crawler/AgentKnowledgeCrawlerWebsiteLinkDeleteController.php:12
* @route '/agents/{agent}/sources/websites/{website}/links/{link}'
*/
export const deleteMethod = (args: { agent: number | { id: number }, website: number | { id: number }, link: number | { id: number } } | [agent: number | { id: number }, website: number | { id: number }, link: number | { id: number } ], options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: deleteMethod.url(args, options),
    method: 'delete',
})

deleteMethod.definition = {
    methods: ["delete"],
    url: '/agents/{agent}/sources/websites/{website}/links/{link}',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\Agents\Sources\Crawler\AgentKnowledgeCrawlerWebsiteLinkDeleteController::__invoke
* @see app/Http/Controllers/Agents/Sources/Crawler/AgentKnowledgeCrawlerWebsiteLinkDeleteController.php:12
* @route '/agents/{agent}/sources/websites/{website}/links/{link}'
*/
deleteMethod.url = (args: { agent: number | { id: number }, website: number | { id: number }, link: number | { id: number } } | [agent: number | { id: number }, website: number | { id: number }, link: number | { id: number } ], options?: RouteQueryOptions) => {
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

    return deleteMethod.definition.url
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
deleteMethod.delete = (args: { agent: number | { id: number }, website: number | { id: number }, link: number | { id: number } } | [agent: number | { id: number }, website: number | { id: number }, link: number | { id: number } ], options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: deleteMethod.url(args, options),
    method: 'delete',
})

/**
* @see \App\Http\Controllers\Agents\Sources\Crawler\AgentKnowledgeCrawlerLinkRetrainController::__invoke
* @see app/Http/Controllers/Agents/Sources/Crawler/AgentKnowledgeCrawlerLinkRetrainController.php:12
* @route '/agents/{agent}/sources/websites/{website}/links/{link}/retrain'
*/
export const retrain = (args: { agent: number | { id: number }, website: number | { id: number }, link: number | { id: number } } | [agent: number | { id: number }, website: number | { id: number }, link: number | { id: number } ], options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: retrain.url(args, options),
    method: 'post',
})

retrain.definition = {
    methods: ["post"],
    url: '/agents/{agent}/sources/websites/{website}/links/{link}/retrain',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Agents\Sources\Crawler\AgentKnowledgeCrawlerLinkRetrainController::__invoke
* @see app/Http/Controllers/Agents/Sources/Crawler/AgentKnowledgeCrawlerLinkRetrainController.php:12
* @route '/agents/{agent}/sources/websites/{website}/links/{link}/retrain'
*/
retrain.url = (args: { agent: number | { id: number }, website: number | { id: number }, link: number | { id: number } } | [agent: number | { id: number }, website: number | { id: number }, link: number | { id: number } ], options?: RouteQueryOptions) => {
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

    return retrain.definition.url
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
retrain.post = (args: { agent: number | { id: number }, website: number | { id: number }, link: number | { id: number } } | [agent: number | { id: number }, website: number | { id: number }, link: number | { id: number } ], options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: retrain.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Agents\Sources\Crawler\AgentKnowledgeCrawlerWebsiteLinksBulkDeleteController::__invoke
* @see app/Http/Controllers/Agents/Sources/Crawler/AgentKnowledgeCrawlerWebsiteLinksBulkDeleteController.php:11
* @route '/agents/{agent}/sources/websites/{website}/links/bulk-delete'
*/
export const bulkDelete = (args: { agent: number | { id: number }, website: number | { id: number } } | [agent: number | { id: number }, website: number | { id: number } ], options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: bulkDelete.url(args, options),
    method: 'post',
})

bulkDelete.definition = {
    methods: ["post"],
    url: '/agents/{agent}/sources/websites/{website}/links/bulk-delete',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Agents\Sources\Crawler\AgentKnowledgeCrawlerWebsiteLinksBulkDeleteController::__invoke
* @see app/Http/Controllers/Agents/Sources/Crawler/AgentKnowledgeCrawlerWebsiteLinksBulkDeleteController.php:11
* @route '/agents/{agent}/sources/websites/{website}/links/bulk-delete'
*/
bulkDelete.url = (args: { agent: number | { id: number }, website: number | { id: number } } | [agent: number | { id: number }, website: number | { id: number } ], options?: RouteQueryOptions) => {
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

    return bulkDelete.definition.url
            .replace('{agent}', parsedArgs.agent.toString())
            .replace('{website}', parsedArgs.website.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Agents\Sources\Crawler\AgentKnowledgeCrawlerWebsiteLinksBulkDeleteController::__invoke
* @see app/Http/Controllers/Agents/Sources/Crawler/AgentKnowledgeCrawlerWebsiteLinksBulkDeleteController.php:11
* @route '/agents/{agent}/sources/websites/{website}/links/bulk-delete'
*/
bulkDelete.post = (args: { agent: number | { id: number }, website: number | { id: number } } | [agent: number | { id: number }, website: number | { id: number } ], options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: bulkDelete.url(args, options),
    method: 'post',
})

const links = {
    delete: Object.assign(deleteMethod, deleteMethod),
    retrain: Object.assign(retrain, retrain),
    bulkDelete: Object.assign(bulkDelete, bulkDelete),
}

export default links