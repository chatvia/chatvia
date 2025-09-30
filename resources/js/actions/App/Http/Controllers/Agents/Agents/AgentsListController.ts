import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Agents\Agents\AgentsListController::__invoke
* @see app/Http/Controllers/Agents/Agents/AgentsListController.php:10
* @route '/agents/list'
*/
const AgentsListController = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: AgentsListController.url(options),
    method: 'get',
})

AgentsListController.definition = {
    methods: ["get","head"],
    url: '/agents/list',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Agents\Agents\AgentsListController::__invoke
* @see app/Http/Controllers/Agents/Agents/AgentsListController.php:10
* @route '/agents/list'
*/
AgentsListController.url = (options?: RouteQueryOptions) => {
    return AgentsListController.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Agents\Agents\AgentsListController::__invoke
* @see app/Http/Controllers/Agents/Agents/AgentsListController.php:10
* @route '/agents/list'
*/
AgentsListController.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: AgentsListController.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Agents\Agents\AgentsListController::__invoke
* @see app/Http/Controllers/Agents/Agents/AgentsListController.php:10
* @route '/agents/list'
*/
AgentsListController.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: AgentsListController.url(options),
    method: 'head',
})

export default AgentsListController