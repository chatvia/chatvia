/**
 * Whether passkeys can work at this page's address.
 *
 * Browsers bind a passkey to a domain name and refuse one on a bare IP address
 * (an install reached at https://203.0.113.5 because the server has no domain),
 * so the passkey options are hidden there instead of failing with the passkey
 * library's developer-facing error.
 */
export function passkeysAllowedOn(hostname: string): boolean {
    const isIpv4 = /^\d{1,3}(\.\d{1,3}){3}$/.test(hostname);
    // `location.hostname` keeps the brackets of an IPv6 address; a bare one has colons.
    const isIpv6 = hostname.startsWith('[') || hostname.includes(':');

    return !isIpv4 && !isIpv6;
}

export function passkeysAllowedHere(): boolean {
    return passkeysAllowedOn(window.location.hostname);
}
