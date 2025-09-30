import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Chat\Auth\ChatMemberSwitchAgentController::__invoke
* @see app/Http/Controllers/Chat/Auth/ChatMemberSwitchAgentController.php:14
* @route '/chat/agents/switch/{agent}'
*/
const ChatMemberSwitchAgentController = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: ChatMemberSwitchAgentController.url(args, options),
    method: 'post',
})

ChatMemberSwitchAgentController.definition = {
    methods: ["post"],
    url: '/chat/agents/switch/{agent}',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Chat\Auth\ChatMemberSwitchAgentController::__invoke
* @see app/Http/Controllers/Chat/Auth/ChatMemberSwitchAgentController.php:14
* @route '/chat/agents/switch/{agent}'
*/
ChatMemberSwitchAgentController.url = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
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

    return ChatMemberSwitchAgentController.definition.url
            .replace('{agent}', parsedArgs.agent.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Chat\Auth\ChatMemberSwitchAgentController::__invoke
* @see app/Http/Controllers/Chat/Auth/ChatMemberSwitchAgentController.php:14
* @route '/chat/agents/switch/{agent}'
*/
ChatMemberSwitchAgentController.post = (args: { agent: number | { id: number } } | [agent: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: ChatMemberSwitchAgentController.url(args, options),
    method: 'post',
})

export default ChatMemberSwitchAgentController