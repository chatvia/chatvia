import Cashier from './Cashier'
import Horizon from './Horizon'
import Sanctum from './Sanctum'
import Telescope from './Telescope'

const Laravel = {
    Cashier: Object.assign(Cashier, Cashier),
    Horizon: Object.assign(Horizon, Horizon),
    Sanctum: Object.assign(Sanctum, Sanctum),
    Telescope: Object.assign(Telescope, Telescope),
}

export default Laravel