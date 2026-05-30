import { defineConfig } from 'vitepress'

// https://vitepress.dev/reference/site-config
export default defineConfig({
  title: 'Chatvia Documentation',
  description:
    'Build, train and embed AI chatbots powered by your own content. Chatvia is a privacy-first, EU-hosted RAG platform.',
  cleanUrls: true,
  lastUpdated: true,

  // `auto` follows the OS preference; users can override with the toggle in
  // the nav. Set to `false` to disable the toggle entirely.
  appearance: 'auto',

  head: [
    ['link', { rel: 'icon', type: 'image/svg+xml', href: '/logo.svg' }],
  ],

  themeConfig: {
    logo: { src: '/logo.svg', alt: 'Chatvia' },
    siteTitle: 'Chatvia Docs',

    nav: [
      { text: 'Guide', link: '/guide/introduction' },
      { text: 'Embed', link: '/embed/widget' },
      { text: 'API reference', link: 'https://chatvia.ai/docs/api' },
      { text: 'chatvia.ai', link: 'https://chatvia.ai' },
    ],

    sidebar: {
      '/guide/': [
        {
          text: 'Getting Started',
          items: [
            { text: 'Introduction', link: '/guide/introduction' },
            { text: 'Quick start', link: '/guide/quick-start' },
            { text: 'Core concepts', link: '/guide/core-concepts' },
          ],
        },
        {
          text: 'Agents',
          items: [
            { text: 'Agent types', link: '/guide/agents/types' },
            { text: 'Creating an agent', link: '/guide/agents/create' },
            { text: 'Persona & instructions', link: '/guide/agents/persona' },
            { text: 'Model & generation', link: '/guide/agents/models' },
            { text: 'Appearance & branding', link: '/guide/agents/appearance' },
            { text: 'Conversation starters', link: '/guide/agents/starters' },
            { text: 'Playground', link: '/guide/agents/playground' },
          ],
        },
        {
          text: 'Knowledge sources',
          items: [
            { text: 'Overview', link: '/guide/sources/overview' },
            { text: 'Files', link: '/guide/sources/files' },
            { text: 'Web pages', link: '/guide/sources/web' },
            { text: 'YouTube', link: '/guide/sources/youtube' },
            { text: 'Text snippets', link: '/guide/sources/text' },
            { text: 'Q&A pairs', link: '/guide/sources/qa' },
          ],
        },
        {
          text: 'Tools & extensions',
          items: [
            { text: 'Functions (webhooks)', link: '/guide/tools/functions' },
            { text: 'MCP connectors', link: '/guide/tools/connectors' },
          ],
        },
        {
          text: 'Account & access',
          items: [
            { text: 'Profile', link: '/guide/account/profile' },
            { text: 'API keys', link: '/guide/account/api-keys' },
            { text: 'Identity verification', link: '/guide/account/identity' },
            { text: 'Prompts library', link: '/guide/account/prompts' },
            { text: 'Templates', link: '/guide/account/templates' },
            { text: 'Teams & roles', link: '/guide/account/teams' },
            { text: 'Usage & billing', link: '/guide/account/billing' },
          ],
        },
        {
          text: 'Conversations',
          items: [
            { text: 'Chat logs', link: '/guide/chat/logs' },
            { text: 'Leads', link: '/guide/chat/leads' },
            { text: 'Analytics', link: '/guide/chat/analytics' },
            { text: 'Human handoff', link: '/guide/chat/handoff' },
          ],
        },
      ],

      '/embed/': [
        {
          text: 'Embedding',
          items: [
            { text: 'Widget (chat bubble)', link: '/embed/widget' },
            { text: 'Full-page embed', link: '/embed/full-page' },
            { text: 'Protected agents', link: '/embed/protected' },
          ],
        },
      ],
    },

    search: {
      provider: 'local',
    },

    footer: {
      message: 'Privacy-first, EU-hosted, open-source friendly.',
      copyright: '© Chatvia',
    },
  },
})
