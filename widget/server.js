import http from 'http';
import { URL } from 'url';

// Create HTTP server
const server = http.createServer((req, res) => {
  // Add CORS headers for all responses
  res.setHeader('Access-Control-Allow-Origin', '*');
  res.setHeader('Access-Control-Allow-Methods', 'GET, POST, OPTIONS');
  res.setHeader('Access-Control-Allow-Headers', 'Content-Type');

  const url = new URL(req.url, `http://${req.headers.host}`);
  const path = url.pathname;
  const agentId = url.searchParams.get('agent_id');

  // Handle SSE endpoint
  if (path === '/events') {
    // Handle both GET and POST for SSE
    if (req.method === 'GET') {
      // Legacy GET support for EventSource
      // Set SSE headers
      res.writeHead(200, {
        'Content-Type': 'text/event-stream',
        'Cache-Control': 'no-cache',
        'Connection': 'keep-alive',
        'Access-Control-Allow-Origin': '*',
        'Access-Control-Allow-Headers': 'Cache-Control'
      });

      // Send initial connection message
      res.write('event: message\ndata: {"type": "connected", "content": "SSE connection established"}\n\n');

      // Store client connection
      const clientId = Date.now();
      clients.set(clientId, { res, agentId });

      // Handle client disconnect
      req.on('close', () => {
        console.log('Client disconnected (GET)');
        clients.delete(clientId);
      });

      req.on('error', (error) => {
        console.error('Client error (GET):', error);
        clients.delete(clientId);
      });

      return;
    } else if (req.method === 'POST') {
      // POST streaming endpoint
      let body = '';
      req.on('data', chunk => {
        body += chunk.toString();
      });

      req.on('end', () => {
        try {
          const message = JSON.parse(body);
          console.log('Received SSE connection request:', message);

          // Set SSE headers
          res.writeHead(200, {
            'Content-Type': 'text/event-stream',
            'Cache-Control': 'no-cache',
            'Connection': 'keep-alive',
            'Access-Control-Allow-Origin': '*',
            'Access-Control-Allow-Headers': 'Cache-Control'
          });

          // Send initial connection message
          res.write('event: message\ndata: {"type": "connected", "content": "SSE connection established via POST"}\n\n');

          // Store client connection
          const clientId = Date.now();
          clients.set(clientId, { res, agentId: message.agent_id });

          // Handle client disconnect
          req.on('close', () => {
            console.log('Client disconnected (POST)');
            clients.delete(clientId);
          });

          req.on('error', (error) => {
            console.error('Client error (POST):', error);
            clients.delete(clientId);
          });

        } catch (error) {
          console.error('Error parsing SSE connection request:', error);
          res.writeHead(400, { 'Content-Type': 'application/json' });
          res.end(JSON.stringify({ error: 'Invalid JSON' }));
        }
      });
      return;
    }
  }

  // Handle message sending endpoint
  if (path === '/send' && req.method === 'POST') {
    // Handle CORS preflight
    if (req.method === 'OPTIONS') {
      res.writeHead(200, {
        'Access-Control-Allow-Origin': '*',
        'Access-Control-Allow-Methods': 'POST, OPTIONS',
        'Access-Control-Allow-Headers': 'Content-Type'
      });
      res.end();
      return;
    }

    let body = '';
    req.on('data', chunk => {
      body += chunk.toString();
    });

    req.on('end', () => {
      try {
        const message = JSON.parse(body);
        console.log('Received message:', message);

        // Simulate streaming response to all connected clients
        simulateStreamingSSE(message);
        
        res.writeHead(200, { 
          'Content-Type': 'application/json',
          'Access-Control-Allow-Origin': '*'
        });
        res.end(JSON.stringify({ status: 'sent' }));
      } catch (error) {
        console.error('Error parsing message:', error);
        res.writeHead(400, { 
          'Content-Type': 'application/json',
          'Access-Control-Allow-Origin': '*'
        });
        res.end(JSON.stringify({ error: 'Invalid JSON' }));
      }
    });
    return;
  }

  // Default response
  res.writeHead(200, { 'Content-Type': 'text/plain' });
  res.end('SSE server is running');
});

// Store connected clients
const clients = new Map();

// Simulated responses for different types of messages
const responses = {
  greeting: [
    "Hello!",
    "How can I help you today?",
    "I'm here to assist you with any questions you might have."
  ],
  question: [
    "That's an interesting question.",
    "Let me think about that...",
    "I can help you with that!",
    "Here's what I know about that topic."
  ],
  default: [
    "I understand.",
    "That makes sense.",
    "I'm here to help.",
    "Is there anything else you'd like to know?"
  ]
};

// Function to simulate streaming response via SSE
function simulateStreamingSSE(message) {
  // Determine response type based on message content
  let responseType = 'default';
  if (message.content.toLowerCase().includes('hello') || message.content.toLowerCase().includes('hi')) {
    responseType = 'greeting';
  } else if (message.content.includes('?')) {
    responseType = 'question';
  }

  // Get response array
  const responseArray = responses[responseType];
  
  console.log(`Streaming response to ${clients.size} clients, type: ${responseType}`);
  
  // Send response to all connected clients
  clients.forEach((client, clientId) => {
    // Only send to clients with matching agent_id if specified
    if (message.agent_id && client.agentId && message.agent_id !== client.agentId) {
      console.log(`Skipping client ${clientId} - agent_id mismatch: ${message.agent_id} vs ${client.agentId}`);
      return;
    }

    console.log(`Streaming to client ${clientId}`);
    
    let currentIndex = 0;
    
    function sendNextChunk() {
      if (currentIndex < responseArray.length) {
        // Send current chunk
        const chunkData = JSON.stringify({
          type: 'stream',
          content: responseArray[currentIndex] + ' '
        });
        
        const sseMessage = `data: ${chunkData}\n\n`;
        console.log(`Sending chunk ${currentIndex + 1}/${responseArray.length} to client ${clientId}:`, chunkData);
        
        try {
          client.res.write(sseMessage);
        } catch (error) {
          console.error(`Error sending chunk to client ${clientId}:`, error);
          clients.delete(clientId);
          return;
        }
        
        currentIndex++;
        
        // Schedule next chunk
        setTimeout(sendNextChunk, Math.random() * 1000 + 500); // Random delay between 500-1500ms
      } else {
        // Send [DONE] marker when finished
        const doneData = JSON.stringify({
          type: 'stream',
          content: '[DONE]'
        });
        
        const doneMessage = `data: ${doneData}\n\n`;
        console.log(`Sending [DONE] to client ${clientId}`);
        
        try {
          client.res.write(doneMessage);
        } catch (error) {
          console.error(`Error sending [DONE] to client ${clientId}:`, error);
          clients.delete(clientId);
        }
      }
    }
    
    // Start streaming
    sendNextChunk();
  });
}

// Start the server
const PORT = process.env.PORT || 8080;
server.listen(PORT, () => {
  console.log(`SSE server is running on port ${PORT}`);
  console.log(`SSE URL: http://localhost:${PORT}/events`);
  console.log(`Send endpoint: http://localhost:${PORT}/send`);
}); 