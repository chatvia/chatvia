import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../wayfinder'
import agentsFfbb41 from './agents'
/**
* @see \App\Http\Controllers\Chat\Auth\ChatMemberAgentsController::__invoke
* @see app/Http/Controllers/Chat/Auth/ChatMemberAgentsController.php:9
* @route '/chat/agents'
*/
export const agents = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: agents.url(options),
    method: 'get',
})

agents.definition = {
    methods: ["get","head"],
    url: '/chat/agents',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Chat\Auth\ChatMemberAgentsController::__invoke
* @see app/Http/Controllers/Chat/Auth/ChatMemberAgentsController.php:9
* @route '/chat/agents'
*/
agents.url = (options?: RouteQueryOptions) => {
    return agents.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Chat\Auth\ChatMemberAgentsController::__invoke
* @see app/Http/Controllers/Chat/Auth/ChatMemberAgentsController.php:9
* @route '/chat/agents'
*/
agents.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: agents.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Chat\Auth\ChatMemberAgentsController::__invoke
* @see app/Http/Controllers/Chat/Auth/ChatMemberAgentsController.php:9
* @route '/chat/agents'
*/
agents.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: agents.url(options),
    method: 'head',
})

const member = {
    agents: Object.assign(agents, agentsFfbb41),
}

export default member