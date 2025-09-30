import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Ingestion\WebhookIngestionJobController::__invoke
* @see app/Http/Controllers/Ingestion/WebhookIngestionJobController.php:10
* @route '/ingestion/webhook'
*/
const WebhookIngestionJobController = (options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: WebhookIngestionJobController.url(options),
    method: 'patch',
})

WebhookIngestionJobController.definition = {
    methods: ["patch"],
    url: '/ingestion/webhook',
} satisfies RouteDefinition<["patch"]>

/**
* @see \App\Http\Controllers\Ingestion\WebhookIngestionJobController::__invoke
* @see app/Http/Controllers/Ingestion/WebhookIngestionJobController.php:10
* @route '/ingestion/webhook'
*/
WebhookIngestionJobController.url = (options?: RouteQueryOptions) => {
    return WebhookIngestionJobController.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Ingestion\WebhookIngestionJobController::__invoke
* @see app/Http/Controllers/Ingestion/WebhookIngestionJobController.php:10
* @route '/ingestion/webhook'
*/
WebhookIngestionJobController.patch = (options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: WebhookIngestionJobController.url(options),
    method: 'patch',
})

export default WebhookIngestionJobController