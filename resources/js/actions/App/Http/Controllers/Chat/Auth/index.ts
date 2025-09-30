import ChatShowLoginController from './ChatShowLoginController'
import ChatLoginController from './ChatLoginController'
import ChatMemberAgentsController from './ChatMemberAgentsController'
import ChatMemberSwitchAgentController from './ChatMemberSwitchAgentController'
import ChatLogoutController from './ChatLogoutController'
import ChatProfileUpdateController from './ChatProfileUpdateController'

const Auth = {
    ChatShowLoginController: Object.assign(ChatShowLoginController, ChatShowLoginController),
    ChatLoginController: Object.assign(ChatLoginController, ChatLoginController),
    ChatMemberAgentsController: Object.assign(ChatMemberAgentsController, ChatMemberAgentsController),
    ChatMemberSwitchAgentController: Object.assign(ChatMemberSwitchAgentController, ChatMemberSwitchAgentController),
    ChatLogoutController: Object.assign(ChatLogoutController, ChatLogoutController),
    ChatProfileUpdateController: Object.assign(ChatProfileUpdateController, ChatProfileUpdateController),
}

export default Auth