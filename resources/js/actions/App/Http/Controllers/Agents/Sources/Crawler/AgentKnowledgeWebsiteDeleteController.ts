import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Agents\Sources\Crawler\AgentKnowledgeWebsiteDeleteController::__invoke
* @see app/Http/Controllers/Agents/Sources/Crawler/AgentKnowledgeWebsiteDeleteController.php:11
* @route '/agents/{agent}/sources/websites/{website}'
*/
const AgentKnowledgeWebsiteDeleteController = (args: { agent: number | { id: number }, website: number | { id: number } } | [agent: number | { id: number }, website: number | { id: number } ], options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: AgentKnowledgeWebsiteDeleteController.url(args, options),
    method: 'delete',
})

AgentKnowledgeWebsiteDeleteController.definition = {
    methods: ["delete"],
    url: '/agents/{agent}/sources/websites/{website}',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\Agents\Sources\Crawler\AgentKnowledgeWebsiteDeleteController::__invoke
* @see app/Http/Controllers/Agents/Sources/Crawler/AgentKnowledgeWebsiteDeleteController.php:11
* @route '/agents/{agent}/sources/websites/{website}'
*/
AgentKnowledgeWebsiteDeleteController.url = (args: { agent: number | { id: number }, website: number | { id: number } } | [agent: number | { id: number }, website: number | { id: number } ], options?: RouteQueryOptions) => {
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

    return AgentKnowledgeWebsiteDeleteController.definition.url
            .replace('{agent}', parsedArgs.agent.toString())
            .replace('{website}', parsedArgs.website.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Agents\Sources\Crawler\AgentKnowledgeWebsiteDeleteController::__invoke
* @see app/Http/Controllers/Agents/Sources/Crawler/AgentKnowledgeWebsiteDeleteController.php:11
* @route '/agents/{agent}/sources/websites/{website}'
*/
AgentKnowledgeWebsiteDeleteController.delete = (args: { agent: number | { id: number }, website: number | { id: number } } | [agent: number | { id: number }, website: number | { id: number } ], options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: AgentKnowledgeWebsiteDeleteController.url(args, options),
    method: 'delete',
})

export default AgentKnowledgeWebsiteDeleteController