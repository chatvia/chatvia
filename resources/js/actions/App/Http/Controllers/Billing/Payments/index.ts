import BillingPaymentMethodsController from './BillingPaymentMethodsController'
import BillingPaymentMethodDeleteController from './BillingPaymentMethodDeleteController'
import BillingPaymentMethodDefaultController from './BillingPaymentMethodDefaultController'

const Payments = {
    BillingPaymentMethodsController: Object.assign(BillingPaymentMethodsController, BillingPaymentMethodsController),
    BillingPaymentMethodDeleteController: Object.assign(BillingPaymentMethodDeleteController, BillingPaymentMethodDeleteController),
    BillingPaymentMethodDefaultController: Object.assign(BillingPaymentMethodDefaultController, BillingPaymentMethodDefaultController),
}

export default Payments