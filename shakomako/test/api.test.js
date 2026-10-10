import { describe, it, expect, afterEach, vi } from 'vitest';
import api from '../src/api.js';

describe('api()', () => {
  afterEach(() => {
    vi.unstubAllGlobals();
  });

  it('keeps the original error as the cause when a request times out', async () => {
    const timeout = new DOMException('The operation timed out.', 'TimeoutError');
    vi.stubGlobal('fetch', vi.fn().mockRejectedValue(timeout));

    const error = await api({ endpoint: 'sessions', method: 'GET' }).catch(e => e);

    expect(error.message).toBe('API request timeout: sessions');
    expect(error.cause).toBe(timeout);
  });

  it('rethrows other network errors unchanged', async () => {
    const failure = new TypeError('Failed to fetch');
    vi.stubGlobal('fetch', vi.fn().mockRejectedValue(failure));

    await expect(api({ endpoint: 'sessions', method: 'GET' })).rejects.toBe(failure);
  });
});
