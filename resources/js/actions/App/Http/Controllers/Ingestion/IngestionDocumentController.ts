import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Ingestion\IngestionDocumentController::__invoke
* @see app/Http/Controllers/Ingestion/IngestionDocumentController.php:12
* @route '/ingestion/file'
*/
const IngestionDocumentController = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: IngestionDocumentController.url(options),
    method: 'post',
})

IngestionDocumentController.definition = {
    methods: ["post"],
    url: '/ingestion/file',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Ingestion\IngestionDocumentController::__invoke
* @see app/Http/Controllers/Ingestion/IngestionDocumentController.php:12
* @route '/ingestion/file'
*/
IngestionDocumentController.url = (options?: RouteQueryOptions) => {
    return IngestionDocumentController.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Ingestion\IngestionDocumentController::__invoke
* @see app/Http/Controllers/Ingestion/IngestionDocumentController.php:12
* @route '/ingestion/file'
*/
IngestionDocumentController.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: IngestionDocumentController.url(options),
    method: 'post',
})

export default IngestionDocumentController