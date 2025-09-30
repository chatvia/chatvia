import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../wayfinder'
/**
* @see \App\Http\Controllers\Ingestion\WebhookIngestionJobController::__invoke
* @see app/Http/Controllers/Ingestion/WebhookIngestionJobController.php:10
* @route '/ingestion/webhook'
*/
export const webhook = (options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: webhook.url(options),
    method: 'patch',
})

webhook.definition = {
    methods: ["patch"],
    url: '/ingestion/webhook',
} satisfies RouteDefinition<["patch"]>

/**
* @see \App\Http\Controllers\Ingestion\WebhookIngestionJobController::__invoke
* @see app/Http/Controllers/Ingestion/WebhookIngestionJobController.php:10
* @route '/ingestion/webhook'
*/
webhook.url = (options?: RouteQueryOptions) => {
    return webhook.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Ingestion\WebhookIngestionJobController::__invoke
* @see app/Http/Controllers/Ingestion/WebhookIngestionJobController.php:10
* @route '/ingestion/webhook'
*/
webhook.patch = (options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: webhook.url(options),
    method: 'patch',
})

/**
* @see \App\Http\Controllers\Ingestion\IngestionDocumentController::__invoke
* @see app/Http/Controllers/Ingestion/IngestionDocumentController.php:12
* @route '/ingestion/file'
*/
export const file = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: file.url(options),
    method: 'post',
})

file.definition = {
    methods: ["post"],
    url: '/ingestion/file',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Ingestion\IngestionDocumentController::__invoke
* @see app/Http/Controllers/Ingestion/IngestionDocumentController.php:12
* @route '/ingestion/file'
*/
file.url = (options?: RouteQueryOptions) => {
    return file.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Ingestion\IngestionDocumentController::__invoke
* @see app/Http/Controllers/Ingestion/IngestionDocumentController.php:12
* @route '/ingestion/file'
*/
file.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: file.url(options),
    method: 'post',
})

const ingestion = {
    webhook: Object.assign(webhook, webhook),
    file: Object.assign(file, file),
}

export default ingestion