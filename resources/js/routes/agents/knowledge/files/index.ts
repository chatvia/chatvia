import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\Agents\Agents\AgentKnowledgeFilesAnalyzeController::__invoke
* @see app/Http/Controllers/Agents/Agents/AgentKnowledgeFilesAnalyzeController.php:10
* @route '/agents/knowledge/files/analyze'
*/
export const analyze = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: analyze.url(options),
    method: 'post',
})

analyze.definition = {
    methods: ["post"],
    url: '/agents/knowledge/files/analyze',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Agents\Agents\AgentKnowledgeFilesAnalyzeController::__invoke
* @see app/Http/Controllers/Agents/Agents/AgentKnowledgeFilesAnalyzeController.php:10
* @route '/agents/knowledge/files/analyze'
*/
analyze.url = (options?: RouteQueryOptions) => {
    return analyze.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Agents\Agents\AgentKnowledgeFilesAnalyzeController::__invoke
* @see app/Http/Controllers/Agents/Agents/AgentKnowledgeFilesAnalyzeController.php:10
* @route '/agents/knowledge/files/analyze'
*/
analyze.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: analyze.url(options),
    method: 'post',
})

const files = {
    analyze: Object.assign(analyze, analyze),
}

export default files