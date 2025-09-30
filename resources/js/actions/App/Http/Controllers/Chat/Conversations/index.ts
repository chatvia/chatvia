import ConversationCreateController from './ConversationCreateController'
import ConversationMessagesController from './ConversationMessagesController'
import ConversationsController from './ConversationsController'
import ConversationUpdate from './ConversationUpdate'
import ConversationDeleteController from './ConversationDeleteController'

const Conversations = {
    ConversationCreateController: Object.assign(ConversationCreateController, ConversationCreateController),
    ConversationMessagesController: Object.assign(ConversationMessagesController, ConversationMessagesController),
    ConversationsController: Object.assign(ConversationsController, ConversationsController),
    ConversationUpdate: Object.assign(ConversationUpdate, ConversationUpdate),
    ConversationDeleteController: Object.assign(ConversationDeleteController, ConversationDeleteController),
}

export default Conversations