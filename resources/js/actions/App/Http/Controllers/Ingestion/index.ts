import WebhookIngestionJobController from './WebhookIngestionJobController'
import IngestionDocumentController from './IngestionDocumentController'

const Ingestion = {
    WebhookIngestionJobController: Object.assign(WebhookIngestionJobController, WebhookIngestionJobController),
    IngestionDocumentController: Object.assign(IngestionDocumentController, IngestionDocumentController),
}

export default Ingestion