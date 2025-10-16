import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Agents\Agents\AgentStoreController::__invoke
* @see app/Http/Controllers/Agents/Agents/AgentStoreController.php:15
* @route '/agents'
*/
const AgentStoreController = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: AgentStoreController.url(options),
    method: 'post',
})

AgentStoreController.definition = {
    methods: ["post"],
    url: '/agents',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Agents\Agents\AgentStoreController::__invoke
* @see app/Http/Controllers/Agents/Agents/AgentStoreController.php:15
* @route '/agents'
*/
AgentStoreController.url = (options?: RouteQueryOptions) => {
    return AgentStoreController.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Agents\Agents\AgentStoreController::__invoke
* @see app/Http/Controllers/Agents/Agents/AgentStoreController.php:15
* @route '/agents'
*/
AgentStoreController.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: AgentStoreController.url(options),
    method: 'post',
})

export default AgentStoreController