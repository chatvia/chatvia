import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Agents\Agents\AgentsGenerateRandomSubDomainController::__invoke
* @see app/Http/Controllers/Agents/Agents/AgentsGenerateRandomSubDomainController.php:10
* @route '/generate-random-subdomain'
*/
const AgentsGenerateRandomSubDomainController = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: AgentsGenerateRandomSubDomainController.url(options),
    method: 'get',
})

AgentsGenerateRandomSubDomainController.definition = {
    methods: ["get","head"],
    url: '/generate-random-subdomain',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Agents\Agents\AgentsGenerateRandomSubDomainController::__invoke
* @see app/Http/Controllers/Agents/Agents/AgentsGenerateRandomSubDomainController.php:10
* @route '/generate-random-subdomain'
*/
AgentsGenerateRandomSubDomainController.url = (options?: RouteQueryOptions) => {
    return AgentsGenerateRandomSubDomainController.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Agents\Agents\AgentsGenerateRandomSubDomainController::__invoke
* @see app/Http/Controllers/Agents/Agents/AgentsGenerateRandomSubDomainController.php:10
* @route '/generate-random-subdomain'
*/
AgentsGenerateRandomSubDomainController.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: AgentsGenerateRandomSubDomainController.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Agents\Agents\AgentsGenerateRandomSubDomainController::__invoke
* @see app/Http/Controllers/Agents/Agents/AgentsGenerateRandomSubDomainController.php:10
* @route '/generate-random-subdomain'
*/
AgentsGenerateRandomSubDomainController.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: AgentsGenerateRandomSubDomainController.url(options),
    method: 'head',
})

export default AgentsGenerateRandomSubDomainController