/**
 * Widget authentication for protected agents.
 *
 * A protected agent (is_public = false) embeds with a signed `data-auth` JWT —
 * a short-lived bootstrap token the embedding site signs per visitor. On load
 * the widget exchanges that token once for an opaque, longer-lived widget
 * session token, then sends the session token as `X-AUTH-TOKEN` on every gated
 * request. Tokens are kept per agent id so multiple widgets on one page never
 * share a credential.
 */

import { getApiUrl } from './endpoints.js';

// agentId -> { token: string, expiresAt: number|null }
const sessions = new Map();

/**
 * Current session token for an agent, or null when absent/expired.
 * @param {string} agentId
 * @returns {string|null}
 */
export function getAuthToken(agentId) {
  const entry = sessions.get(agentId);
  if (!entry) return null;
  if (entry.expiresAt !== null && Date.now() >= entry.expiresAt) {
    sessions.delete(agentId);
    return null;
  }
  return entry.token;
}

/**
 * Header object carrying the session token, or empty when there is none.
 * Spread into a request's headers to authorize a gated widget call.
 * @param {string} agentId
 * @returns {Object}
 */
export function authHeaders(agentId) {
  const token = getAuthToken(agentId);
  return token ? { 'X-AUTH-TOKEN': token } : {};
}

/**
 * @param {string} agentId
 * @param {string} token
 * @param {string|null} expiresAt - ISO 8601 timestamp, or null for no expiry
 */
export function setAuthToken(agentId, token, expiresAt = null) {
  const ms = expiresAt ? new Date(expiresAt).getTime() : NaN;
  sessions.set(agentId, { token, expiresAt: Number.isNaN(ms) ? null : ms });
}

export function clearAuthToken(agentId) {
  sessions.delete(agentId);
}

export class AuthManager {
  constructor(agentId, bootstrapToken = null) {
    this.agentId = agentId;
    this.bootstrapToken =
      typeof bootstrapToken === 'string' && bootstrapToken !== '' ? bootstrapToken : null;
  }

  hasBootstrapToken() {
    return this.bootstrapToken !== null;
  }

  /**
   * Exchange the signed bootstrap token (embed data-auth) for a widget session
   * token, storing it so {@link authHeaders} attaches it on later calls. Uses a
   * bare fetch rather than ./api.js to keep api.js -> auth.js the only edge
   * (api.js attaches the token this call produces).
   * @returns {Promise<boolean>} true when a session token was obtained
   */
  async exchange() {
    if (!this.hasBootstrapToken()) return false;

    try {
      const response = await fetch(`${getApiUrl()}/auth/exchange`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          Accept: 'application/json',
          'X-AGENT-ID': this.agentId
        },
        body: JSON.stringify({ token: this.bootstrapToken }),
        signal: AbortSignal.timeout ? AbortSignal.timeout(30000) : undefined
      });

      if (!response.ok) {
        console.error('chatvia: auth exchange failed:', response.status, response.statusText);
        return false;
      }

      const data = await response.json();
      if (!data || !data.auth_token) {
        console.error('chatvia: auth exchange returned no token');
        return false;
      }

      setAuthToken(this.agentId, data.auth_token, data.expires_at || null);
      return true;
    } catch (error) {
      console.error('chatvia: auth exchange error:', error);
      return false;
    }
  }
}
