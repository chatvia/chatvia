import KnowledgeFilesShowController from './KnowledgeFilesShowController'
import KnowledgeFilesListController from './KnowledgeFilesListController'
import KnowledgeFilesDownload from './KnowledgeFilesDownload'

const KnowledgeFiles = {
    KnowledgeFilesShowController: Object.assign(KnowledgeFilesShowController, KnowledgeFilesShowController),
    KnowledgeFilesListController: Object.assign(KnowledgeFilesListController, KnowledgeFilesListController),
    KnowledgeFilesDownload: Object.assign(KnowledgeFilesDownload, KnowledgeFilesDownload),
}

export default KnowledgeFiles