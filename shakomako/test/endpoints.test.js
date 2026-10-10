import { describe, it, expect, afterEach } from 'vitest';
import {
  apiUrlFromScriptSrc,
  configureEndpoints,
  configureServerReverb,
  getApiOrigin,
  getApiUrl,
  getReverbConfig,
  readEndpointOverrides
} from '../src/endpoints.js';

// The build constants (__API_URL__, __REVERB_*__) are not defined under
// vitest, so the "build value" fallback here is empty — which is exactly the
// case of a self-hosted install that relies on the embed tag alone.
describe('widget endpoints', () => {
  afterEach(() => {
    configureEndpoints(undefined);
    configureServerReverb(undefined);
  });

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

  it('uses /widget on the server that served embed.js when nothing else is given', () => {
    configureEndpoints({ scriptApiUrl: 'https://chat.example.test/widget' });
    expect(getApiUrl()).toBe('https://chat.example.test/widget');
  });

  it('prefers data-api-url over the server that served embed.js', () => {
    configureEndpoints({
      apiUrl: 'https://api.example.test/widget',
      scriptApiUrl: 'https://cdn.example.test/widget'
    });
    expect(getApiUrl()).toBe('https://api.example.test/widget');
  });

  it('works out the API URL from the embed script address', () => {
    expect(apiUrlFromScriptSrc('https://chat.example.test/shakomako/embed.js')).toBe(
      'https://chat.example.test/widget'
    );
    expect(apiUrlFromScriptSrc('http://localhost:8000/shakomako/embed.js?v=1')).toBe(
      'http://localhost:8000/widget'
    );
    expect(apiUrlFromScriptSrc('')).toBeNull();
    expect(apiUrlFromScriptSrc('data:text/javascript,1')).toBeNull();
    expect(apiUrlFromScriptSrc(undefined)).toBeNull();
  });

  it('uses the Reverb settings the server sent when the tag and build give none', () => {
    configureEndpoints({ scriptApiUrl: 'https://chat.example.test/widget' });
    configureServerReverb({ key: 'server-key', host: 'ws.example.test', port: 6001, scheme: 'https' });
    expect(getReverbConfig()).toEqual({
      key: 'server-key',
      host: 'ws.example.test',
      port: 6001,
      scheme: 'https',
      forceTLS: true
    });
  });

  it('lets the embed tag override the Reverb settings the server sent', () => {
    configureEndpoints({ reverb: { host: 'tag.example.test', port: '8080', scheme: 'http' } });
    configureServerReverb({ key: 'server-key', host: 'ws.example.test', port: 6001, scheme: 'https' });
    expect(getReverbConfig()).toEqual({
      key: 'server-key',
      host: 'tag.example.test',
      port: 8080,
      scheme: 'http',
      forceTLS: false
    });
  });

  it('connects Reverb to the API server itself when no source gives a host', () => {
    configureEndpoints({ scriptApiUrl: 'http://localhost:8000/widget' });
    configureServerReverb({ key: 'server-key', host: null, port: null, scheme: null });
    expect(getReverbConfig()).toEqual({
      key: 'server-key',
      host: 'localhost',
      port: 8000,
      scheme: 'http',
      forceTLS: false
    });
  });

  it('uses the default port of the API scheme when the API URL has none', () => {
    configureEndpoints({ scriptApiUrl: 'https://chat.example.test/widget' });
    configureServerReverb({ key: 'server-key' });
    expect(getReverbConfig()).toMatchObject({
      host: 'chat.example.test',
      port: 443,
      scheme: 'https',
      forceTLS: true
    });
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

  it('adds the API URL of the server the embed tag loads from', () => {
    const tag = document.createElement('script');
    tag.setAttribute('src', 'https://chat.example.test/shakomako/embed.js');
    expect(readEndpointOverrides(tag)).toEqual({
      scriptApiUrl: 'https://chat.example.test/widget',
      reverb: {}
    });
  });
});
