import AgentTeamController from './AgentTeamController'
import AgentMemberInvitationSendController from './AgentMemberInvitationSendController'
import AgentMembersListController from './AgentMembersListController'
import AgentMembersDeleteController from './AgentMembersDeleteController'
import AgentMembersAccessGrantController from './AgentMembersAccessGrantController'
import AgentMembersInvitationCreateAccountController from './AgentMembersInvitationCreateAccountController'
import AgentMembersInvitationAcceptController from './AgentMembersInvitationAcceptController'

const Team = {
    AgentTeamController: Object.assign(AgentTeamController, AgentTeamController),
    AgentMemberInvitationSendController: Object.assign(AgentMemberInvitationSendController, AgentMemberInvitationSendController),
    AgentMembersListController: Object.assign(AgentMembersListController, AgentMembersListController),
    AgentMembersDeleteController: Object.assign(AgentMembersDeleteController, AgentMembersDeleteController),
    AgentMembersAccessGrantController: Object.assign(AgentMembersAccessGrantController, AgentMembersAccessGrantController),
    AgentMembersInvitationCreateAccountController: Object.assign(AgentMembersInvitationCreateAccountController, AgentMembersInvitationCreateAccountController),
    AgentMembersInvitationAcceptController: Object.assign(AgentMembersInvitationAcceptController, AgentMembersInvitationAcceptController),
}

export default Team