import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Agents\Agents\AgentsIndexController::__invoke
* @see app/Http/Controllers/Agents/Agents/AgentsIndexController.php:10
* @route '/agents'
*/
const AgentsIndexController = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: AgentsIndexController.url(options),
    method: 'get',
})

AgentsIndexController.definition = {
    methods: ["get","head"],
    url: '/agents',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Agents\Agents\AgentsIndexController::__invoke
* @see app/Http/Controllers/Agents/Agents/AgentsIndexController.php:10
* @route '/agents'
*/
AgentsIndexController.url = (options?: RouteQueryOptions) => {
    return AgentsIndexController.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Agents\Agents\AgentsIndexController::__invoke
* @see app/Http/Controllers/Agents/Agents/AgentsIndexController.php:10
* @route '/agents'
*/
AgentsIndexController.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: AgentsIndexController.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Agents\Agents\AgentsIndexController::__invoke
* @see app/Http/Controllers/Agents/Agents/AgentsIndexController.php:10
* @route '/agents'
*/
AgentsIndexController.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: AgentsIndexController.url(options),
    method: 'head',
})

export default AgentsIndexController