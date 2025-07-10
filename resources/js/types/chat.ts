export interface Message {
  id?: string | number
  content: string
  role: 'user' | 'assistant'
  model?: string
  created_at?: string
  updated_at?: string
}

export interface Model {
  external_id: string
  name: string
  description: string
  context: number
  icon: string
  features?: string[]
}

export interface Conversation {
  id: string
  title: string
  created_at: string
  updated_at: string
  messages?: Message[]
}

export interface User {
  id: number
  name: string
  email: string
  avatar?: string
} 