import BillingSubscribeController from './BillingSubscribeController'
import BillingSubscribeFailureController from './BillingSubscribeFailureController'
import BillingSubscriptionChangeController from './BillingSubscriptionChangeController'
import BillingSubscriptionScheduleCancelController from './BillingSubscriptionScheduleCancelController'
import BillingSubscriptionCancelController from './BillingSubscriptionCancelController'
import BillingSubscriptionResumeController from './BillingSubscriptionResumeController'

const Subscriptions = {
    BillingSubscribeController: Object.assign(BillingSubscribeController, BillingSubscribeController),
    BillingSubscribeFailureController: Object.assign(BillingSubscribeFailureController, BillingSubscribeFailureController),
    BillingSubscriptionChangeController: Object.assign(BillingSubscriptionChangeController, BillingSubscriptionChangeController),
    BillingSubscriptionScheduleCancelController: Object.assign(BillingSubscriptionScheduleCancelController, BillingSubscriptionScheduleCancelController),
    BillingSubscriptionCancelController: Object.assign(BillingSubscriptionCancelController, BillingSubscriptionCancelController),
    BillingSubscriptionResumeController: Object.assign(BillingSubscriptionResumeController, BillingSubscriptionResumeController),
}

export default Subscriptions