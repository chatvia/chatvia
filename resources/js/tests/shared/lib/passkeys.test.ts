import { passkeysAllowedOn } from '@/shared/lib/passkeys';
import { describe, expect, it } from 'vitest';

// Browsers refuse passkeys on a bare IP address; a server with no domain is
// reached that way, so the passkey options must be hidden there.
describe('passkeysAllowedOn', () => {
    it.each([
        ['chat.example.com', true],
        ['localhost', true],
        ['192.168.56.10', false],
        ['[::1]', false],
        ['2001:db8::1', false],
    ])('%s → %s', (hostname, allowed) => {
        expect(passkeysAllowedOn(hostname)).toBe(allowed);
    });
});
