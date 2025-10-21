import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Agents\Sources\Text\AgentKnowledgeTextUpdateController::__invoke
* @see app/Http/Controllers/Agents/Sources/Text/AgentKnowledgeTextUpdateController.php:13
* @route '/agents/{agent}/sources/text/{id}'
*/
const AgentKnowledgeTextUpdateController = (args: { agent: number | { id: number }, id: string | number } | [agent: number | { id: number }, id: string | number ], options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: AgentKnowledgeTextUpdateController.url(args, options),
    method: 'patch',
})

AgentKnowledgeTextUpdateController.definition = {
    methods: ["patch"],
    url: '/agents/{agent}/sources/text/{id}',
} satisfies RouteDefinition<["patch"]>

/**
* @see \App\Http\Controllers\Agents\Sources\Text\AgentKnowledgeTextUpdateController::__invoke
* @see app/Http/Controllers/Agents/Sources/Text/AgentKnowledgeTextUpdateController.php:13
* @route '/agents/{agent}/sources/text/{id}'
*/
AgentKnowledgeTextUpdateController.url = (args: { agent: number | { id: number }, id: string | number } | [agent: number | { id: number }, id: string | number ], options?: RouteQueryOptions) => {
    if (Array.isArray(args)) {
        args = {
            agent: args[0],
            id: args[1],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        agent: typeof args.agent === 'object'
        ? args.agent.id
        : args.agent,
        id: args.id,
    }

    return AgentKnowledgeTextUpdateController.definition.url
            .replace('{agent}', parsedArgs.agent.toString())
            .replace('{id}', parsedArgs.id.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Agents\Sources\Text\AgentKnowledgeTextUpdateController::__invoke
* @see app/Http/Controllers/Agents/Sources/Text/AgentKnowledgeTextUpdateController.php:13
* @route '/agents/{agent}/sources/text/{id}'
*/
AgentKnowledgeTextUpdateController.patch = (args: { agent: number | { id: number }, id: string | number } | [agent: number | { id: number }, id: string | number ], options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: AgentKnowledgeTextUpdateController.url(args, options),
    method: 'patch',
})

export default AgentKnowledgeTextUpdateController