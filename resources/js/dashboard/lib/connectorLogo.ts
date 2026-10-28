/**
 * Image URL for a connector's logo.
 *
 * Uses the logo the server sends (`logo_url` in config/connectors.php, a file
 * under public/images/logos/connectors/) and otherwise a bundled generic icon.
 * It never falls back to a third-party icon CDN: that would send the viewer's
 * IP address to an outside service and break installs without internet access.
 */
import type { Connector } from '@/shared/types/connector';

export const GENERIC_CONNECTOR_LOGO = '/images/logos/connectors/generic.svg';

export function connectorLogoUrl(connector: Pick<Connector, 'logoUrl'> | null | undefined): string {
    return connector?.logoUrl || GENERIC_CONNECTOR_LOGO;
}
