import { authHeaders } from './auth.js';

/**
 * Make an API request with proper error handling
 * @param {Object} options - Request options
 * @param {string} options.endpoint - API endpoint
 * @param {string} options.method - HTTP method
 * @param {Object} options.headers - Additional headers
 * @param {string|null} options.body - Request body (JSON string)
 * @returns {Promise<Response>} - Fetch response
 */
export default async function api({ endpoint, method, headers = {}, body = null }) {
  const mergedHeaders = {
    'Content-Type': 'application/json',
    Accept: 'application/json',
    ...headers
  };

  // Attach the widget session token (X-AUTH-TOKEN) for protected agents. Keyed
  // off the X-AGENT-ID the caller already sets, so every gated api() call is
  // authorized without each call site knowing about auth.
  Object.assign(mergedHeaders, authHeaders(mergedHeaders['X-AGENT-ID']));

  /* global __API_URL__ */
  const apiUrl = __API_URL__;

  try {
    const response = await fetch(`${apiUrl}/${endpoint}`, {
      method: method,
      headers: mergedHeaders,
      body: body,
      // Add timeout via AbortController for better error handling
      signal: AbortSignal.timeout ? AbortSignal.timeout(30000) : undefined
    });

    return response;
  } catch (error) {
    // Re-throw with more context
    if (error.name === 'AbortError' || error.name === 'TimeoutError') {
      throw new Error(`API request timeout: ${endpoint}`);
    }
    throw error;
  }
}

/**
 * Multipart file upload — separate from api() because Content-Type must be
 * unset so the browser fills in the boundary, and we need a longer timeout
 * than the JSON RPC default.
 *
 * @param {Object} options
 * @param {string} options.endpoint - e.g. 'conversations/attachments'
 * @param {File} options.file - the File object to upload
 * @param {Object} options.headers - X-AGENT-ID, X-SESSION-ID, X-CONVERSATION-ID, etc.
 * @returns {Promise<Response>}
 */
export async function uploadFile({ endpoint, file, headers = {} }) {
  const mergedHeaders = { Accept: 'application/json', ...headers };
  Object.assign(mergedHeaders, authHeaders(mergedHeaders['X-AGENT-ID']));
  // Let fetch derive the multipart boundary.
  delete mergedHeaders['Content-Type'];

  const form = new FormData();
  form.append('file', file);

  /* global __API_URL__ */
  const apiUrl = __API_URL__;

  const response = await fetch(`${apiUrl}/${endpoint}`, {
    method: 'POST',
    headers: mergedHeaders,
    body: form,
    signal: AbortSignal.timeout ? AbortSignal.timeout(120000) : undefined
  });

  return response;
}
