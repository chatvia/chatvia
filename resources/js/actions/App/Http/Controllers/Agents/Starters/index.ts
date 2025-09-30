import StartersShowController from './StartersShowController'
import StartersStoreController from './StartersStoreController'
import StarterDeleteController from './StarterDeleteController'

const Starters = {
    StartersShowController: Object.assign(StartersShowController, StartersShowController),
    StartersStoreController: Object.assign(StartersStoreController, StartersStoreController),
    StarterDeleteController: Object.assign(StarterDeleteController, StarterDeleteController),
}

export default Starters