import { describe, it, expect, afterEach } from 'vitest';
import {
  configureEndpoints,
  getApiOrigin,
  getApiUrl,
  getReverbConfig,
  readEndpointOverrides
} from '../src/endpoints.js';

// The build constants (__API_URL__, __REVERB_*__) are not defined under
// vitest, so the "build value" fallback here is empty — which is exactly the
// case of a self-hosted install that relies on the embed tag alone.
describe('widget endpoints', () => {
  afterEach(() => configureEndpoints(undefined));

  it('uses the runtime API URL without a trailing slash', () => {
    configureEndpoints({ apiUrl: 'https://chat.example.test/widget/' });
    expect(getApiUrl()).toBe('https://chat.example.test/widget');
    expect(getApiOrigin()).toBe('https://chat.example.test');
  });

  it('falls back to the build value when no override is given', () => {
    configureEndpoints({});
    expect(getApiUrl()).toBe('');
    expect(getApiOrigin()).toBeNull();
  });

  it('rejects non-http(s) API URLs', () => {
    configureEndpoints({ apiUrl: 'javascript:alert(1)' });
    expect(getApiUrl()).toBe('');
  });

  it('builds the Reverb config from overrides', () => {
    configureEndpoints({
      reverb: { host: 'ws.example.test', port: '8080', key: 'k', scheme: 'http' }
    });
    expect(getReverbConfig()).toEqual({
      key: 'k',
      host: 'ws.example.test',
      port: 8080,
      scheme: 'http',
      forceTLS: false
    });
  });

  it('turns live chat off (no key/host) when nothing is configured', () => {
    configureEndpoints(undefined);
    const config = getReverbConfig();
    expect(config.key).toBeNull();
    expect(config.host).toBeNull();
    expect(config.port).toBe(443);
    expect(config.forceTLS).toBe(true);
  });

  it('reads only the endpoint attributes from the embed tag', () => {
    const tag = document.createElement('script');
    tag.setAttribute('data-api-url', 'https://chat.example.test/widget');
    tag.setAttribute('data-reverb-host', 'https://bad host');
    expect(readEndpointOverrides(tag)).toEqual({
      apiUrl: 'https://chat.example.test/widget',
      reverb: {}
    });
    expect(readEndpointOverrides(null)).toEqual({ reverb: {} });
  });
});
