import exports from './exports'
import imports from './imports'
import admin from './admin'

const filament = {
    exports: Object.assign(exports, exports),
    imports: Object.assign(imports, imports),
    admin: Object.assign(admin, admin),
}

export default filament