import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Agents\Sources\AgentKnowledgeAudioController::__invoke
* @see app/Http/Controllers/Agents/Sources/AgentKnowledgeAudioController.php:11
* @route '/agents/{agent}/sources/audio'
*/
const AgentKnowledgeAudioController = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: AgentKnowledgeAudioController.url(args, options),
    method: 'get',
})

AgentKnowledgeAudioController.definition = {
    methods: ["get","head"],
    url: '/agents/{agent}/sources/audio',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Agents\Sources\AgentKnowledgeAudioController::__invoke
* @see app/Http/Controllers/Agents/Sources/AgentKnowledgeAudioController.php:11
* @route '/agents/{agent}/sources/audio'
*/
AgentKnowledgeAudioController.url = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
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

    return AgentKnowledgeAudioController.definition.url
            .replace('{agent}', parsedArgs.agent.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Agents\Sources\AgentKnowledgeAudioController::__invoke
* @see app/Http/Controllers/Agents/Sources/AgentKnowledgeAudioController.php:11
* @route '/agents/{agent}/sources/audio'
*/
AgentKnowledgeAudioController.get = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: AgentKnowledgeAudioController.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Agents\Sources\AgentKnowledgeAudioController::__invoke
* @see app/Http/Controllers/Agents/Sources/AgentKnowledgeAudioController.php:11
* @route '/agents/{agent}/sources/audio'
*/
AgentKnowledgeAudioController.head = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: AgentKnowledgeAudioController.url(args, options),
    method: 'head',
})

export default AgentKnowledgeAudioController