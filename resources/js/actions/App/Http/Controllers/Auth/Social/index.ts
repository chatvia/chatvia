import GoogleAuthController from './GoogleAuthController'
import GoogleCallbackAuthController from './GoogleCallbackAuthController'
import MicrosoftAuthController from './MicrosoftAuthController'
import MicrosoftCallbackAuthController from './MicrosoftCallbackAuthController'

const Social = {
    GoogleAuthController: Object.assign(GoogleAuthController, GoogleAuthController),
    GoogleCallbackAuthController: Object.assign(GoogleCallbackAuthController, GoogleCallbackAuthController),
    MicrosoftAuthController: Object.assign(MicrosoftAuthController, MicrosoftAuthController),
    MicrosoftCallbackAuthController: Object.assign(MicrosoftCallbackAuthController, MicrosoftCallbackAuthController),
}

export default Social