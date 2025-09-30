import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\Agents\Sources\Crawler\AgentKnowledgeWebsitesListController::__invoke
* @see app/Http/Controllers/Agents/Sources/Crawler/AgentKnowledgeWebsitesListController.php:10
* @route '/agents/{agent}/sources/websites/list'
*/
export const list = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: list.url(args, options),
    method: 'get',
})

list.definition = {
    methods: ["get","head"],
    url: '/agents/{agent}/sources/websites/list',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Agents\Sources\Crawler\AgentKnowledgeWebsitesListController::__invoke
* @see app/Http/Controllers/Agents/Sources/Crawler/AgentKnowledgeWebsitesListController.php:10
* @route '/agents/{agent}/sources/websites/list'
*/
list.url = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
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

    return list.definition.url
            .replace('{agent}', parsedArgs.agent.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Agents\Sources\Crawler\AgentKnowledgeWebsitesListController::__invoke
* @see app/Http/Controllers/Agents/Sources/Crawler/AgentKnowledgeWebsitesListController.php:10
* @route '/agents/{agent}/sources/websites/list'
*/
list.get = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: list.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Agents\Sources\Crawler\AgentKnowledgeWebsitesListController::__invoke
* @see app/Http/Controllers/Agents/Sources/Crawler/AgentKnowledgeWebsitesListController.php:10
* @route '/agents/{agent}/sources/websites/list'
*/
list.head = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: list.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Agents\Sources\Crawler\AgentKnowledgeWebsiteDeleteController::__invoke
* @see app/Http/Controllers/Agents/Sources/Crawler/AgentKnowledgeWebsiteDeleteController.php:11
* @route '/agents/{agent}/sources/websites/{website}'
*/
export const deleteMethod = (args: { agent: number | { id: number }, website: number | { id: number } } | [agent: number | { id: number }, website: number | { id: number } ], options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: deleteMethod.url(args, options),
    method: 'delete',
})

deleteMethod.definition = {
    methods: ["delete"],
    url: '/agents/{agent}/sources/websites/{website}',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\Agents\Sources\Crawler\AgentKnowledgeWebsiteDeleteController::__invoke
* @see app/Http/Controllers/Agents/Sources/Crawler/AgentKnowledgeWebsiteDeleteController.php:11
* @route '/agents/{agent}/sources/websites/{website}'
*/
deleteMethod.url = (args: { agent: number | { id: number }, website: number | { id: number } } | [agent: number | { id: number }, website: number | { id: number } ], options?: RouteQueryOptions) => {
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

    return deleteMethod.definition.url
            .replace('{agent}', parsedArgs.agent.toString())
            .replace('{website}', parsedArgs.website.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Agents\Sources\Crawler\AgentKnowledgeWebsiteDeleteController::__invoke
* @see app/Http/Controllers/Agents/Sources/Crawler/AgentKnowledgeWebsiteDeleteController.php:11
* @route '/agents/{agent}/sources/websites/{website}'
*/
deleteMethod.delete = (args: { agent: number | { id: number }, website: number | { id: number } } | [agent: number | { id: number }, website: number | { id: number } ], options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: deleteMethod.url(args, options),
    method: 'delete',
})

/**
* @see \App\Http\Controllers\Agents\Sources\Crawler\AgentKnowledgeCrawlerWebsiteLinksController::__invoke
* @see app/Http/Controllers/Agents/Sources/Crawler/AgentKnowledgeCrawlerWebsiteLinksController.php:10
* @route '/agents/{agent}/sources/websites/{website}/links'
*/
export const links = (args: { agent: number | { id: number }, website: number | { id: number } } | [agent: number | { id: number }, website: number | { id: number } ], options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: links.url(args, options),
    method: 'get',
})

links.definition = {
    methods: ["get","head"],
    url: '/agents/{agent}/sources/websites/{website}/links',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Agents\Sources\Crawler\AgentKnowledgeCrawlerWebsiteLinksController::__invoke
* @see app/Http/Controllers/Agents/Sources/Crawler/AgentKnowledgeCrawlerWebsiteLinksController.php:10
* @route '/agents/{agent}/sources/websites/{website}/links'
*/
links.url = (args: { agent: number | { id: number }, website: number | { id: number } } | [agent: number | { id: number }, website: number | { id: number } ], options?: RouteQueryOptions) => {
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

    return links.definition.url
            .replace('{agent}', parsedArgs.agent.toString())
            .replace('{website}', parsedArgs.website.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Agents\Sources\Crawler\AgentKnowledgeCrawlerWebsiteLinksController::__invoke
* @see app/Http/Controllers/Agents/Sources/Crawler/AgentKnowledgeCrawlerWebsiteLinksController.php:10
* @route '/agents/{agent}/sources/websites/{website}/links'
*/
links.get = (args: { agent: number | { id: number }, website: number | { id: number } } | [agent: number | { id: number }, website: number | { id: number } ], options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: links.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Agents\Sources\Crawler\AgentKnowledgeCrawlerWebsiteLinksController::__invoke
* @see app/Http/Controllers/Agents/Sources/Crawler/AgentKnowledgeCrawlerWebsiteLinksController.php:10
* @route '/agents/{agent}/sources/websites/{website}/links'
*/
links.head = (args: { agent: number | { id: number }, website: number | { id: number } } | [agent: number | { id: number }, website: number | { id: number } ], options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: links.url(args, options),
    method: 'head',
})

const websites = {
    list: Object.assign(list, list),
    delete: Object.assign(deleteMethod, deleteMethod),
    links: Object.assign(links, links),
}

export default websites