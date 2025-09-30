import TicketsController from './TicketsController'
import TicketStoreController from './TicketStoreController'
import TicketShowController from './TicketShowController'
import TicketReplyController from './TicketReplyController'
import TicketChangePriorityController from './TicketChangePriorityController'

const Tickets = {
    TicketsController: Object.assign(TicketsController, TicketsController),
    TicketStoreController: Object.assign(TicketStoreController, TicketStoreController),
    TicketShowController: Object.assign(TicketShowController, TicketShowController),
    TicketReplyController: Object.assign(TicketReplyController, TicketReplyController),
    TicketChangePriorityController: Object.assign(TicketChangePriorityController, TicketChangePriorityController),
}

export default Tickets