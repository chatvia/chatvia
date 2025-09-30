import PrivacyController from './PrivacyController'
import TermsController from './TermsController'
import RefundController from './RefundController'
import ChangelogController from './ChangelogController'

const Landing = {
    PrivacyController: Object.assign(PrivacyController, PrivacyController),
    TermsController: Object.assign(TermsController, TermsController),
    RefundController: Object.assign(RefundController, RefundController),
    ChangelogController: Object.assign(ChangelogController, ChangelogController),
}

export default Landing