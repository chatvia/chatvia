import api from './api.js';

export async function getSettings(agent_id) {
  const response = await api({
    endpoint: 'agents/settings',
    method: 'GET',
    headers: {
      'X-AGENT-ID': agent_id
    }
  });

  if (!response.ok) {
    throw new Error('Failed to fetch settings');
  }

  return await response.json();
}
