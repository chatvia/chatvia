import ChatAppearanceController from './ChatAppearanceController'
import Auth from './Auth'
import ChatHomeController from './ChatHomeController'
import ChatEmbedHomeController from './ChatEmbedHomeController'
import ChatEmbedController from './ChatEmbedController'
import ChatModelsListController from './ChatModelsListController'
import ChatStreamController from './ChatStreamController'
import Conversations from './Conversations'
import ChatDeleteAllConversationsController from './ChatDeleteAllConversationsController'
import ChatStartersController from './ChatStartersController'
import ChatMessageFeedbackController from './ChatMessageFeedbackController'
import ChatMessageDeleteController from './ChatMessageDeleteController'
import Export from './Export'
import ChatSourcesController from './ChatSourcesController'
import ChatController from './ChatController'
import ChatMessagesUsage from './ChatMessagesUsage'

const Chat = {
    ChatAppearanceController: Object.assign(ChatAppearanceController, ChatAppearanceController),
    Auth: Object.assign(Auth, Auth),
    ChatHomeController: Object.assign(ChatHomeController, ChatHomeController),
    ChatEmbedHomeController: Object.assign(ChatEmbedHomeController, ChatEmbedHomeController),
    ChatEmbedController: Object.assign(ChatEmbedController, ChatEmbedController),
    ChatModelsListController: Object.assign(ChatModelsListController, ChatModelsListController),
    ChatStreamController: Object.assign(ChatStreamController, ChatStreamController),
    Conversations: Object.assign(Conversations, Conversations),
    ChatDeleteAllConversationsController: Object.assign(ChatDeleteAllConversationsController, ChatDeleteAllConversationsController),
    ChatStartersController: Object.assign(ChatStartersController, ChatStartersController),
    ChatMessageFeedbackController: Object.assign(ChatMessageFeedbackController, ChatMessageFeedbackController),
    ChatMessageDeleteController: Object.assign(ChatMessageDeleteController, ChatMessageDeleteController),
    Export: Object.assign(Export, Export),
    ChatSourcesController: Object.assign(ChatSourcesController, ChatSourcesController),
    ChatController: Object.assign(ChatController, ChatController),
    ChatMessagesUsage: Object.assign(ChatMessagesUsage, ChatMessagesUsage),
}

export default Chat