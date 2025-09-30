import Subscriptions from './Subscriptions'
import Checkout from './Checkout'
import BillingController from './BillingController'
import Stripe from './Stripe'
import InvoicesController from './InvoicesController'
import Payments from './Payments'
import BillingPaymentMethodAddController from './BillingPaymentMethodAddController'
import BillingUpdateInformationController from './BillingUpdateInformationController'

const Billing = {
    Subscriptions: Object.assign(Subscriptions, Subscriptions),
    Checkout: Object.assign(Checkout, Checkout),
    BillingController: Object.assign(BillingController, BillingController),
    Stripe: Object.assign(Stripe, Stripe),
    InvoicesController: Object.assign(InvoicesController, InvoicesController),
    Payments: Object.assign(Payments, Payments),
    BillingPaymentMethodAddController: Object.assign(BillingPaymentMethodAddController, BillingPaymentMethodAddController),
    BillingUpdateInformationController: Object.assign(BillingUpdateInformationController, BillingUpdateInformationController),
}

export default Billing